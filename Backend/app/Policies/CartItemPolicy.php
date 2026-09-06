<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;

class CartItemPolicy
{
    public function view(User $user, CartItem $cartItem): bool
    {
        return $user->id === $cartItem->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'customer';
    }

    public function update(User $user, CartItem $cartItem): bool
    {
        return $user->id === $cartItem->customer_id;
    }

    public function delete(User $user, CartItem $cartItem): bool
    {
        return $user->id === $cartItem->customer_id;
    }
}