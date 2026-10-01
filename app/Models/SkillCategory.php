<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillCategory extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['slug', 'name', 'default_accent_color', 'order'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'order' => 'integer',
        ];
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'skill_category_id')->orderBy('order')->orderBy('id');
    }
}
