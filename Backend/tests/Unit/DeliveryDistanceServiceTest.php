<?php

namespace Tests\Unit;

use App\Services\DeliveryDistanceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryDistanceServiceTest extends TestCase
{
    public function test_it_calculates_road_distance_in_kilometers(): void
    {
        Http::fake([
            'https://router.project-osrm.org/*' => Http::response([
                'routes' => [
                    [
                        'distance' => 7500,
                    ],
                ],
            ], 200),
        ]);

        $service = new DeliveryDistanceService();

        $distance = $service->calculateDistanceKm(
            9.0300,
            38.7400,
            9.0500,
            38.7600
        );

        $this->assertSame(7.5, $distance);
    }

    public function test_it_throws_exception_when_routing_request_fails(): void
    {
        Http::fake([
            'https://router.project-osrm.org/*' => Http::response([], 500),
        ]);

        $service = new DeliveryDistanceService();

        $this->expectException(\Exception::class);

        $service->calculateDistanceKm(
            9.0300,
            38.7400,
            9.0500,
            38.7600
        );
    }

    public function test_it_throws_exception_when_distance_is_missing(): void
    {
        Http::fake([
            'https://router.project-osrm.org/*' => Http::response([
                'routes' => [],
            ], 200),
        ]);

        $service = new DeliveryDistanceService();

        $this->expectException(\Exception::class);

        $service->calculateDistanceKm(
            9.0300,
            38.7400,
            9.0500,
            38.7600
        );
    }
}