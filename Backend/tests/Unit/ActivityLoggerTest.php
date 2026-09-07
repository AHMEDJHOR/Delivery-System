<?php

namespace Tests\Unit;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    public function test_restaurant_created_is_logged(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('activity')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Restaurant created',
                \Mockery::on(function (array $data): bool {
                    return $data['action'] === 'restaurant_created';
                })
            );

        $request = Request::create('/api/v1/restaurants', 'POST');

        ActivityLogger::restaurantCreated($request);
    }

    public function test_restaurant_updated_is_logged(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('activity')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Restaurant updated',
                \Mockery::on(function (array $data): bool {
                    return $data['action'] === 'restaurant_updated';
                })
            );

        $request = Request::create('/api/v1/restaurants/1', 'PUT');

        ActivityLogger::restaurantUpdated($request);
    }

    public function test_restaurant_approval_updated_is_logged(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('activity')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Restaurant approval status updated',
                \Mockery::on(function (array $data): bool {
                    return $data['action'] === 'restaurant_approval_updated';
                })
            );

        $request = Request::create(
            '/api/v1/restaurants/1/approval-status',
            'PATCH'
        );

        ActivityLogger::restaurantApprovalUpdated($request);
    }

    public function test_restaurant_deleted_is_logged(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('activity')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Restaurant deleted',
                \Mockery::on(function (array $data): bool {
                    return $data['action'] === 'restaurant_deleted';
                })
            );

        $request = Request::create('/api/v1/restaurants/1', 'DELETE');

        ActivityLogger::restaurantDeleted($request);
    }

    public function test_restaurant_activity_log_contains_request_context(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Restaurant created',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'restaurant_created'
                    && $data['user_id'] === 123
                    && $data['ip_address'] === '127.0.0.1'
                    && $data['user_agent'] === 'PHPUnit'
                    && isset($data['timestamp']);
            })
        );

    $user = \Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('getAuthIdentifier')
        ->andReturn(123);

    $this->be($user);

    $request = Request::create('/api/v1/restaurants', 'POST');
    $request->setUserResolver(fn () => $user);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $request->headers->set('User-Agent', 'PHPUnit');

    ActivityLogger::restaurantCreated($request);
}

public function test_menu_item_created_is_logged(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Menu item created',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'menu_item_created';
            })
        );

    $request = Request::create(
        '/api/v1/restaurants/1/menu-items',
        'POST'
    );

    ActivityLogger::menuItemCreated($request);
}

public function test_menu_item_updated_is_logged(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Menu item updated',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'menu_item_updated';
            })
        );

    $request = Request::create(
        '/api/v1/menu-items/1',
        'PUT'
    );

    ActivityLogger::menuItemUpdated($request);
}

public function test_menu_item_deleted_is_logged(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Menu item deleted',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'menu_item_deleted';
            })
        );

    $request = Request::create(
        '/api/v1/menu-items/1',
        'DELETE'
    );

    ActivityLogger::menuItemDeleted($request);
}

public function test_order_created_is_logged(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Order created',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'order_created';
            })
        );

    $request = Request::create(
        '/api/v1/orders',
        'POST'
    );

    ActivityLogger::orderCreated($request);
}

public function test_order_status_updated_is_logged(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Order status updated',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'order_status_updated';
            })
        );

    $request = Request::create(
        '/api/v1/orders/1/status',
        'PUT'
    );

    ActivityLogger::orderStatusUpdated($request);
}

public function test_order_driver_assigned_is_logged(): void
{
    Log::shouldReceive('channel')
        ->once()
        ->with('activity')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with(
            'Driver assigned to order',
            \Mockery::on(function (array $data): bool {
                return $data['action'] === 'order_driver_assigned';
            })
        );

    $request = Request::create(
        '/api/v1/orders/1/assign-driver',
        'PUT'
    );

    ActivityLogger::orderDriverAssigned($request);
}
}