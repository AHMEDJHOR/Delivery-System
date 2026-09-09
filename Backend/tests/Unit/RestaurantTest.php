<?php

namespace Tests\Unit;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class RestaurantTest extends TestCase
{
    public function test_restaurant_has_fillable_attributes(): void
    {
        $model = new Restaurant();

        $this->assertEquals([
            'name',
            'description',
            'address',
            'latitude',
            'longitude',
            'phone',
            'logo',
        ], $model->getFillable());
    }

    public function test_restaurant_belongs_to_manager(): void
    {
        $model = new Restaurant();

        $this->assertInstanceOf(BelongsTo::class, $model->manager());
    }

    public function test_restaurant_has_many_menu_items(): void
    {
        $model = new Restaurant();

        $this->assertInstanceOf(HasMany::class, $model->menuItems());
    }

    public function test_restaurant_has_many_orders(): void
    {
        $model = new Restaurant();

        $this->assertInstanceOf(HasMany::class, $model->orders());
    }
}