<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $description
 * @property-read string $address
 * @property-read string|null $latitude
 * @property-read string|null $longitude
 * @property-read string $phone
 * @property-read string|null $logo
 * @property-read string $approval_status
 * @property-read string $status
 * @property-read \Carbon\Carbon|null $created_at
 * @property-read \Carbon\Carbon|null $updated_at
 */
class RestaurantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
      public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->description,
        'address' => $this->address,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'phone' => $this->phone,
        'logo' => $this->logo
            ? asset('storage/' . $this->logo)
            : null,
        'approval_status' => $this->approval_status,
        'status' => $this->status,
        'created_at' => $this->created_at?->toDateTimeString(),
        'updated_at' => $this->updated_at?->toDateTimeString(),
    ];
}
}