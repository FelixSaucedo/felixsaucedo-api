<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['slug', 'name', 'is_active', 'order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('order')->orderBy('id');
    }
}
