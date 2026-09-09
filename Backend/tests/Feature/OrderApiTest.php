<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\DriverProfile;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Jobs\CalculateDeliveryFee;
use App\Services\DeliveryDistanceService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_see_their_own_orders(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customerOrder = $this->createOrder(
            $customer,
            $restaurant
        );

        $otherOrder = $this->createOrder(
            $otherCustomer,
            $restaurant
        );

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/orders');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $customerOrder->id,
            ])
            ->assertJsonMissing([
                'id' => $otherOrder->id,
            ]);
    }

    public function test_restaurant_manager_can_only_see_orders_from_their_restaurant(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $otherManager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $otherRestaurant = Restaurant::factory()->create([
            'manager_id' => $otherManager->id,
        ]);

        $customerOne = User::factory()->create([
            'role' => 'customer',
        ]);

        $customerTwo = User::factory()->create([
            'role' => 'customer',
        ]);

        $accessibleOrder = $this->createOrder(
            $customerOne,
            $restaurant
        );

        $inaccessibleOrder = $this->createOrder(
            $customerTwo,
            $otherRestaurant
        );

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/orders');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $accessibleOrder->id,
            ])
            ->assertJsonMissing([
                'id' => $inaccessibleOrder->id,
            ]);
    }

    public function test_driver_can_only_see_orders_assigned_to_them(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
        ]);

        $otherDriver = User::factory()->create([
            'role' => 'driver',
        ]);

        $driverProfile = $this->createDriverProfile(
            $driver,
            'LIC-001'
        );

        $otherDriverProfile = $this->createDriverProfile(
            $otherDriver,
            'LIC-002'
        );

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customerOne = User::factory()->create([
            'role' => 'customer',
        ]);

        $customerTwo = User::factory()->create([
            'role' => 'customer',
        ]);

        $accessibleOrder = $this->createOrder(
            $customerOne,
            $restaurant,
            $driverProfile->id,
            'ready_for_pickup'
        );

        $inaccessibleOrder = $this->createOrder(
            $customerTwo,
            $restaurant,
            $otherDriverProfile->id,
            'ready_for_pickup'
        );

        Sanctum::actingAs($driver);

        $response = $this->getJson('/api/v1/orders');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $accessibleOrder->id,
            ])
            ->assertJsonMissing([
                'id' => $inaccessibleOrder->id,
            ]);
    }

    public function test_driver_without_driver_profile_sees_no_orders(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $this->createOrder(
            $customer,
            $restaurant
        );

        Sanctum::actingAs($driver);

        $response = $this->getJson('/api/v1/orders');

        $response
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_admin_can_see_all_orders(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customerOne = User::factory()->create([
            'role' => 'customer',
        ]);

        $customerTwo = User::factory()->create([
            'role' => 'customer',
        ]);

        $orderOne = $this->createOrder(
            $customerOne,
            $restaurant
        );

        $orderTwo = $this->createOrder(
            $customerTwo,
            $restaurant
        );

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/orders');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $orderOne->id,
            ])
            ->assertJsonFragment([
                'id' => $orderTwo->id,
            ]);
    }

    public function test_customer_can_view_their_own_order(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $order = $this->createOrder(
            $customer,
            $restaurant
        );

        Sanctum::actingAs($customer);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath(
                'data.customer.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.restaurant.id',
                $restaurant->id
            );
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $order = $this->createOrder(
            $otherCustomer,
            $restaurant
        );

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_restaurant_manager_can_view_order_from_their_restaurant(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $order = $this->createOrder(
            $customer,
            $restaurant
        );

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_restaurant_manager_cannot_view_order_from_another_restaurant(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $otherManager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $otherRestaurant = Restaurant::factory()->create([
            'manager_id' => $otherManager->id,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $order = $this->createOrder(
            $customer,
            $otherRestaurant
        );

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_driver_can_view_order_assigned_to_them(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
        ]);

        $driverProfile = $this->createDriverProfile(
            $driver,
            'LIC-003'
        );

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $order = $this->createOrder(
            $customer,
            $restaurant,
            $driverProfile->id,
            'ready_for_pickup'
        );

        Sanctum::actingAs($driver);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath(
                'data.driver.id',
                $driverProfile->id
            );
    }

    public function test_driver_cannot_view_order_assigned_to_another_driver(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
        ]);

        $otherDriver = User::factory()->create([
            'role' => 'driver',
        ]);

        $otherDriverProfile = $this->createDriverProfile(
            $otherDriver,
            'LIC-004'
        );

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $order = $this->createOrder(
            $customer,
            $restaurant,
            $otherDriverProfile->id,
            'ready_for_pickup'
        );

        Sanctum::actingAs($driver);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_any_order(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $order = $this->createOrder(
            $customer,
            $restaurant
        );

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

public function test_customer_can_create_order_from_their_cart(): void
{
    Queue::fake();

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'active',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'Burger',
        100.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        2
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.status',
            'pending'
        )
        ->assertJsonPath(
            'data.delivery_status',
            'pending'
        )
        ->assertJsonPath(
            'data.subtotal',
            '200.00'
        )
        ->assertJsonPath(
            'data.delivery_fee',
            '0.00'
        )
        ->assertJsonPath(
            'data.total_amount',
            '200.00'
        );

    $order = Order::query()
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->firstOrFail();

    Queue::assertPushed(
        CalculateDeliveryFee::class,
        function (CalculateDeliveryFee $job) use ($order): bool {
            return $job->orderId === $order->id;
        }
    );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'customer_id' => $customer->id,
        'restaurant_id' => $restaurant->id,
        'subtotal' => '200.00',
        'delivery_fee' => '0.00',
        'total_amount' => '200.00',
        'delivery_status' => 'pending',
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('order_items', [
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Burger',
        'quantity' => 2,
        'unit_price' => '100.00',
        'subtotal' => '200.00',
    ]);

    $this->assertDatabaseCount('cart_items', 0);
}

    public function test_order_items_store_price_and_name_snapshot(): void
    {
        Http::fake([
            'https://router.project-osrm.org/*' => Http::response([
                'routes' => [
                    [
                        'distance' => 2000,
                    ],
                ],
            ], 200),
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = Restaurant::factory()->create([
            'manager_id' => $manager->id,
            'approval_status' => 'approved',
            'status' => 'active',
            'latitude' => 8.5400000,
            'longitude' => 39.2700000,
        ]);

        $category = $this->createCategory();

        $menuItem = $this->createMenuItem(
    $restaurant,
    $category,
    'Pizza',
    150.00
);
        $this->createCartItem(
            $customer,
            $menuItem,
            2
        );

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders', [
            'delivery_address' => 'Adama',
            'delivery_latitude' => 8.5500000,
            'delivery_longitude' => 39.2600000,
            'phone' => '0912345678',
        ])->assertCreated();

        $orderItem = OrderItem::query()->firstOrFail();

        $this->assertSame(
            'Pizza',
            $orderItem->item_name
        );

        $this->assertSame(
            '150.00',
            $orderItem->unit_price
        );

        $this->assertSame(
            2,
            $orderItem->quantity
        );

        $this->assertSame(
            '300.00',
            $orderItem->subtotal
        );
    }

    public function test_customer_cannot_create_order_with_an_empty_cart(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_address' => 'Adama',
            'delivery_latitude' => 8.5500000,
            'delivery_longitude' => 39.2600000,
            'phone' => '0912345678',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'message',
                'Your cart is empty.'
            );
    }

public function test_delivery_fee_and_total_are_calculated_by_the_backend(): void
{
    Queue::fake();

    Http::fake([
        'https://router.project-osrm.org/*' => Http::response([
            'routes' => [
                [
                    'distance' => 7500,
                ],
            ],
        ], 200),
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'active',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'Pasta',
        80.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        3
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,

        // These values must be ignored.
        'delivery_fee' => 9999.99,
        'total_amount' => 99999.99,

        'phone' => '0912345678',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.subtotal',
            '240.00'
        )
        ->assertJsonPath(
            'data.delivery_fee',
            '0.00'
        )
        ->assertJsonPath(
            'data.total_amount',
            '240.00'
        )
        ->assertJsonPath(
            'data.delivery_status',
            'pending'
        );

    $order = Order::query()
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->firstOrFail();

    Queue::assertPushed(
        CalculateDeliveryFee::class,
        function (CalculateDeliveryFee $job) use ($order): bool {
            return $job->orderId === $order->id;
        }
    );

    $job = new CalculateDeliveryFee($order->id);

    $job->handle(
        app(DeliveryDistanceService::class)
    );

    $order->refresh();

    $this->assertSame(
        '170.00',
        $order->delivery_fee
    );

    $this->assertSame(
        '410.00',
        $order->total_amount
    );

    $this->assertSame(
        'calculated',
        $order->delivery_status
    );
}

public function test_delivery_fee_cannot_exceed_the_configured_maximum(): void
{
    Queue::fake();

    Http::fake([
        'https://router.project-osrm.org/*' => Http::response([
            'routes' => [
                [
                    'distance' => 50010,
                ],
            ],
        ], 200),
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'active',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'Delivery Fee Cap Item',
        100.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        1
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Far Location',
        'delivery_latitude' => 9.0000000,
        'delivery_longitude' => 40.0000000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.delivery_status',
            'pending'
        )
        ->assertJsonPath(
            'data.delivery_fee',
            '0.00'
        )
        ->assertJsonPath(
            'data.total_amount',
            '100.00'
        );

    $order = Order::query()
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->firstOrFail();

    Queue::assertPushed(
        CalculateDeliveryFee::class,
        function (CalculateDeliveryFee $job) use ($order): bool {
            return $job->orderId === $order->id;
        }
    );

    $job = new CalculateDeliveryFee($order->id);

    try {
        $job->handle(
            app(DeliveryDistanceService::class)
        );

        $this->fail(
            'Expected the delivery fee calculation to exceed the maximum.'
        );
    } catch (\DomainException $e) {
        $this->assertSame(
            'The calculated delivery fee exceeds the supported limit.',
            $e->getMessage()
        );

        $job->failed($e);
    }

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'delivery_status' => 'failed',
        'delivery_fee' => '0.00',
        'total_amount' => '100.00',
    ]);
}

public function test_unavailable_menu_item_is_rejected(): void
{
    Http::fake([
        'https://router.project-osrm.org/*' => Http::response([
            'routes' => [
                [
                    'distance' => 5000,
                ],
            ],
        ], 200),
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'active',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = new MenuItem();
    $menuItem->restaurant_id = $restaurant->id;
    $menuItem->category_id = $category->id;
    $menuItem->name = 'Unavailable Pizza';
    $menuItem->description = 'Unavailable test item';
    $menuItem->price = 100.00;
    $menuItem->is_available = false;
    $menuItem->save();

    $this->createCartItem(
        $customer,
        $menuItem,
        1
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath(
            'message',
            "Menu item 'Unavailable Pizza' is no longer available."
        );

    $this->assertDatabaseCount('orders', 0);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $customer->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 1,
    ]);
}

public function test_unapproved_restaurant_is_rejected(): void
{
    Http::fake();

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'active',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'Pending Restaurant Item',
        100.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        1
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath(
            'message',
            'The restaurant is not currently available.'
        );

    Http::assertNothingSent();

    $this->assertDatabaseCount('orders', 0);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $customer->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 1,
    ]);
}

