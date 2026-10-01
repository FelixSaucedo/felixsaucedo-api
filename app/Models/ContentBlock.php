<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlock extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['section_id', 'slug', 'title', 'subtitle', 'body', 'icon', 'accent_color_hex', 'metadata', 'order'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'subtitle' => 'array',
            'body' => 'array',
            'metadata' => 'array',
            'order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
