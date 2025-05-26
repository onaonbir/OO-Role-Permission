<?php

namespace OnaOnbir\OORolePermission\Enums;

enum OORoleType: string
{
    case SYSTEM_DEFAULT = 'system_default';
    case EDITABLE = 'editable';

    public static function default(): self
    {
        return self::EDITABLE;
    }

    /**
     * Case'lerin label'lerini döndürür.
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_DEFAULT => 'Sistem',
            self::EDITABLE => 'Düzenlenebilir',
        };
    }

    /**
     * Tüm label'leri döndürür.
     */
    public static function labels(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }

    public function color(): string
    {
        return match ($this) {
            self::SYSTEM_DEFAULT => 'gray',
            self::EDITABLE => 'blue',
        };
    }
}