public function test_inactive_restaurant_is_rejected(): void
{
    Http::fake();

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'inactive',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'Inactive Restaurant Item',
        100.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        1
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath(
            'message',
            'The restaurant is not currently available.'
        );

    Http::assertNothingSent();

    $this->assertDatabaseCount('orders', 0);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $customer->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 1,
    ]);
}

public function test_restaurant_without_coordinates_is_rejected(): void
{
    Http::fake();

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'active',
        'latitude' => null,
        'longitude' => null,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'No Location Item',
        100.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        1
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath(
            'message',
            'Restaurant location is not available for delivery calculation.'
        );

    Http::assertNothingSent();

    $this->assertDatabaseCount('orders', 0);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $customer->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 1,
    ]);
}


public function test_routing_service_failure_is_handled(): void
{
    Queue::fake();

    Http::fake([
        'https://router.project-osrm.org/*' => Http::response(
            [],
            500
        ),
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'approved',
        'status' => 'active',
        'latitude' => 8.5400000,
        'longitude' => 39.2700000,
    ]);

    $category = $this->createCategory();

    $menuItem = $this->createMenuItem(
        $restaurant,
        $category,
        'Routing Failure Item',
        100.00
    );

    $this->createCartItem(
        $customer,
        $menuItem,
        1
    );

    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/orders', [
        'delivery_address' => 'Adama',
        'delivery_latitude' => 8.5500000,
        'delivery_longitude' => 39.2600000,
        'phone' => '0912345678',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.status',
            'pending'
        )
        ->assertJsonPath(
            'data.delivery_status',
            'pending'
        )
        ->assertJsonPath(
            'data.subtotal',
            '100.00'
        )
        ->assertJsonPath(
            'data.delivery_fee',
            '0.00'
        )
        ->assertJsonPath(
            'data.total_amount',
            '100.00'
        );

    $order = Order::query()
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->firstOrFail();

    Queue::assertPushed(
        CalculateDeliveryFee::class,
        function (CalculateDeliveryFee $job) use ($order): bool {
            return $job->orderId === $order->id;
        }
    );

    $job = new CalculateDeliveryFee($order->id);

    try {
        $job->handle(
            app(DeliveryDistanceService::class)
        );

        $this->fail(
            'Expected delivery distance calculation to fail.'
        );
    } catch (\Exception $e) {
        $this->assertSame(
            'Unable to calculate delivery distance.',
            $e->getMessage()
        );

        $job->failed($e);
    }

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'delivery_status' => 'failed',
        'delivery_fee' => '0.00',
        'total_amount' => '100.00',
    ]);

    $this->assertDatabaseMissing('cart_items', [
        'customer_id' => $customer->id,
        'menu_item_id' => $menuItem->id,
    ]);
}

