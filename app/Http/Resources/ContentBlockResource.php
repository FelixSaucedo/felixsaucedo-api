<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'metadata' => $this->metadata,
            'order' => $this->order,
            'title' => $this->resource->getLocalized('title'),
            'subtitle' => $this->resource->getLocalized('subtitle'),
            'body' => $this->resource->getLocalized('body'),
            'translations' => [
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'body' => $this->body,
            ],
        ];
    }
}
