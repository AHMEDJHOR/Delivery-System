<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $customer_id
 * @property-read int $restaurant_id
 * @property-read int|null $driver_id
 * @property-read string $subtotal
 * @property-read string $delivery_fee
 * @property-read string $total_amount
 * @property-read string $delivery_address
 * @property-read string|null $delivery_latitude
 * @property-read string|null $delivery_longitude
 * @property-read string $phone
 * @property-read string $status
 * @property-read \Carbon\Carbon|null $assigned_at
 * @property-read \Carbon\Carbon|null $delivered_at
 * @property-read \Carbon\Carbon|null $created_at
 * @property-read \Carbon\Carbon|null $updated_at
 */
class OrderResource extends JsonResource
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

            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ],

            'restaurant' => [
                'id' => $this->restaurant->id,
                'name' => $this->restaurant->name,
                'address' => $this->restaurant->address,
            ],

            'driver' => $this->driver
                ? [
                    'id' => $this->driver->id,
                    'user_id' => $this->driver->user_id,
                    'name' => $this->driver->user->name,
                    'phone' => $this->driver->user->phone,
                    'vehicle_type' => $this->driver->vehicle_type,
                ]
                : null,

            'order_items' => OrderItemResource::collection(
                $this->orderItems
            ),

            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total_amount' => $this->total_amount,

            'delivery' => [
                'address' => $this->delivery_address,
                'latitude' => $this->delivery_latitude,
                'longitude' => $this->delivery_longitude,
                'phone' => $this->phone,
            ],

            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'delivered_at' => $this->delivered_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
