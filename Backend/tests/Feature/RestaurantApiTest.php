<?php

namespace Tests\Feature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantApiTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/restaurants';

    public function test_restaurant_manager_can_create_restaurant(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson($this->endpoint, [
                'name' => 'Ahmed Restaurant',
                'description' => 'A test restaurant',
                'address' => 'Adama, Ethiopia',
                'latitude' => 8.54,
                'longitude' => 39.27,
                'phone' => '0912345678',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Ahmed Restaurant',
            'manager_id' => $manager->id,
            'approval_status' => 'pending',
            'status' => 'inactive',
        ]);
    }

    public function test_manager_can_create_multiple_restaurants(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson($this->endpoint, [
                'name' => 'First Restaurant',
                'address' => 'Adama, Ethiopia',
                'phone' => '0912345678',
            ])
            ->assertCreated();

        $this->actingAs($manager, 'sanctum')
            ->postJson($this->endpoint, [
                'name' => 'Second Restaurant',
                'address' => 'Addis Ababa, Ethiopia',
                'phone' => '0912345679',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('restaurants', 2);

        $this->assertDatabaseHas('restaurants', [
            'name' => 'First Restaurant',
            'manager_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Second Restaurant',
            'manager_id' => $manager->id,
        ]);
    }

    public function test_customer_cannot_create_restaurant(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson($this->endpoint, [
                'name' => 'Unauthorized Restaurant',
                'address' => 'Adama, Ethiopia',
                'phone' => '0912345678',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('restaurants', [
            'name' => 'Unauthorized Restaurant',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_restaurant(): void
    {
        $response = $this->postJson($this->endpoint, [
            'name' => 'Unauthorized Restaurant',
            'address' => 'Adama, Ethiopia',
            'phone' => '0912345678',
        ]);

        $response->assertUnauthorized();
    }

    public function test_restaurant_creation_validates_input(): void
    {
        $manager = User::factory()->create([
            'role' => 'restaurant_manager',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson($this->endpoint, [
                'name' => '',
                'description' => str_repeat('a', 2001),
                'address' => str_repeat('a', 501),
                'phone' => '123456789',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
            'description',
            'address',
            'phone',
        ]);

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_public_can_get_approved_active_restaurants(): void
{
    Restaurant::factory()->create([
        'name' => 'Approved Restaurant',
        'approval_status' => 'approved',
        'status' => 'active',
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();

    $response->assertJsonFragment([
        'name' => 'Approved Restaurant',
    ]);
}

public function test_restaurant_list_excludes_pending_restaurants(): void
{
    Restaurant::factory()->create([
        'name' => 'Pending Restaurant',
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();

    $response->assertJsonMissing([
        'name' => 'Pending Restaurant',
    ]);
}

public function test_restaurant_list_excludes_rejected_restaurants(): void
{
    Restaurant::factory()->create([
        'name' => 'Rejected Restaurant',
        'approval_status' => 'rejected',
        'status' => 'inactive',
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();

    $response->assertJsonMissing([
        'name' => 'Rejected Restaurant',
    ]);
}

public function test_restaurant_list_excludes_inactive_restaurants(): void
{
    Restaurant::factory()->create([
        'name' => 'Inactive Restaurant',
        'approval_status' => 'approved',
        'status' => 'inactive',
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();

    $response->assertJsonMissing([
        'name' => 'Inactive Restaurant',
    ]);
}

public function test_restaurant_list_is_public(): void
{
    Restaurant::factory()->create([
        'name' => 'Public Restaurant',
        'approval_status' => 'approved',
        'status' => 'active',
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();
}

public function test_restaurant_list_returns_expected_fields(): void
{
    Restaurant::factory()->create([
        'name' => 'Ahmed Restaurant',
        'description' => 'Test description',
        'address' => 'Adama, Ethiopia',
        'latitude' => 8.54,
        'longitude' => 39.27,
        'phone' => '0912345678',
        'approval_status' => 'approved',
        'status' => 'active',
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'description',
                'address',
                'latitude',
                'longitude',
                'phone',
                'logo',
                'approval_status',
                'status',
                'created_at',
                'updated_at',
            ],
        ],
    ]);
}

public function test_restaurant_list_returns_latest_restaurants_first(): void
{
    $oldRestaurant = Restaurant::factory()->create([
        'name' => 'Old Restaurant',
        'approval_status' => 'approved',
        'status' => 'active',
        'created_at' => now()->subDay(),
    ]);

    $newRestaurant = Restaurant::factory()->create([
        'name' => 'New Restaurant',
        'approval_status' => 'approved',
        'status' => 'active',
        'created_at' => now(),
    ]);

    $response = $this->getJson($this->endpoint);

    $response->assertOk();

    $data = $response->json('data');

    $this->assertSame($newRestaurant->id, $data[0]['id']);
    $this->assertSame($oldRestaurant->id, $data[1]['id']);
}

public function test_public_can_view_approved_active_restaurant(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'approved',
        'status' => 'active',
        'name' => 'Ahmed Restaurant',
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertOk();

    $response->assertJsonPath(
        'data.id',
        $restaurant->id
    );

    $response->assertJsonPath(
        'data.name',
        'Ahmed Restaurant'
    );
}

public function test_pending_restaurant_is_not_visible(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertNotFound();

    $response->assertJsonPath(
        'message',
        'Restaurant not found.'
    );
}

public function test_rejected_restaurant_is_not_visible(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'rejected',
        'status' => 'inactive',
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertNotFound();

    $response->assertJsonPath(
        'message',
        'Restaurant not found.'
    );
}

public function test_inactive_approved_restaurant_is_not_visible(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'approved',
        'status' => 'inactive',
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertNotFound();

    $response->assertJsonPath(
        'message',
        'Restaurant not found.'
    );
}

public function test_suspended_approved_restaurant_is_not_visible(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'approved',
        'status' => 'suspended',
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertNotFound();

    $response->assertJsonPath(
        'message',
        'Restaurant not found.'
    );
}

public function test_nonexistent_restaurant_returns_not_found(): void
{
    $response = $this->getJson(
        $this->endpoint . '/999999'
    );

    $response->assertNotFound();
}

public function test_unauthenticated_user_can_view_approved_active_restaurant(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'approved',
        'status' => 'active',
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertOk();
}

public function test_restaurant_show_returns_expected_fields(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'approved',
        'status' => 'active',
        'name' => 'Ahmed Restaurant',
        'description' => 'A test restaurant',
        'address' => 'Adama, Ethiopia',
        'latitude' => 8.54,
        'longitude' => 39.27,
        'phone' => '0912345678',
        'logo' => null,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'description',
            'address',
            'address',
            'latitude',
            'longitude',
            'phone',
            'logo',
            'approval_status',
            'status',
            'created_at',
            'updated_at',
        ],
    ]);
}

public function test_manager_can_view_own_restaurants(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    Restaurant::factory()->count(3)->create([
        'manager_id' => $manager->id,
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/my-restaurants');

    $response->assertOk();

    $this->assertCount(
        3,
        $response->json('data')
    );
}

public function test_manager_cannot_view_other_managers_restaurants(): void
{
    $managerOne = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $managerTwo = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    Restaurant::factory()->count(2)->create([
        'manager_id' => $managerOne->id,
    ]);

    Restaurant::factory()->count(1)->create([
        'manager_id' => $managerTwo->id,
    ]);

    $response = $this->actingAs($managerOne, 'sanctum')
        ->getJson('/api/v1/my-restaurants');

    $response->assertOk();

    $this->assertCount(
        2,
        $response->json('data')
    );
}

public function test_manager_with_no_restaurants_gets_empty_collection(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/my-restaurants');

    $response->assertOk();

    $this->assertCount(
        0,
        $response->json('data')
    );
}

public function test_guest_cannot_view_my_restaurants(): void
{
    $response = $this->getJson('/api/v1/my-restaurants');

    $response->assertUnauthorized();
}

public function test_customer_cannot_view_my_restaurants(): void
{
    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $response = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/my-restaurants');

    $response->assertForbidden();
}

public function test_my_restaurants_are_returned_latest_first(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $older = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'name' => 'Older Restaurant',
    ]);

    sleep(1);

    $newer = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'name' => 'Newer Restaurant',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/my-restaurants');

    $response->assertOk();

    $restaurants = $response->json('data');

    $this->assertEquals(
        $newer->id,
        $restaurants[0]['id']
    );

    $this->assertEquals(
        $older->id,
        $restaurants[1]['id']
    );
}

public function test_my_restaurants_returns_expected_fields(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/my-restaurants');

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'description',
                'address',
                'address',
                'latitude',
                'longitude',
                'phone',
                'logo',
                'approval_status',
                'status',
                'created_at',
                'updated_at',
            ],
        ],
    ]);
}

public function test_owner_manager_can_update_restaurant(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'name' => 'Old Restaurant',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->putJson("/api/v1/restaurants/{$restaurant->id}", [
            'name' => 'Updated Restaurant',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'name' => 'Updated Restaurant',
    ]);
}

public function test_other_manager_cannot_update_restaurant(): void
{
    $owner = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $otherManager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $owner->id,
    ]);

    $response = $this->actingAs($otherManager, 'sanctum')
        ->putJson("/api/v1/restaurants/{$restaurant->id}", [
            'name' => 'Hacked Name',
        ]);

    $response->assertForbidden();
}

public function test_customer_cannot_update_restaurant(): void
{
    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $restaurant = Restaurant::factory()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->putJson("/api/v1/restaurants/{$restaurant->id}", [
            'name' => 'Updated Name',
        ]);

    $response->assertForbidden();
}

public function test_guest_cannot_update_restaurant(): void
{
    $restaurant = Restaurant::factory()->create();

    $response = $this->putJson(
        "/api/v1/restaurants/{$restaurant->id}",
        [
            'name' => 'Updated Name',
        ]
    );

    $response->assertUnauthorized();
}

public function test_restaurant_update_validates_input(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->putJson("/api/v1/restaurants/{$restaurant->id}", [
            'name' => '',
            'description' => str_repeat('a', 2001),
            'address' => str_repeat('a', 501),
            'phone' => '123',
        ]);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'name',
        'description',
        'address',
        'phone',
    ]);
}

public function test_manager_can_update_multiple_fields(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->putJson("/api/v1/restaurants/{$restaurant->id}", [
            'name' => 'Updated Restaurant',
            'description' => 'Updated Description',
            'address' => 'Updated Address',
            'latitude' => 6.54,
            'longitude' => 49.27,
            'phone' => '0911111111',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'name' => 'Updated Restaurant',
        'description' => 'Updated Description',
        'address' => 'Updated Address',
        'phone' => '0911111111',
    ]);
}

public function test_restaurant_is_not_updated_when_validation_fails(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'name' => 'Original Name',
    ]);

    $this->actingAs($manager, 'sanctum')
        ->putJson("/api/v1/restaurants/{$restaurant->id}", [
            'name' => '',
        ])
        ->assertUnprocessable();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'name' => 'Original Name',
    ]);
}

public function test_manager_can_upload_logo(): void
{
    Storage::fake('public');

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $file = UploadedFile::fake()->image('logo.jpg');

    $response = $this->actingAs($manager, 'sanctum')
        ->put(
            "/api/v1/restaurants/{$restaurant->id}",
            [
                'logo' => $file,
            ]
        );

    $response->assertOk();

    $restaurant->refresh();

    $this->assertNotNull($restaurant->logo);

    Storage::disk('public')->assertExists(
        $restaurant->logo
    );
}

public function test_admin_can_approve_restaurant(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'approved',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'approved',
        'status' => 'active',
    ]);
}

public function test_approved_restaurant_becomes_active(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'approved',
        ])
        ->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'approved',
        'status' => 'active',
    ]);
}

