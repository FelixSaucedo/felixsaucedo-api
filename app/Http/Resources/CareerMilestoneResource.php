<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->resource->getLocalized('period'),
            'company' => $this->company,
            'accent_color_hex' => $this->accent_color_hex,
            'order' => $this->order,
            'role' => $this->resource->getLocalized('role'),
            'location' => $this->resource->getLocalized('location'),
            'highlights' => $this->resource->getLocalized('highlights'),
            'translations' => [
                'period' => $this->period,
                'role' => $this->role,
                'location' => $this->location,
                'highlights' => $this->highlights,
            ],
        ];
    }
}
