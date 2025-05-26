<?php

namespace OnaOnbir\OORolePermission\Models\Traits;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class JsonCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return json_decode($value, JSON_UNESCAPED_UNICODE);
    }

    public function set($model, $key, $value, $attributes)
    {
        return [$key => json_encode($value, JSON_UNESCAPED_UNICODE)];
    }
}
