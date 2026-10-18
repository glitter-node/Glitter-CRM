<?php

namespace Modules\Glitter\Crm\Enums;

/**
 * CRM 고객과 관련된 명시적 상호작용 유형.
 */
enum ActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case StatusChange = 'status_change';
    case System = 'system';

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
        return 'glitter-crm::module.enums.activity_type.'.$this->value;
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
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
