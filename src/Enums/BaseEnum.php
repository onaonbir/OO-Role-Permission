<?php

namespace OnaOnbir\OORolePermission\Enums;

trait BaseEnum
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = ucfirst(strtolower(str_replace('_', ' ', $case->name)));
        }

        return $array;
    }

    public static function label(string $value): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', array_search($value, array_column(self::cases(), 'value')))));
    }
}
