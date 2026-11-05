<?php

namespace App\Models;

use LogicException;
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Log audit tidak dapat diubah.');
        });

        static::deleting(function (): never {
            throw new LogicException('Log audit tidak dapat dihapus.');
        });
    }
}