public function test_restaurant_manager_can_move_order_from_pending_to_preparing(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'pending'
    );

    Sanctum::actingAs($manager);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'preparing',
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'preparing'
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'preparing',
    ]);
}

public function test_restaurant_manager_can_reject_pending_order(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'pending'
    );

    Sanctum::actingAs($manager);

    $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'rejected',
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'rejected'
        );
}

public function test_restaurant_manager_can_move_preparing_order_to_ready_for_pickup(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'preparing'
    );

    Sanctum::actingAs($manager);

    $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'ready_for_pickup',
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'ready_for_pickup'
        );
}

public function test_driver_can_move_ready_order_to_in_transit(): void
{
    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-005'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        $driverProfile->id,
        'ready_for_pickup'
    );

    Sanctum::actingAs($driver);

    $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'in_transit',
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'in_transit'
        );
}

public function test_driver_can_move_in_transit_order_to_delivered(): void
{
    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-006'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        $driverProfile->id,
        'in_transit'
    );

    Sanctum::actingAs($driver);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'delivered',
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'delivered'
        );

    $this->assertNotNull(
        Order::find($order->id)->delivered_at
    );
}

public function test_driver_cannot_move_ready_order_to_delivered_directly(): void
{
    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-007'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        $driverProfile->id,
        'ready_for_pickup'
    );

    Sanctum::actingAs($driver);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'delivered',
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'ready_for_pickup',
    ]);
}

