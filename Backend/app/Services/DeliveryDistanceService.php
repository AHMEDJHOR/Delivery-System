<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class DeliveryDistanceService
{
    /**
     * Calculate road distance in kilometers.
     *
     * @throws Exception
     */
    public function calculateDistanceKm(
        float $restaurantLatitude,
        float $restaurantLongitude,
        float $deliveryLatitude,
        float $deliveryLongitude
    ): float {
        $baseUrl = rtrim(
            config(
                'delivery.routing.base_url',
                'https://router.project-osrm.org'
            ),
            '/'
        );

        $url = $baseUrl
            . '/route/v1/driving/'
            . $restaurantLongitude . ','
            . $restaurantLatitude . ';'
            . $deliveryLongitude . ','
            . $deliveryLatitude;

        $response = Http::timeout(10)
            ->get($url, [
                'overview' => 'false',
            ]);

        if (! $response->successful()) {
            throw new Exception(
                'Unable to calculate delivery distance.'
            );
        }

        $distanceMeters = $response->json(
            'routes.0.distance'
        );

        if (! is_numeric($distanceMeters)) {
            throw new Exception(
                'Delivery distance could not be determined.'
            );
        }

        return round(
            (float) $distanceMeters / 1000,
            2
        );
    }
}