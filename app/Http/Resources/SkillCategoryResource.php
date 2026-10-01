<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'default_accent_color' => $this->default_accent_color,
            'order' => $this->order,
            'name' => $this->resource->getLocalized('name'),
            'translations' => [
                'name' => $this->name,
            ],
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
