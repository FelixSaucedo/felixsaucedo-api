<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CaseStudy extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['slug', 'title', 'badge_text', 'badge_color_hex', 'context', 'problem', 'solution', 'tradeoffs', 'impact_metrics', 'is_featured', 'order'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'context' => 'array',
            'problem' => 'array',
            'solution' => 'array',
            'tradeoffs' => 'array',
            'impact_metrics' => 'array',
            'is_featured' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->orderBy('skills.order')->orderBy('skills.id');
    }
}
