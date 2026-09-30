<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;

class CareerMilestone extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['period', 'role', 'company', 'location', 'highlights', 'order'];

    protected function casts(): array
    {
        return [
            'role' => 'array',
            'location' => 'array',
            'highlights' => 'array',
            'order' => 'integer',
        ];
    }
}
