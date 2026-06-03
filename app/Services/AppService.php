<?php

namespace App\Services;

use Illuminate\Support\Str;

class AppService
{
    public function getAppName(): string
    {
        return config('app.name') ?: 'enki';
    }

    public function getAppSlug(): string
    {
        $machineName = config('app.machine_name');

        return $machineName ?: Str::slug($this->getAppName());
    }
}
