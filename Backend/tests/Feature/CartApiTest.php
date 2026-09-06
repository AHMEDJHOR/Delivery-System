<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/cart';

    private const CART_FIELDS = [
        'id',
        'menu_item_id',
        'quantity',
        'menu_item',
        'restaurant',
        'created_at',
        'updated_at',
    ];

    public function test_customer_can_view_own_cart(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category,
            quantity: 2
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson($this->endpoint);

        $response->assertOk();

        $response->assertJsonFragment([
            'id' => $cartItem->id,
            'menu_item_id' => $cartItem->menu_item_id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_cannot_see_another_customers_cart_items(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $this->createCartItem(
            $otherCustomer,
            $restaurant,
            $category,
            quantity: 2
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson($this->endpoint);

        $response->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_cart_returns_expected_fields(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson($this->endpoint);

        $response->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => self::CART_FIELDS,
            ],
        ]);
    }

    public function test_guest_cannot_view_cart(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertUnauthorized();
    }

    public function test_non_customer_cannot_use_cart(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson($this->endpoint);

        $response->assertForbidden();
    }

    public function test_customer_can_add_available_menu_item_to_cart(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $menuItem = $this->createMenuItem(
            $restaurant,
            $category,
            [
                'name' => 'Special Rice',
            ]
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 2,
                ]
            );

        $response->assertCreated();

        $response->assertJsonPath(
            'message',
            'Item added to cart successfully.'
        );

        $this->assertDatabaseHas('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
        ]);
    }

    public function test_same_menu_item_increases_existing_cart_quantity(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $menuItem = $this->createMenuItem($restaurant, $category);

        $this->createCartItem(
            $customer,
            $restaurant,
            $category,
            menuItem: $menuItem,
            quantity: 2
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 3,
                ]
            );

        $response->assertOk();

        $this->assertDatabaseCount('cart_items', 1);

        $this->assertDatabaseHas('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 5,
        ]);
    }

    public function test_same_menu_item_cannot_exceed_quantity_of_99(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $menuItem = $this->createMenuItem($restaurant, $category);

        $this->createCartItem(
            $customer,
            $restaurant,
            $category,
            menuItem: $menuItem,
            quantity: 98
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 2,
                ]
            );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 98,
        ]);
    }

    public function test_customer_cannot_add_item_from_another_restaurant(): void
    {
        $customer = $this->createCustomer();

        $firstRestaurant = $this->createApprovedRestaurant('Restaurant A');
        $secondRestaurant = $this->createApprovedRestaurant('Restaurant B');

        $firstCategory = $this->createCategory('Category A');
        $secondCategory = $this->createCategory('Category B');

        $firstMenuItem = $this->createMenuItem(
            $firstRestaurant,
            $firstCategory,
            ['name' => 'Meal A']
        );

        $secondMenuItem = $this->createMenuItem(
            $secondRestaurant,
            $secondCategory,
            ['name' => 'Meal B']
        );

        $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $firstMenuItem->id,
                    'quantity' => 1,
                ]
            )
            ->assertCreated();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $secondMenuItem->id,
                    'quantity' => 1,
                ]
            );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $firstMenuItem->id,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $secondMenuItem->id,
        ]);
    }

    public function test_unavailable_menu_item_cannot_be_added_to_cart(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $menuItem = $this->createMenuItem(
            $restaurant,
            $category,
            [
                'is_available' => false,
            ]
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                ]
            );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $menuItem->id,
        ]);
    }

    public function test_item_from_unapproved_restaurant_cannot_be_added_to_cart(): void
    {
        $customer = $this->createCustomer();

        $restaurant = Restaurant::factory()->create([
            'approval_status' => 'pending',
            'status' => 'inactive',
        ]);

        $category = $this->createCategory();

        $menuItem = $this->createMenuItem($restaurant, $category);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                ]
            );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $menuItem->id,
        ]);
    }

    public function test_item_from_inactive_restaurant_cannot_be_added_to_cart(): void
    {
        $customer = $this->createCustomer();

        $restaurant = Restaurant::factory()->create([
            'approval_status' => 'approved',
            'status' => 'inactive',
        ]);

        $category = $this->createCategory();

        $menuItem = $this->createMenuItem($restaurant, $category);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                ]
            );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('cart_items', [
            'customer_id' => $customer->id,
            'menu_item_id' => $menuItem->id,
        ]);
    }

    public function test_cart_creation_requires_menu_item_id(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'quantity' => 1,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'menu_item_id',
        ]);
    }

    public function test_cart_creation_requires_existing_menu_item(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => 999999,
                    'quantity' => 1,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'menu_item_id',
        ]);
    }

    public function test_cart_creation_quantity_must_be_between_1_and_99(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'menu_item_id' => 1,
                    'quantity' => 0,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'menu_item_id',
            'quantity',
        ]);
    }

    public function test_customer_can_update_own_cart_item_quantity(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category,
            quantity: 2
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $cartItem->id,
                [
                    'quantity' => 5,
                ]
            );

        $response->assertOk();

        $response->assertJsonPath(
            'message',
            'Cart item updated successfully.'
        );

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'customer_id' => $customer->id,
            'quantity' => 5,
        ]);
    }

    public function test_customer_cannot_update_another_customers_cart_item(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $otherCustomer,
            $restaurant,
            $category,
            quantity: 2
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $cartItem->id,
                [
                    'quantity' => 10,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'customer_id' => $otherCustomer->id,
            'quantity' => 2,
        ]);
    }

    public function test_nonexistent_cart_item_returns_not_found(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson(
                $this->endpoint . '/999999',
                []
            );

        $response->assertNotFound();
    }

    public function test_cart_item_update_validates_quantity(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $cartItem->id,
                [
                    'quantity' => 100,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'quantity',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 1,
        ]);
    }

    public function test_guest_cannot_update_cart_item(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->putJson(
            $this->endpoint . '/' . $cartItem->id,
            [
                'quantity' => 5,
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_non_customer_cannot_update_cart_item(): void
    {
        $customer = $this->createCustomer();
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->actingAs($manager, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $cartItem->id,
                [
                    'quantity' => 5,
                ]
            );

        $response->assertForbidden();
    }

    public function test_customer_can_delete_own_cart_item(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/' . $cartItem->id
            );

        $response->assertOk();

        $response->assertJsonPath(
            'message',
            'Cart item removed successfully.'
        );

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_customer_cannot_delete_another_customers_cart_item(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $otherCustomer,
            $restaurant,
            $category
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/' . $cartItem->id
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_guest_cannot_delete_cart_item(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->deleteJson(
            $this->endpoint . '/' . $cartItem->id
        );

        $response->assertUnauthorized();
    }

    public function test_non_customer_cannot_delete_cart_item(): void
    {
        $customer = $this->createCustomer();
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $cartItem = $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $response = $this->actingAs($manager, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/' . $cartItem->id
            );

        $response->assertForbidden();
    }

    public function test_customer_can_clear_own_cart(): void
    {
        $customer = $this->createCustomer();
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $this->createCartItem($customer, $restaurant, $category, quantity: 1);

        $secondMenuItem = $this->createMenuItem(
            $restaurant,
            $category,
            ['name' => 'Second Meal']
        );

        $customer->cartItems()->create([
    'menu_item_id' => $secondMenuItem->id,
    'quantity' => 2,
]);

        $response = $this->actingAs($customer, 'sanctum')
            ->deleteJson($this->endpoint);

        $response->assertOk();

        $response->assertJsonPath(
            'message',
            'Cart cleared successfully.'
        );

        $this->assertDatabaseMissing('cart_items', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_clearing_cart_does_not_affect_another_customers_cart(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $this->createCartItem(
            $customer,
            $restaurant,
            $category
        );

        $otherCartItem = $this->createCartItem(
            $otherCustomer,
            $restaurant,
            $category,
            quantity: 3
        );

        $response = $this->actingAs($customer, 'sanctum')
            ->deleteJson($this->endpoint);

        $response->assertOk();

        $this->assertDatabaseMissing('cart_items', [
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $otherCartItem->id,
            'customer_id' => $otherCustomer->id,
            'quantity' => 3,
        ]);
    }

    public function test_guest_cannot_clear_cart(): void
    {
        $response = $this->deleteJson($this->endpoint);

        $response->assertUnauthorized();
    }

    public function test_non_customer_cannot_clear_cart(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->deleteJson($this->endpoint);

        $response->assertForbidden();
    }

    private function createCustomer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
        ]);
    }

    private function createApprovedRestaurant(
        string $name = 'Main Restaurant'
    ): Restaurant {
        return Restaurant::factory()->create([
            'name' => $name,
            'approval_status' => 'approved',
            'status' => 'active',
        ]);
    }

    private function createCategory(
        string $name = 'Main Food'
    ): Category {
        return Category::create([
            'name' => $name,
        ]);
    }

    private function createMenuItem(
        Restaurant $restaurant,
        Category $category,
        array $attributes = []
    ): MenuItem {
        return $restaurant->menuItems()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Special Rice',
            'description' => 'Delicious rice',
            'price' => 150.00,
            'is_available' => true,
            'image' => null,
        ], $attributes));
    }

    private function createCartItem(
    User $customer,
    Restaurant $restaurant,
    Category $category,
    ?MenuItem $menuItem = null,
    int $quantity = 1
): CartItem {
    $menuItem ??= $this->createMenuItem(
        $restaurant,
        $category
    );

    return $customer->cartItems()->create([
        'menu_item_id' => $menuItem->id,
        'quantity' => $quantity,
    ]);
}
}
