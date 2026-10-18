<?php

namespace Modules\Glitter\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Http\Requests\Concerns\ValidatesActivityInput;

class StoreActivityRequest extends FormRequest
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
            'contact_uuid' => ['required', 'uuid'],
            'type' => [
                'required',
                Rule::in($this->adminWritableTypes()),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'occurred_at' => ['required', $this->timezoneAwareDateRule()],
            'metadata' => ['nullable', 'array', $this->metadataObjectRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['subject', 'body'])) {
                return;
            }

            if ($this->input('subject') === null && $this->input('body') === null) {
                $validator->errors()->add(
                    'body',
                    __('glitter-crm::module.validation.activity.content_required'),
                );
            }
        });
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