public function test_driver_cannot_mark_unassigned_order_in_transit(): void
{
    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $this->createDriverProfile(
        $driver,
        'LIC-008'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($driver);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'in_transit',
        ]
    );

    $response->assertForbidden();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'ready_for_pickup',
        'driver_id' => null,
    ]);
}

public function test_restaurant_manager_cannot_skip_from_pending_to_delivered(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'pending'
    );

    Sanctum::actingAs($manager);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/status",
        [
            'status' => 'delivered',
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
    ]);
}

public function test_admin_can_assign_approved_online_driver_to_ready_order(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-009'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($admin);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $order->id
        )
        ->assertJsonPath(
            'data.driver.id',
            $driverProfile->id
        );

    $updatedOrder = Order::findOrFail($order->id);

    $this->assertSame(
        $driverProfile->id,
        $updatedOrder->driver_id
    );

    $this->assertNotNull(
        $updatedOrder->assigned_at
    );
}

public function test_admin_cannot_reassign_an_already_assigned_order(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $firstDriver = User::factory()->create([
        'role' => 'driver',
    ]);

    $firstDriverProfile = $this->createDriverProfile(
        $firstDriver,
        'LIC-011'
    );

    $secondDriver = User::factory()->create([
        'role' => 'driver',
    ]);

    $secondDriverProfile = $this->createDriverProfile(
        $secondDriver,
        'LIC-012'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        $firstDriverProfile->id,
        'ready_for_pickup'
    );

    Sanctum::actingAs($admin);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $secondDriverProfile->id,
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'A driver is already assigned to this order.'
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'driver_id' => $firstDriverProfile->id,
    ]);
}

public function test_non_admin_cannot_assign_driver(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-010'
    );

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($manager);

    $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    )->assertForbidden();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'driver_id' => null,
    ]);
}

public function test_unapproved_driver_cannot_be_assigned(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-011'
    );

    $driverProfile->approval_status = 'pending';
    $driverProfile->save();

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($admin);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'success',
            false
        )
        ->assertJsonPath(
            'message',
            'The driver is not approved.'
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'driver_id' => null,
    ]);
}

public function test_offline_driver_cannot_be_assigned(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-012'
    );

    $driverProfile->is_online = false;
    $driverProfile->save();

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($admin);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'success',
            false
        )
        ->assertJsonPath(
            'message',
            'The driver is currently offline.'
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'driver_id' => null,
    ]);
}

