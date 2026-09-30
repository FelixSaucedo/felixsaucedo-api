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
            'period' => $this->period,
            'company' => $this->company,
            'order' => $this->order,
            'role' => $this->resource->getLocalized('role'),
            'location' => $this->resource->getLocalized('location'),
            'highlights' => $this->resource->getLocalized('highlights'),
            'translations' => [
                'role' => $this->role,
                'location' => $this->location,
                'highlights' => $this->highlights,
            ],
        ];
    }
}
