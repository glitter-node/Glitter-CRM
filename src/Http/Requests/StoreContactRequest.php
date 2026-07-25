<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Helpers\TimezoneHelper;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Crm\Enums\ContactStatus;

class StoreContactRequest extends FormRequest
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
            'user_id' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'assigned_user_id' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'source' => ['nullable', 'string', 'max:100'],
            'next_action_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'tag_uuids' => ['nullable', 'array'],
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
     * datetime-local 입력을 프로젝트 표준 사이트 타임존 기준 UTC 값으로 변환합니다.
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
