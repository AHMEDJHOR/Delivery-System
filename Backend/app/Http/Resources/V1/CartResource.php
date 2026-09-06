<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $customer_id
 * @property-read int $menu_item_id
 * @property-read int $quantity
 * @property-read \App\Models\MenuItem $menuItem
 * @property-read \Carbon\Carbon|null $created_at
 * @property-read \Carbon\Carbon|null $updated_at
 */
class CartResource extends JsonResource
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
            'menu_item_id' => $this->menu_item_id,
            'quantity' => $this->quantity,
            'menu_item' => MenuItemResource::make($this->menuItem),
            'restaurant' => [
                'id' => $this->menuItem->restaurant->id,
                'name' => $this->menuItem->restaurant->name,
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

