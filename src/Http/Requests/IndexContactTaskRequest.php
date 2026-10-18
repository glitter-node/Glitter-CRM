<?php

namespace Modules\Glitter\Crm\Http\Requests;

class IndexContactTaskRequest extends IndexTaskRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['contact_uuid']);

        return $rules;
    }
}