public function test_admin_can_reject_restaurant(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'rejected',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'rejected',
        'status' => 'inactive',
    ]);
}

public function test_rejected_restaurant_becomes_inactive(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'rejected',
        ])
        ->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'rejected',
        'status' => 'inactive',
    ]);
}

public function test_restaurant_manager_cannot_update_approval_status(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'approved',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);
}

public function test_customer_cannot_update_approval_status(): void
{
    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($customer, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'approved',
        ]);

    $response->assertForbidden();
}

public function test_guest_cannot_update_approval_status(): void
{
    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->patchJson(
        $this->endpoint . '/' . $restaurant->id . '/approval-status',
        [
            'approval_status' => 'approved',
        ]
    );

    $response->assertUnauthorized();
}

public function test_approval_status_validation_fails_for_invalid_value(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'invalid_status',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['approval_status']);

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);
}

public function test_admin_can_change_pending_restaurant_to_approved(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'approved',
        ])
        ->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'approved',
        'status' => 'active',
    ]);
}

public function test_admin_can_change_approved_restaurant_to_rejected(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'approved',
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'rejected',
        ])
        ->assertOk();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
        'approval_status' => 'rejected',
        'status' => 'inactive',
    ]);
}

public function test_approval_status_response_returns_updated_restaurant(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $restaurant = Restaurant::factory()->create([
        'approval_status' => 'pending',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson($this->endpoint . '/' . $restaurant->id . '/approval-status', [
            'approval_status' => 'approved',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $restaurant->id)
        ->assertJsonPath('data.approval_status', 'approved')
        ->assertJsonPath('data.status', 'active');
}

public function test_admin_can_delete_restaurant(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $restaurant = Restaurant::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson($this->endpoint . '/' . $restaurant->id);

    $response->assertOk();
}

public function test_deleted_restaurant_is_removed_from_database(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $restaurant = Restaurant::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson($this->endpoint . '/' . $restaurant->id)
        ->assertOk();

    $this->assertDatabaseMissing('restaurants', [
        'id' => $restaurant->id,
    ]);
}

public function test_manager_cannot_delete_restaurant(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = Restaurant::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->deleteJson($this->endpoint . '/' . $restaurant->id);

    $response->assertForbidden();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
    ]);
}

public function test_customer_cannot_delete_restaurant(): void
{
    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $restaurant = Restaurant::factory()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->deleteJson($this->endpoint . '/' . $restaurant->id);

    $response->assertForbidden();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
    ]);
}

