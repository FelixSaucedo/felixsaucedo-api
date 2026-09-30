<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['category_id', 'name', 'slug', 'summary', 'badge_color', 'is_highlight', 'order'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'is_highlight' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
    }

    public function caseStudies(): BelongsToMany
    {
        return $this->belongsToMany(CaseStudy::class);
    }
}
