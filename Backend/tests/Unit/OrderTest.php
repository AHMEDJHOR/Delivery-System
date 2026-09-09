<?php

namespace Tests\Unit;

use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function test_order_has_fillable_attributes(): void
    {
        $this->assertEquals([
            'delivery_address',
            'phone',
            'delivery_latitude',
            'delivery_longitude',
        ], (new Order())->getFillable());
    }

    public function test_order_has_correct_casts(): void
    {
        $model = new Order();

        $this->assertEquals('decimal:2', $model->getCasts()['subtotal']);
        $this->assertEquals('decimal:2', $model->getCasts()['delivery_fee']);
        $this->assertEquals('decimal:2', $model->getCasts()['total_amount']);
        $this->assertEquals('datetime', $model->getCasts()['assigned_at']);
        $this->assertEquals('datetime', $model->getCasts()['delivered_at']);
    }

    public function test_order_belongs_to_customer(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Order())->customer());
    }

    public function test_order_belongs_to_restaurant(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Order())->restaurant());
    }

    public function test_order_belongs_to_driver(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Order())->driver());
    }

    public function test_order_has_many_order_items(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Order())->orderItems());
    }

    public function test_order_has_one_payment(): void
    {
        $this->assertInstanceOf(HasOne::class, (new Order())->payment());
    }
}