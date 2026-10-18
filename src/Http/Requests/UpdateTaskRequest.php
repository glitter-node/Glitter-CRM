<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Http\Requests\Concerns\ValidatesTaskInput;

class UpdateTaskRequest extends FormRequest
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
            'assigned_to_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists(User::class, 'uuid'),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_at' => [
                'sometimes',
                'nullable',
                $this->timezoneAwareTaskDateRule('due_at_invalid'),
            ],
            'metadata' => [
                'sometimes',
                'nullable',
                'array',
                $this->taskMetadataObjectRule(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTaskStrings();
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }
}