public function test_guest_cannot_delete_restaurant(): void
{
    $restaurant = Restaurant::factory()->create();

    $response = $this->deleteJson(
        $this->endpoint . '/' . $restaurant->id
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('restaurants', [
        'id' => $restaurant->id,
    ]);
}

public function test_admin_can_delete_restaurant_with_logo(): void
{
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Storage::disk('public')->put(
        'restaurants/test-logo.jpg',
        'fake image content'
    );

    $restaurant = Restaurant::factory()->create([
        'logo' => 'restaurants/test-logo.jpg',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson($this->endpoint . '/' . $restaurant->id);

    $response->assertOk();

    $this->assertDatabaseMissing('restaurants', [
        'id' => $restaurant->id,
    ]);

    Storage::disk('public')->assertMissing(
        'restaurants/test-logo.jpg'
    );
}

public function test_delete_response_returns_expected_message(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $restaurant = Restaurant::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson($this->endpoint . '/' . $restaurant->id);

    $response->assertOk()
        ->assertJsonPath(
            'message',
            'Restaurant deleted successfully.'
        );
}

public function test_admin_cannot_delete_nonexistent_restaurant(): void
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson($this->endpoint . '/999999');

    $response->assertNotFound();
}

public function test_customer_cannot_delete_nonexistent_restaurant(): void
{
    $customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $response = $this->actingAs($customer, 'sanctum')
        ->deleteJson($this->endpoint . '/999999');

    $response->assertNotFound();
}

private function createRestaurant(
    string $approvalStatus = 'approved',
    string $status = 'active'
): Restaurant {
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $restaurant = new Restaurant([
        'name' => 'Test Restaurant',
        'address' => 'Adama, Ethiopia',
        'phone' => '0912345678',
    ]);

    $restaurant->manager_id = $manager->id;
    $restaurant->approval_status = $approvalStatus;
    $restaurant->status = $status;
    $restaurant->save();

    return $restaurant;
}

public function test_manager_can_create_restaurant_with_coordinates(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson($this->endpoint, [
            'name' => 'Ahmed Restaurant',
            'address' => 'Adama, Ethiopia',
            'latitude' => 8.54,
            'longitude' => 39.27,
            'phone' => '0912345678',
        ]);

    $response->assertCreated();

    $response->assertJsonPath(
        'data.latitude',
        '8.5400000'
    );

    $response->assertJsonPath(
        'data.longitude',
        '39.2700000'
    );
}

public function test_restaurant_coordinates_must_be_valid(): void
{
    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson($this->endpoint, [
            'name' => 'Ahmed Restaurant',
            'address' => 'Adama, Ethiopia',
            'latitude' => 100,
            'longitude' => 200,
            'phone' => '0912345678',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'latitude',
            'longitude',
        ]);
}

public function test_public_can_view_restaurant_menu_items(): void
{
    $restaurant = $this->createRestaurant();

    $category = Category::create([
        'name' => 'Main Food',
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Special Rice',
        'description' => 'Delicious rice',
        'price' => 150.00,
        'is_available' => true,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $response->assertJsonFragment([
        'name' => 'Special Rice',
    ]);
}

public function test_unauthenticated_user_can_view_menu_items(): void
{
    $restaurant = $this->createRestaurant();

    $category = Category::create([
        'name' => 'Main Food',
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Public Meal',
        'description' => 'Available meal',
        'price' => 100.00,
        'is_available' => true,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $response->assertJsonFragment([
        'name' => 'Public Meal',
    ]);
}

public function test_pending_restaurant_menu_items_are_not_visible(): void
{
    $restaurant = $this->createRestaurant(
        'pending',
        'inactive'
    );

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertNotFound();
}

public function test_rejected_restaurant_menu_items_are_not_visible(): void
{
    $restaurant = $this->createRestaurant(
        'rejected',
        'inactive'
    );

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertNotFound();
}

public function test_inactive_restaurant_menu_items_are_not_visible(): void
{
    $restaurant = $this->createRestaurant(
        'approved',
        'inactive'
    );

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertNotFound();
}

public function test_suspended_restaurant_menu_items_are_not_visible(): void
{
    $restaurant = $this->createRestaurant(
        'approved',
        'suspended'
    );

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertNotFound();
}

public function test_only_available_menu_items_are_returned(): void
{
    $restaurant = $this->createRestaurant();

    $category = Category::create([
        'name' => 'Main Food',
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Available Meal',
        'description' => 'Available',
        'price' => 150.00,
        'is_available' => true,
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Unavailable Meal',
        'description' => 'Unavailable',
        'price' => 200.00,
        'is_available' => false,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $response->assertJsonFragment([
        'name' => 'Available Meal',
    ]);

    $response->assertJsonMissing([
        'name' => 'Unavailable Meal',
    ]);
}

public function test_unavailable_menu_items_are_excluded(): void
{
    $restaurant = $this->createRestaurant();

    $category = Category::create([
        'name' => 'Drinks',
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Unavailable Drink',
        'description' => 'Not currently available',
        'price' => 80.00,
        'is_available' => false,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $response->assertJsonMissing([
        'name' => 'Unavailable Drink',
    ]);

    $this->assertCount(0, $response->json('data'));
}

public function test_empty_menu_returns_empty_collection(): void
{
    $restaurant = $this->createRestaurant();

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $this->assertSame([], $response->json('data'));
}

public function test_nonexistent_restaurant_menu_items_return_not_found(): void
{
    $response = $this->getJson(
        $this->endpoint . '/999999/menu-items'
    );

    $response->assertNotFound();
}

public function test_menu_items_returns_expected_fields(): void
{
    $restaurant = $this->createRestaurant();

    $category = Category::create([
        'name' => 'Main Food',
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Special Rice',
        'description' => 'Delicious rice',
        'price' => 150.00,
        'is_available' => true,
        'image' => null,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            '*' => [
                'id',
                'restaurant_id',
                'category_id',
                'name',
                'description',
                'price',
                'is_available',
                'image',
                'created_at',
                'updated_at',
            ],
        ],
    ]);
}

public function test_menu_items_belong_to_requested_restaurant(): void
{
    $restaurant = $this->createRestaurant();

    $otherRestaurant = $this->createRestaurant();

    $category = Category::create([
        'name' => 'Main Food',
    ]);

    $restaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Restaurant One Meal',
        'price' => 150.00,
        'is_available' => true,
    ]);

    $otherRestaurant->menuItems()->create([
        'category_id' => $category->id,
        'name' => 'Restaurant Two Meal',
        'price' => 200.00,
        'is_available' => true,
    ]);

    $response = $this->getJson(
        $this->endpoint . '/' . $restaurant->id . '/menu-items'
    );

    $response->assertOk();

    $response->assertJsonFragment([
        'name' => 'Restaurant One Meal',
    ]);

    $response->assertJsonMissing([
        'name' => 'Restaurant Two Meal',
    ]);
}

public function test_manager_can_replace_existing_logo(): void
{
    Storage::fake('public');

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    Storage::disk('public')->put(
        'restaurants/old-logo.jpg',
        'old logo'
    );

    $restaurant = new Restaurant([
        'name' => 'Test Restaurant',
        'address' => 'Adama, Ethiopia',
        'phone' => '0912345678',
        'logo' => 'restaurants/old-logo.jpg',
    ]);

    $restaurant->manager_id = $manager->id;
    $restaurant->save();

    $newLogo = UploadedFile::fake()->create(
        'new-logo.jpg',
        100,
        'image/jpeg'
    );

    $response = $this->actingAs($manager, 'sanctum')
        ->put(
            "/api/v1/restaurants/{$restaurant->id}",
            [
                'logo' => $newLogo,
            ]
        );

    $response->assertOk();

    $restaurant->refresh();

    $this->assertNotNull($restaurant->logo);

    Storage::disk('public')->assertMissing(
        'restaurants/old-logo.jpg'
    );

    Storage::disk('public')->assertExists(
        $restaurant->logo
    );
}

public function test_manager_can_remove_existing_logo(): void
{
    Storage::fake('public');

    $manager = User::factory()->create([
        'role' => 'restaurant_manager',
    ]);

    Storage::disk('public')->put(
        'restaurants/logo.jpg',
        'logo'
    );

    $restaurant = new Restaurant([
        'name' => 'Test Restaurant',
        'address' => 'Adama, Ethiopia',
        'latitude' => 8.54,
        'longitude' => 39.27,
        'phone' => '0912345678',
        'logo' => 'restaurants/logo.jpg',
    ]);

    $restaurant->manager_id = $manager->id;
    $restaurant->save();

    $response = $this->actingAs($manager, 'sanctum')
        ->put(
            "/api/v1/restaurants/{$restaurant->id}",
            [
                'logo' => null,
            ]
        );

    $response->assertOk();

    $restaurant->refresh();

    $this->assertNull($restaurant->logo);

    Storage::disk('public')->assertMissing(
        'restaurants/logo.jpg'
    );
}
}