<?php

namespace Modules\Glitter\Crm\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Crm\Enums\ContactStatus;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;

class IndexContactRequest extends FormRequest
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
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'user_id' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'assigned_user_id' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'sort' => ['nullable', 'string', Rule::in(ContactRepositoryInterface::SORTABLE_COLUMNS)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
