<?php

namespace App\Models;

use App\Services\SkillContent;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'slug', 'name', 'summary', 'author_id',
    'version', 'installs',
    'monogram_tint', 'tags', 'readme', 'usage', 'visibility', 'github_url', 'created_by_user_id',
])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'summary', 'version', 'visibility', 'slug'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'installs' => 'integer',
            'monogram_tint' => 'integer',
            'tags' => 'array',
            'visibility' => 'string',
        ];
    }

    public function getUpdatedAttribute(): string
    {
        return $this->updated_at->diffForHumans(['parts' => 1]);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function changelogEntries(): HasMany
    {
        return $this->hasMany(SkillChangelogEntry::class)->orderByDesc('released_on');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SkillFile::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(Star::class)->withPivot('starred_at');
    }

    public function getContent(): SkillContent
    {
        return new SkillContent($this);
    }
}
