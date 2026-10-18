<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Helpers\TimezoneHelper;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Crm\Enums\ContactStatus;

class UpdateContactRequest extends FormRequest
{
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
            'user_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'assigned_user_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'status' => ['sometimes', Rule::enum(ContactStatus::class)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:50'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:50', 'timezone:all'],
            'source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'next_action_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'tag_uuids' => ['sometimes', 'nullable', 'array'],
            'tag_uuids.*' => ['uuid', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * 전달된 datetime-local 입력만 프로젝트 표준 사이트 타임존 기준 UTC 값으로 변환합니다.
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key === null && ! empty($data['next_action_at'])) {
            $data['next_action_at'] = TimezoneHelper::fromSiteDateTime($data['next_action_at']);
        }

        return $data;
    }
}
