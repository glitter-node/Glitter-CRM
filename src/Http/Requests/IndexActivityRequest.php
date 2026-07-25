<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Http\Requests\Concerns\ValidatesActivityInput;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;

class IndexActivityRequest extends FormRequest
{
    use ValidatesActivityInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'contact_uuid' => [
                'nullable',
                'uuid',
                Rule::exists(Contact::class, 'uuid')->whereNull('deleted_at'),
            ],
            'type' => ['nullable', Rule::enum(ActivityType::class)],
            'created_by_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'occurred_from' => ['nullable', $this->timezoneAwareDateRule()],
            'occurred_to' => ['nullable', $this->timezoneAwareDateRule()],
            'sort' => [
                'nullable',
                'string',
                Rule::in(ActivityRepositoryInterface::SORTABLE_COLUMNS),
            ],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('occurred_from');
            $to = $this->input('occurred_to');
            if (! is_string($from) || ! is_string($to) || $validator->errors()->isNotEmpty()) {
                return;
            }

            if (new DateTimeImmutable($to) < new DateTimeImmutable($from)) {
                $validator->errors()->add(
                    'occurred_to',
                    __('glitter-crm::module.validation.activity.date_range_invalid'),
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareActivityStrings();
    }
}
