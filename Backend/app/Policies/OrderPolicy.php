<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return match ($user->role) {
            'admin' => true,

            'customer' => $order->customer_id === $user->id,

            'restaurant_manager' => $order->restaurant
                ->manager_id === $user->id,

            'driver' => $order->driver_id !== null
                && $order->driver_id === $user->driverProfile?->id,

            default => false,
        };
    }

    /**
     * Determine whether the user can update the order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return match ($user->role) {
            'admin' => true,

            'restaurant_manager' => $order->restaurant
                ->manager_id === $user->id,

            'driver' => $order->driver_id !== null
                && $order->driver_id === $user->driverProfile?->id,

            default => false,
        };
    }

    /**
     * Determine whether the user can assign a driver.
     */
    public function assignDriver(User $user, Order $order): bool
    {
        return $user->role === 'admin';
    }
}