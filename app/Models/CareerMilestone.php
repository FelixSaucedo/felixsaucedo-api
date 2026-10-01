<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;

class CareerMilestone extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['period', 'role', 'company', 'accent_color_hex', 'location', 'highlights', 'order'];

    protected function casts(): array
    {
        return [
            'period' => 'array',
            'role' => 'array',
            'location' => 'array',
            'highlights' => 'array',
            'order' => 'integer',
        ];
    }
}
