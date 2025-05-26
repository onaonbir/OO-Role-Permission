<?php

use OnaOnbir\OORolePermission\Services\OORolePermission;

if (! function_exists('oo_rp')) {

    function oo_rp()
    {
        return app()->make(OORolePermission::class);
    }
}
