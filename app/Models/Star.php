<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Star extends Pivot
{
    protected $table = 'skill_user';

    public $incrementing = false;

    protected $fillable = ['skill_id', 'user_id', 'starred_at'];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
