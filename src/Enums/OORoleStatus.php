<?php

namespace OnaOnbir\OORolePermission\Enums;

enum OORoleStatus: string
{
    use BaseEnum;

    case DRAFT = 'draft';
    case PASSIVE = 'passive';
    case ACTIVE = 'active';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Taslak',
            self::PASSIVE => 'Pasif',
            self::ACTIVE => 'Aktif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::PASSIVE => 'danger',
            self::ACTIVE => 'success',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function optionsNested(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $select = [
                'label' => $case->label(),
                'value' => $case->value,
            ];
            $options[] = $select;
        }

        return $options;
    }

    public static function colors(): array
    {
        $colors = [];
        foreach (self::cases() as $case) {
            $colors[$case->value] = $case->color();
        }

        return $colors;
    }
}
