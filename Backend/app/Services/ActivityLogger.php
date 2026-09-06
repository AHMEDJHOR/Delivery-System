<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    protected static string $channel = 'activity';

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function log(
        string $action,
        ?string $description = null,
        ?Request $request = null,
        ?array $extra = []
    ): void {
        $data = array_merge([
            'action' => $action,
            'description' => $description,
            'user_id' => Auth::id(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ], $extra);

        Log::channel(self::$channel)->info($description ?? $action, $data);
    }

    public static function auth(string $action, ?Request $request = null): void
    {
        self::log($action, "User {$action}", $request);
    }

    public static function login(?Request $request = null): void
    {
        self::auth('login', $request);
    }

    public static function logout(?Request $request = null): void
    {
        self::auth('logout', $request);
    }

    public static function register(?Request $request = null): void
    {
        self::auth('register', $request);
    }

    public static function passwordChanged(?Request $request = null): void
    {
        self::auth('password_changed', $request);
    }

    public static function passwordReset(?Request $request = null): void
    {
        self::auth('password_reset', $request);
    }

    public static function emailVerified(?Request $request = null): void
    {
        self::auth('email_verified', $request);
    }

    public static function restaurantCreated(?Request $request = null): void
{
    self::log(
        'restaurant_created',
        'Restaurant created',
        $request
    );
}

public static function restaurantUpdated(?Request $request = null): void
{
    self::log(
        'restaurant_updated',
        'Restaurant updated',
        $request
    );
}

public static function restaurantApprovalUpdated(?Request $request = null): void
{
    self::log(
        'restaurant_approval_updated',
        'Restaurant approval status updated',
        $request
    );
}

public static function restaurantDeleted(?Request $request = null): void
{
    self::log(
        'restaurant_deleted',
        'Restaurant deleted',
        $request
    );
}

    public static function menuItemCreated(?Request $request = null): void
    {
        self::log(
            'menu_item_created',
            'Menu item created',
            $request
        );
    }

    public static function menuItemUpdated(?Request $request = null): void
    {
        self::log(
            'menu_item_updated',
            'Menu item updated',
            $request
        );
    }

    public static function menuItemDeleted(?Request $request = null): void
    {
        self::log(
            'menu_item_deleted',
            'Menu item deleted',
            $request
        );
    }
    
    public static function cartItemAdded(?Request $request = null): void
    {
        self::log(
            'cart_item_added',
            'Cart item added',
            $request
        );
    }

    public static function cartItemUpdated(?Request $request = null): void
    {
        self::log(
            'cart_item_updated',
            'Cart item updated',
            $request
        );
    }

    public static function cartItemDeleted(?Request $request = null): void
    {
        self::log(
            'cart_item_deleted',
            'Cart item deleted',
            $request
        );
    }

    public static function cartCleared(?Request $request = null): void
    {
        self::log(
            'cart_cleared',
            'Cart cleared',
            $request
        );
    }

    public static function orderCreated(?Request $request = null): void
    {
        self::log(
            'order_created',
            'Order created',
            $request
        );
    }

    public static function orderStatusUpdated(?Request $request = null): void
    {
        self::log(
            'order_status_updated',
            'Order status updated',
            $request
        );
    }

    public static function orderDriverAssigned(?Request $request = null): void
    {
        self::log(
            'order_driver_assigned',
            'Driver assigned to order',
            $request
        );
    }

    public static function categoryCreated(?Request $request = null): void
    {
        self::log(
            'category_created',
            'Category created',
            $request
        );
    }

    public static function categoryUpdated(?Request $request = null): void
    {
        self::log(
            'category_updated',
            'Category updated',
            $request
        );
    }

    public static function categoryDeleted(?Request $request = null): void
    {
        self::log(
            'category_deleted',
            'Category deleted',
            $request
        );
    }
}
