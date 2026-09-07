<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private DeliveryDistanceService $deliveryDistanceService
    ) {
    }

    /**
     * Get orders accessible to the authenticated user.
     */
    public function getAccessibleOrders(User $user): Collection
    {
        $query = Order::query()
            ->with([
                'customer',
                'restaurant',
                'driver.user',
                'orderItems',
            ]);

        match ($user->role) {
            'admin' => null,

            'customer' => $query->where(
                'customer_id',
                $user->id
            ),

            'restaurant_manager' => $query->whereHas(
                'restaurant',
                function ($restaurantQuery) use ($user): void {
                    $restaurantQuery->where(
                        'manager_id',
                        $user->id
                    );
                }
            ),

            'driver' => $user->driverProfile === null
                ? $query->whereRaw('1 = 0')
                : $query->where(
                    'driver_id',
                    $user->driverProfile->id
                ),

            default => $query->whereRaw('1 = 0'),
        };

        return $query
            ->latest()
            ->get();
    }

    /**
     * Create an order from the customer's cart.
     *
     * @param array{
     *     delivery_address: string,
     *     delivery_latitude: int|float|string,
     *     delivery_longitude: int|float|string,
     *     phone: string
     * } $data
     */
    public function createOrder(User $customer, array $data): Order
    {
        $cartItems = CartItem::query()
            ->where('customer_id', $customer->id)
            ->with([
                'menuItem.restaurant',
            ])
            ->get();

        if ($cartItems->isEmpty()) {
            throw new DomainException(
                'Your cart is empty.'
            );
        }

        $restaurant = $cartItems->first()->menuItem->restaurant;

        if (
            $restaurant->approval_status !== 'approved'
            || $restaurant->status !== 'active'
        ) {
            throw new DomainException(
                'The restaurant is not currently available.'
            );
        }

        foreach ($cartItems as $cartItem) {
            $menuItem = $cartItem->menuItem;

            if (
                ! $menuItem->is_available
                || $menuItem->restaurant_id !== $restaurant->id
                || $menuItem->restaurant->approval_status !== 'approved'
                || $menuItem->restaurant->status !== 'active'
            ) {
                throw new DomainException(
                    "Menu item '{$menuItem->name}' is no longer available."
                );
            }
        }

        if (
            $restaurant->latitude === null
            || $restaurant->longitude === null
        ) {
            throw new DomainException(
                'Restaurant location is not available for delivery calculation.'
            );
        }

        $distanceKm = $this->deliveryDistanceService->calculateDistanceKm(
            (float) $restaurant->latitude,
            (float) $restaurant->longitude,
            (float) $data['delivery_latitude'],
            (float) $data['delivery_longitude']
        );

        $serviceFee = (float) config(
            'delivery.service_fee',
            20.00
        );

        $distanceRatePerKm = (float) config(
            'delivery.distance_rate_per_km',
            20.00
        );

        $deliveryFee = round(
            $serviceFee + ($distanceKm * $distanceRatePerKm),
            2
        );

        return DB::transaction(
            function () use (
                $customer,
                $restaurant,
                $cartItems,
                $data,
                $deliveryFee
            ): Order {
                $subtotal = 0.00;

                $order = new Order();
                $order->customer_id = $customer->id;
                $order->restaurant_id = $restaurant->id;
                $order->delivery_address = $data['delivery_address'];
                $order->delivery_latitude = $data['delivery_latitude'];
                $order->delivery_longitude = $data['delivery_longitude'];
                $order->phone = $data['phone'];
                $order->status = 'pending';
                $order->delivery_fee = $deliveryFee;
                $order->subtotal = 0.00;
                $order->total_amount = 0.00;
                $order->save();

                foreach ($cartItems as $cartItem) {
                    $menuItem = $cartItem->menuItem;

                    $quantity = (int) $cartItem->quantity;
                    $unitPrice = (float) $menuItem->price;

                    $itemSubtotal = round(
                        $quantity * $unitPrice,
                        2
                    );

                    $orderItem = new OrderItem();
                    $orderItem->order_id = $order->id;
                    $orderItem->menu_item_id = $menuItem->id;
                    $orderItem->item_name = $menuItem->name;
                    $orderItem->quantity = $quantity;
                    $orderItem->unit_price = $unitPrice;
                    $orderItem->subtotal = $itemSubtotal;
                    $orderItem->save();

                    $subtotal += $itemSubtotal;
                }

                $subtotal = round($subtotal, 2);

                $order->subtotal = $subtotal;
                $order->total_amount = round(
                    $subtotal + $deliveryFee,
                    2
                );
                $order->save();

                CartItem::query()
                    ->where('customer_id', $customer->id)
                    ->delete();

                return $order;
            }
        );
    }

    /**
     * Update an order status according to the role-specific state machine.
     */
    public function updateStatus(
        User $user,
        Order $order,
        string $newStatus
    ): Order {
        $allowedTransitions = match ($user->role) {
            'restaurant_manager' => [
                'pending' => [
                    'preparing',
                    'rejected',
                ],
                'preparing' => [
                    'ready_for_pickup',
                ],
            ],

            'driver' => [
                'ready_for_pickup' => [
                    'in_transit',
                ],
                'in_transit' => [
                    'delivered',
                ],
            ],

            'admin' => [
                'pending' => [
                    'preparing',
                    'rejected',
                    'cancelled',
                ],
                'preparing' => [
                    'ready_for_pickup',
                    'rejected',
                    'cancelled',
                ],
                'ready_for_pickup' => [
                    'in_transit',
                    'cancelled',
                ],
                'in_transit' => [
                    'delivered',
                    'cancelled',
                ],
                'delivered' => [],
                'cancelled' => [],
                'rejected' => [],
            ],

            default => [],
        };

        $currentStatus = $order->status;

        if (
            ! isset($allowedTransitions[$currentStatus])
            || ! in_array(
                $newStatus,
                $allowedTransitions[$currentStatus],
                true
            )
        ) {
            throw new DomainException(
                "Order cannot transition from {$currentStatus} to {$newStatus}."
            );
        }

        if (
            $newStatus === 'in_transit'
            && $order->driver_id === null
        ) {
            throw new DomainException(
                'An order must have an assigned driver before it can be marked in transit.'
            );
        }

        $order->status = $newStatus;

        if ($newStatus === 'delivered') {
            $order->delivered_at = now();
        }

        $order->save();

        return $order->refresh();
    }

    /**
     * Assign an approved and online driver to an order.
     */
    public function assignDriver(
        Order $order,
        int $driverId
    ): Order {
        if ($order->status !== 'ready_for_pickup') {
            throw new DomainException(
                'Only orders ready for pickup can be assigned to a driver.'
            );
        }

        $driver = DriverProfile::query()
            ->with('user')
            ->find($driverId);

        if ($driver === null) {
            throw new DomainException(
                'Driver not found.'
            );
        }

        if ($driver->approval_status !== 'approved') {
            throw new DomainException(
                'The driver is not approved.'
            );
        }

        if (! $driver->is_online) {
            throw new DomainException(
                'The driver is currently offline.'
            );
        }

        $hasActiveDelivery = Order::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [
                'ready_for_pickup',
                'in_transit',
            ])
            ->exists();

        if ($hasActiveDelivery) {
            throw new DomainException(
                'The driver already has an active delivery.'
            );
        }

        $order->driver_id = $driver->id;
        $order->assigned_at = now();
        $order->save();

        return $order->refresh();
    }
}
