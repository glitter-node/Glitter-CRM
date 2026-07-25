<?php

namespace Modules\Glitter\Crm\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    public function labelKey(): string
    {
        return 'glitter-crm::module.enums.task_priority.'.$this->value;
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
            fn (self $priority): array => [
                'value' => $priority->value,
                'label' => $priority->label(),
            ],
            self::cases(),
        );
    }
}
