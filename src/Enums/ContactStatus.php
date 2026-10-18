<?php

namespace Modules\Glitter\Crm\Enums;

/**
 * CRM 고객의 업무 상태.
 */
enum ContactStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public function labelKey(): string
    {
        return 'glitter-crm::module.enums.contact_status.'.$this->value;
    }

    public function label(): string
    {
        return __($this->labelKey());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
