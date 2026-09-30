<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'badge_color' => $this->badge_color,
            'is_highlight' => $this->is_highlight,
            'order' => $this->order,
            'summary' => $this->resource->getLocalized('summary'),
            'translations' => [
                'summary' => $this->summary,
            ],
        ];
    }
}