public function test_driver_can_only_be_assigned_to_ready_for_pickup_order(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-013'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'preparing'
    );

    Sanctum::actingAs($admin);

    $response = $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'success',
            false
        )
        ->assertJsonPath(
            'message',
            'Only orders ready for pickup can be assigned to a driver.'
        );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'preparing',
        'driver_id' => null,
    ]);
}

public function test_driver_with_active_delivery_cannot_be_assigned_another_order(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-014'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customerOne = User::factory()->create([
        'role' => 'customer',
    ]);

    $customerTwo = User::factory()->create([
        'role' => 'customer',
    ]);

    $activeOrder = $this->createOrder(
        $customerOne,
        $restaurant,
        $driverProfile->id,
        'in_transit'
    );

    $orderToAssign = $this->createOrder(
        $customerTwo,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($admin);

    $response = $this->putJson(
        "/api/v1/orders/{$orderToAssign->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath(
            'success',
            false
        )
        ->assertJsonPath(
            'message',
            'The driver already has an active delivery.'
        );

    $this->assertDatabaseHas('orders', [
        'id' => $activeOrder->id,
        'driver_id' => $driverProfile->id,
        'status' => 'in_transit',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $orderToAssign->id,
        'driver_id' => null,
    ]);
}

public function test_assigning_driver_does_not_change_order_status(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $driver = User::factory()->create([
        'role' => 'driver',
    ]);

    $driverProfile = $this->createDriverProfile(
        $driver,
        'LIC-015'
    );

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $order = $this->createOrder(
        $customer,
        $restaurant,
        null,
        'ready_for_pickup'
    );

    Sanctum::actingAs($admin);

    $this->putJson(
        "/api/v1/orders/{$order->id}/assign-driver",
        [
            'driver_id' => $driverProfile->id,
        ]
    )->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'driver_id' => $driverProfile->id,
        'status' => 'ready_for_pickup',
    ]);
}


public function test_orders_are_paginated(): void
{
    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    for ($i = 0; $i < 16; $i++) {
        $this->createOrder(
            $customer,
            $restaurant
        );
    }

    Sanctum::actingAs($customer);

    $response = $this->getJson('/api/v1/orders');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);

    $this->assertCount(
        15,
        $response->json('data')
    );

    $this->assertSame(
        16,
        $response->json('meta.total')
    );

    $this->assertSame(
        15,
        $response->json('meta.per_page')
    );

    $this->assertSame(
        1,
        $response->json('meta.current_page')
    );

    $this->assertSame(
        2,
        $response->json('meta.last_page')
    );
}

    private function createOrder(
        User $customer,
        Restaurant $restaurant,
        ?int $driverId = null,
        string $status = 'pending'
    ): Order {
        $order = new Order();

        $order->customer_id = $customer->id;
        $order->restaurant_id = $restaurant->id;
        $order->driver_id = $driverId;
        $order->subtotal = 100.00;
        $order->delivery_fee = 40.00;
        $order->total_amount = 140.00;
        $order->delivery_address = 'Adama';
        $order->delivery_latitude = 8.54;
        $order->delivery_longitude = 39.27;
        $order->phone = '0912345678';
        $order->status = $status;

        $order->save();

        return $order;
    }

    private function createDriverProfile(
        User $user,
        string $licenseNumber
    ): DriverProfile {
        $driverProfile = new DriverProfile();

        $driverProfile->user_id = $user->id;
        $driverProfile->vehicle_type = 'motorcycle';
        $driverProfile->license_number = $licenseNumber;
        $driverProfile->is_online = true;
        $driverProfile->approval_status = 'approved';

        $driverProfile->save();

        return $driverProfile;
    }

    private function createCartItem(
        User $customer,
        MenuItem $menuItem,
        int $quantity
    ): CartItem {
        $cartItem = new CartItem();

        $cartItem->customer_id = $customer->id;
        $cartItem->menu_item_id = $menuItem->id;
        $cartItem->quantity = $quantity;

        $cartItem->save();

        return $cartItem;
    }

private function createCategory(
    string $name = 'Food'
): Category {
    $category = new Category();

    $category->name = $name;
    $category->description = 'Test category';

    $category->save();

    return $category;
}

private function createMenuItem(
    Restaurant $restaurant,
    Category $category,
    string $name,
    float $price
): MenuItem {
    $menuItem = new MenuItem();

    $menuItem->restaurant_id = $restaurant->id;
    $menuItem->category_id = $category->id;
    $menuItem->name = $name;
    $menuItem->description = 'Test menu item';
    $menuItem->price = $price;
    $menuItem->is_available = true;

    $menuItem->save();

    return $menuItem;
}
}
