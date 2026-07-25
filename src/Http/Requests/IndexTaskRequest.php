<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Http\Requests\Concerns\ValidatesTaskInput;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;

class IndexTaskRequest extends FormRequest
{
    use ValidatesTaskInput;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'contact_uuid' => [
                'nullable',
                'uuid',
                Rule::exists(Contact::class, 'uuid')->whereNull('deleted_at'),
            ],
            'assigned_to_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_from' => ['nullable', $this->timezoneAwareTaskDateRule('due_from_invalid')],
            'due_to' => ['nullable', $this->timezoneAwareTaskDateRule('due_to_invalid')],
            'overdue' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! in_array($value, [true, 1, '1', 'true'], true)) {
                        $fail(__('glitter-crm::module.validation.task.overdue_invalid'));
                    }
                },
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in(TaskRepositoryInterface::SORTABLE_COLUMNS),
            ],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('due_from');
            $to = $this->input('due_to');
            if (! is_string($from) || ! is_string($to) || $validator->errors()->isNotEmpty()) {
                return;
            }

            if (new DateTimeImmutable($to) < new DateTimeImmutable($from)) {
                $validator->errors()->add(
                    'due_to',
                    __('glitter-crm::module.validation.task.date_range_invalid'),
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTaskStrings();
        if (in_array($this->input('overdue'), [true, 1, '1', 'true'], true)) {
            $this->merge(['overdue' => true]);
        }
    }
}
