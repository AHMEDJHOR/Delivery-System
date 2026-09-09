<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\DeliveryDistanceService;
use DomainException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CalculateDeliveryFee implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        DeliveryDistanceService $deliveryDistanceService
    ): void {
        $order = Order::query()
            ->with('restaurant')
            ->findOrFail($this->orderId);

        if ($order->delivery_status !== 'pending') {
            return;
        }

        if (
            $order->restaurant->latitude === null
            || $order->restaurant->longitude === null
            || $order->delivery_latitude === null
            || $order->delivery_longitude === null
        ) {
            throw new DomainException(
                'Delivery coordinates are not available.'
            );
        }

        $distanceKm = $deliveryDistanceService->calculateDistanceKm(
            (float) $order->restaurant->latitude,
            (float) $order->restaurant->longitude,
            (float) $order->delivery_latitude,
            (float) $order->delivery_longitude
        );

        $serviceFeeCents = $this->moneyToCents(
            config('delivery.service_fee', '20.00')
        );

        $distanceRatePerKmCents = $this->moneyToCents(
            config('delivery.distance_rate_per_km', '20.00')
        );

        $distanceKmCents = (int) round($distanceKm * 100);

        $deliveryFeeCents = $serviceFeeCents
            + intdiv(
                $distanceKmCents * $distanceRatePerKmCents,
                100
            );

        $maximumDeliveryFeeCents = $this->moneyToCents(
            config('delivery.maximum_delivery_fee', '1020.00')
        );

        if ($deliveryFeeCents > $maximumDeliveryFeeCents) {
            throw new DomainException(
                'The calculated delivery fee exceeds the supported limit.'
            );
        }

        $order->delivery_fee = number_format(
            $deliveryFeeCents / 100,
            2,
            '.',
            ''
        );

        $subtotalCents = $this->moneyToCents(
            $order->subtotal
        );

        $order->total_amount = number_format(
            ($subtotalCents + $deliveryFeeCents) / 100,
            2,
            '.',
            ''
        );

        $order->delivery_status = 'calculated';
        $order->save();
    }

    /**
     * Mark the delivery calculation as failed after the job fails.
     */
    public function failed(Throwable $exception): void
    {
        Order::query()
            ->whereKey($this->orderId)
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'failed',
            ]);
    }

    /**
     * Convert a decimal monetary value to integer cents.
     */
    private function moneyToCents(int|float|string $amount): int
    {
        $value = (string) $amount;

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new DomainException(
                'Invalid monetary value.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '0'
        );

        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }
}
