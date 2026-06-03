<?php

namespace App\Services;

use Illuminate\Support\Str;

class AppService
{
    public function getAppName(): string
    {
        return filled(config('app.name')) ? config('app.name') : 'enki';
    }

    public function getAppSlug(): string
    {
        $machineName = config('app.machine_name');

        return filled($machineName) ? $machineName : Str::slug($this->getAppName());
    }
}
