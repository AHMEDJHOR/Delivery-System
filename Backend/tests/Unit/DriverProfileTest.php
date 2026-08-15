<?php

namespace Tests\Unit;

use App\Models\DriverProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class DriverProfileTest extends TestCase
{
    public function test_driver_profile_has_fillable_attributes(): void
    {
        $model = new DriverProfile();

        $this->assertEquals([
            'user_id',
            'vehicle_type',
            'license_number',
            'approval_status',
            'is_online',
        ], $model->getFillable());
    }

    public function test_driver_profile_casts_is_online_to_boolean(): void
    {
        $model = new DriverProfile();

        $this->assertEquals('boolean', $model->getCasts()['is_online']);
    }

    public function test_driver_profile_belongs_to_user(): void
    {
        $model = new DriverProfile();

        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_driver_profile_has_many_locations(): void
    {
        $model = new DriverProfile();

        $this->assertInstanceOf(HasMany::class, $model->locations());
    }
}