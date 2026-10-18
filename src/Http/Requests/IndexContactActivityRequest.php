<?php

namespace Modules\Glitter\Crm\Http\Requests;

class IndexContactActivityRequest extends IndexActivityRequest
{
    /**
     * Nested endpoint에서는 route Contact가 유일한 Contact filter다.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['contact_uuid']);

        return $rules;
    }
}
