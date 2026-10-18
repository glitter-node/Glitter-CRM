<?php

namespace Modules\Glitter\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Http\Requests\Concerns\ValidatesActivityInput;

class UpdateActivityRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in($this->adminWritableTypes())],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'occurred_at' => ['sometimes', $this->timezoneAwareDateRule()],
            'metadata' => [
                'sometimes',
                'nullable',
                'array',
                $this->metadataObjectRule(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareActivityStrings();

        foreach (['subject', 'body'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function adminWritableTypes(): array
    {
        return array_values(array_filter(
            ActivityType::values(),
            fn (string $type): bool => $type !== ActivityType::System->value,
        ));
    }
}
