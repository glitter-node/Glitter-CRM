<?php

namespace Modules\Glitter\Crm\Http\Requests\Concerns;

use Closure;
use DateTimeImmutable;
use Throwable;

trait ValidatesTaskInput
{
    protected function timezoneAwareTaskDateRule(string $messageKey): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($messageKey): void {
            if (! is_string($value)
                || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', trim($value)) !== 1) {
                $fail(__("glitter-crm::module.validation.task.{$messageKey}"));

                return;
            }

            try {
                new DateTimeImmutable(trim($value));
                $errors = DateTimeImmutable::getLastErrors();
                if (is_array($errors)
                    && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    $fail(__("glitter-crm::module.validation.task.{$messageKey}"));
                }
            } catch (Throwable) {
                $fail(__("glitter-crm::module.validation.task.{$messageKey}"));
            }
        };
    }

    protected function taskMetadataObjectRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                $fail(__('glitter-crm::module.validation.task.metadata_invalid'));
            }
        };
    }

    protected function prepareTaskStrings(): void
    {
        foreach ([
            'search',
            'title',
            'description',
            'status',
            'priority',
            'direction',
            'sort',
        ] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }
}
