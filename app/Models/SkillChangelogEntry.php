<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $released_on */
#[Fillable(['skill_id', 'version', 'released_on', 'notes'])]
class SkillChangelogEntry extends Model
{
    public $timestamps = false;

    #[\Override]
    protected function casts(): array
    {
        return [
            'released_on' => 'date',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
