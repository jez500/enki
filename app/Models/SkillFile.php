<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['skill_id', 'path', 'size', 'kind', 'content', 'sort_order'])]
class SkillFile extends Model
{
    public $timestamps = false;

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
