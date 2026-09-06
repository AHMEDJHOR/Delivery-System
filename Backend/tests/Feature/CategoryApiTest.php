<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/categories';

    private const CATEGORY_FIELDS = [
        'id',
        'name',
        'description',
        'created_at',
        'updated_at',
    ];

    public function test_public_can_get_categories(): void
    {
        $category = $this->createCategory();

        $response = $this->getJson($this->endpoint);

        $response->assertOk();

        $response->assertJsonFragment([
            'id' => $category->id,
            'name' => $category->name,
        ]);
    }

    public function test_category_list_returns_expected_fields(): void
    {
        $this->createCategory();

        $response = $this->getJson($this->endpoint);

        $response->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => self::CATEGORY_FIELDS,
            ],
        ]);
    }

    public function test_category_list_returns_latest_categories_first(): void
    {
        $olderCategory = $this->createCategory('Older Category');

        $olderCategory->created_at = now()->subDay();
        $olderCategory->saveQuietly();

        $newerCategory = $this->createCategory('Newer Category');

        $response = $this->getJson($this->endpoint);

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame($newerCategory->id, $data[0]['id']);
        $this->assertSame($olderCategory->id, $data[1]['id']);
    }

    public function test_public_can_view_category(): void
    {
        $category = $this->createCategory();

        $response = $this->getJson(
            $this->endpoint . '/' . $category->id
        );

        $response->assertOk();

        $response->assertJsonPath(
            'data.id',
            $category->id
        );

        $response->assertJsonPath(
            'data.name',
            $category->name
        );
    }

    public function test_category_show_returns_expected_fields(): void
    {
        $category = $this->createCategory();

        $response = $this->getJson(
            $this->endpoint . '/' . $category->id
        );

        $response->assertOk();

        $response->assertJsonStructure([
            'data' => self::CATEGORY_FIELDS,
        ]);
    }

    public function test_nonexistent_category_returns_not_found(): void
    {
        $response = $this->getJson(
            $this->endpoint . '/999999'
        );

        $response->assertNotFound();
    }

    public function test_public_can_get_available_menu_items_for_category(): void
    {
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $menuItem = $this->createMenuItem($restaurant, $category, [
            'name' => 'Special Rice',
        ]);

        $response = $this->getJson(
            $this->endpoint . '/' . $category->id . '/menu-items'
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'id' => $menuItem->id,
            'name' => 'Special Rice',
        ]);
    }

    public function test_category_menu_items_excludes_unavailable_items(): void
    {
        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory();

        $this->createMenuItem($restaurant, $category, [
            'name' => 'Available Meal',
            'is_available' => true,
        ]);

        $this->createMenuItem($restaurant, $category, [
            'name' => 'Unavailable Meal',
            'is_available' => false,
        ]);

        $response = $this->getJson(
            $this->endpoint . '/' . $category->id . '/menu-items'
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'name' => 'Available Meal',
        ]);

        $response->assertJsonMissing([
            'name' => 'Unavailable Meal',
        ]);
    }

    public function test_category_menu_items_excludes_items_from_non_public_restaurants(): void
    {
        $publicRestaurant = $this->createApprovedRestaurant();

        $nonPublicRestaurant = Restaurant::factory()->create([
            'approval_status' => 'pending',
            'status' => 'inactive',
        ]);

        $category = $this->createCategory();

        $this->createMenuItem($publicRestaurant, $category, [
            'name' => 'Public Meal',
        ]);

        $this->createMenuItem($nonPublicRestaurant, $category, [
            'name' => 'Private Meal',
        ]);

        $response = $this->getJson(
            $this->endpoint . '/' . $category->id . '/menu-items'
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'name' => 'Public Meal',
        ]);

        $response->assertJsonMissing([
            'name' => 'Private Meal',
        ]);
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'name' => 'Beverages',
                    'description' => 'Drinks and beverages',
                ]
            );

        $response->assertCreated();

        $response->assertJsonStructure([
            'data' => self::CATEGORY_FIELDS,
        ]);

        $response->assertJsonPath(
            'message',
            'Category created successfully.'
        );

        $this->assertDatabaseHas('categories', [
            'name' => 'Beverages',
            'description' => 'Drinks and beverages',
        ]);
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'name' => 'Unauthorized Category',
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('categories', [
            'name' => 'Unauthorized Category',
        ]);
    }

    public function test_guest_cannot_create_category(): void
    {
        $response = $this->postJson(
            $this->endpoint,
            [
                'name' => 'Unauthorized Category',
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_category_creation_validates_input(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'name' => '',
                    'description' => 123,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
            'description',
        ]);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_category_name_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->createCategory('Main Food');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson(
                $this->endpoint,
                [
                    'name' => 'Main Food',
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = $this->createCategory('Old Name');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $category->id,
                [
                    'name' => 'Updated Name',
                    'description' => 'Updated description',
                ]
            );

        $response->assertOk();

        $response->assertJsonStructure([
            'data' => self::CATEGORY_FIELDS,
        ]);

        $response->assertJsonPath(
            'message',
            'Category updated successfully.'
        );

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);
    }

    public function test_non_admin_cannot_update_category(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $category = $this->createCategory('Original Category');

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $category->id,
                [
                    'name' => 'Unauthorized Update',
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Original Category',
        ]);
    }

    public function test_guest_cannot_update_category(): void
    {
        $category = $this->createCategory();

        $response = $this->putJson(
            $this->endpoint . '/' . $category->id,
            [
                'name' => 'Unauthorized Update',
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_category_can_be_partially_updated(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = $this->createCategory(
            'Original Category',
            'Original description'
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $category->id,
                [
                    'description' => 'Updated description',
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Original Category',
            'description' => 'Updated description',
        ]);
    }

    public function test_category_update_validates_input(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = $this->createCategory('Original Category');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $category->id,
                [
                    'name' => '',
                    'description' => 123,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
            'description',
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Original Category',
        ]);
    }

    public function test_category_name_can_remain_the_same_during_update(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = $this->createCategory('Main Food');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                $this->endpoint . '/' . $category->id,
                [
                    'name' => 'Main Food',
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Main Food',
        ]);
    }

    public function test_admin_can_delete_category_without_menu_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = $this->createCategory('Category To Delete');

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/' . $category->id
            );

        $response->assertOk();

        $response->assertJsonPath(
            'message',
            'Category deleted successfully.'
        );

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_non_admin_cannot_delete_category(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $category = $this->createCategory('Protected Category');

        $response = $this->actingAs($customer, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/' . $category->id
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_guest_cannot_delete_category(): void
    {
        $category = $this->createCategory('Protected Category');

        $response = $this->deleteJson(
            $this->endpoint . '/' . $category->id
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_with_menu_items_cannot_be_deleted(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $restaurant = $this->createApprovedRestaurant();
        $category = $this->createCategory('Used Category');

        $this->createMenuItem($restaurant, $category);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/' . $category->id
            );

        $response->assertStatus(409);

$response->assertJsonPath(
    'message',
    'Cannot delete category while it has menu items.'
);

$this->assertDatabaseHas('categories', [
    'id' => $category->id,
]);
    }

    public function test_nonexistent_category_cannot_be_deleted(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson(
                $this->endpoint . '/999999'
            );

        $response->assertNotFound();
    }

    private function createApprovedRestaurant(): Restaurant
    {
        return Restaurant::factory()->create([
            'approval_status' => 'approved',
            'status' => 'active',
        ]);
    }

    private function createCategory(
        string $name = 'Main Food',
        ?string $description = null
    ): Category {
        return Category::create([
            'name' => $name,
            'description' => $description,
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
}
