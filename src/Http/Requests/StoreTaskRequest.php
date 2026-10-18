<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Http\Requests\Concerns\ValidatesTaskInput;

class StoreTaskRequest extends FormRequest
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
            'contact_uuid' => ['required', 'uuid'],
            'assigned_to_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => [Rule::enum(TaskStatus::class)],
            'priority' => [Rule::enum(TaskPriority::class)],
            'due_at' => [
                'nullable',
                $this->timezoneAwareTaskDateRule('due_at_invalid'),
            ],
            'metadata' => ['nullable', 'array', $this->taskMetadataObjectRule()],
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
