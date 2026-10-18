<?php

namespace Modules\Glitter\Crm\Http\Requests\Concerns;

use Closure;
use DateTimeImmutable;
use Throwable;

trait ValidatesActivityInput
{
    /**
     * Timezone offset 또는 Z가 포함된 ISO 8601 값만 허용합니다.
     */
    protected function timezoneAwareDateRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)
                || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', trim($value)) !== 1) {
                $fail(__('glitter-crm::module.validation.activity.occurred_at_invalid'));

                return;
            }

            try {
                new DateTimeImmutable(trim($value));
                $errors = DateTimeImmutable::getLastErrors();
                if (is_array($errors)
                    && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    $fail(__('glitter-crm::module.validation.activity.occurred_at_invalid'));
                }
            } catch (Throwable) {
                $fail(__('glitter-crm::module.validation.activity.occurred_at_invalid'));
            }
        };
    }

    /**
     * metadata는 JSON object에 해당하는 연관 배열이어야 합니다.
     */
    protected function metadataObjectRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                $fail(__('glitter-crm::module.validation.activity.metadata_invalid'));
            }
        };
    }

    protected function prepareActivityStrings(): void
    {
        foreach (['search', 'subject', 'body', 'type'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }
}
