<?php

namespace App\Services;

use App\Jobs\CalculateDeliveryFee;
use App\Models\CartItem;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{

   /**
 * Get orders accessible to the authenticated user.
 *
 * @return LengthAwarePaginator<int, Order>
 */
public function getAccessibleOrders(User $user): LengthAwarePaginator
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
    ->paginate(15);
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

    return DB::transaction(
        function () use (
            $customer,
            $restaurant,
            $cartItems,
            $data
        ): Order {
            $subtotalCents = 0;

            $order = new Order();
            $order->customer_id = $customer->id;
            $order->restaurant_id = $restaurant->id;
            $order->delivery_address = $data['delivery_address'];
            $order->delivery_latitude = $data['delivery_latitude'];
            $order->delivery_longitude = $data['delivery_longitude'];
            $order->phone = $data['phone'];
            $order->status = 'pending';

            // Delivery fee is calculated asynchronously after checkout.
            $order->delivery_status = 'pending';
            $order->delivery_fee = '0.00';
            $order->subtotal = '0.00';
            $order->total_amount = '0.00';

            $order->save();

            foreach ($cartItems as $cartItem) {
                $menuItem = $cartItem->menuItem;

                $quantity = (int) $cartItem->quantity;

                $unitPriceCents = $this->moneyToCents(
                    $menuItem->price
                );

                $itemSubtotalCents = $quantity * $unitPriceCents;

                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->menu_item_id = $menuItem->id;
                $orderItem->item_name = $menuItem->name;
                $orderItem->quantity = $quantity;
                $orderItem->unit_price = number_format(
                    $unitPriceCents / 100,
                    2,
                    '.',
                    ''
                );
                $orderItem->subtotal = number_format(
                    $itemSubtotalCents / 100,
                    2,
                    '.',
                    ''
                );
                $orderItem->save();

                $subtotalCents += $itemSubtotalCents;
            }

            $order->subtotal = number_format(
                $subtotalCents / 100,
                2,
                '.',
                ''
            );

            // Final total will be updated by CalculateDeliveryFee.
            $order->total_amount = number_format(
                $subtotalCents / 100,
                2,
                '.',
                ''
            );

            $order->save();

            CartItem::query()
                ->where('customer_id', $customer->id)
                ->delete();

            CalculateDeliveryFee::dispatch($order->id)
                ->afterCommit();

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
    return DB::transaction(function () use (
        $order,
        $driverId
    ): Order {
        $order = Order::query()
            ->lockForUpdate()
            ->findOrFail($order->id);

        if ($order->driver_id !== null) {
            throw new DomainException(
                'A driver is already assigned to this order.'
            );
        }

        if ($order->status !== 'ready_for_pickup') {
            throw new DomainException(
                'Only orders ready for pickup can be assigned to a driver.'
            );
        }

        $driver = DriverProfile::query()
               ->with('user')
                ->lockForUpdate()
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
    });
}

/**
 * Convert a decimal monetary value to integer cents.
 */
private function moneyToCents(int|float|string $amount): int
{
    $value = (string) $amount;

    if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        throw new DomainException(
            'Invalid monetary value.'
        );
    }

    [$whole, $fraction] = array_pad(
        explode('.', $value, 2),
        2,
        '0'
    );

    $fraction = str_pad($fraction, 2, '0');

    return ((int) $whole * 100) + (int) $fraction;
}
}
