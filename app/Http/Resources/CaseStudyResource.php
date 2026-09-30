<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseStudyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'is_featured' => $this->is_featured,
            'order' => $this->order,
            'title' => $this->resource->getLocalized('title'),
            'context' => $this->resource->getLocalized('context'),
            'problem' => $this->resource->getLocalized('problem'),
            'solution' => $this->resource->getLocalized('solution'),
            'tradeoffs' => $this->resource->getLocalized('tradeoffs'),
            'impact_metrics' => $this->resource->getLocalized('impact_metrics'),
            'translations' => [
                'title' => $this->title,
                'context' => $this->context,
                'problem' => $this->problem,
                'solution' => $this->solution,
                'tradeoffs' => $this->tradeoffs,
                'impact_metrics' => $this->impact_metrics,
            ],
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
