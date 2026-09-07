<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Order\AssignDriverRequest;
use App\Http\Requests\V1\Order\StoreOrderRequest;
use App\Http\Requests\V1\Order\UpdateOrderStatusRequest;
use App\Http\Resources\V1\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\OrderService;
use DomainException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $orderService
    ) {
    }

    /**
     * Retrieve orders accessible to the authenticated user.
     */
    public function index(): AnonymousResourceCollection
    {
        $orders = $this->orderService->getAccessibleOrders(
            request()->user()
        );

        return OrderResource::collection($orders);
    }

    /**
     * Retrieve one accessible order.
     */
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return OrderResource::make(
            $order->load([
                'customer',
                'restaurant',
                'driver.user',
                'orderItems',
            ])
        );
    }

    /**
     * Create an order from the customer's cart.
     */
    public function store(
        StoreOrderRequest $request
    ): OrderResource|JsonResponse {
        try {
            $order = $this->orderService->createOrder(
                $request->user(),
                $request->validated()
            );

            ActivityLogger::orderCreated($request);

            return OrderResource::make(
                $order->load([
                    'customer',
                    'restaurant',
                    'driver.user',
                    'orderItems',
                ])
            )->response()->setStatusCode(201);
        } catch (DomainException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to create order.',
                500,
                config('app.debug')
                    ? $e->getMessage()
                    : null
            );
        }
    }

    /**
     * Update order status.
     */
    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order
    ): OrderResource|JsonResponse {
        $this->authorize('updateStatus', $order);

        try {
            $order = $this->orderService->updateStatus(
                $request->user(),
                $order,
                $request->validated()['status']
            );

            ActivityLogger::orderStatusUpdated($request);

            return OrderResource::make(
                $order->load([
                    'customer',
                    'restaurant',
                    'driver.user',
                    'orderItems',
                ])
            );
        } catch (DomainException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to update order status.',
                500,
                config('app.debug')
                    ? $e->getMessage()
                    : null
            );
        }
    }

    /**
     * Assign a driver to an order.
     */
    public function assignDriver(
        AssignDriverRequest $request,
        Order $order
    ): OrderResource|JsonResponse {
        $this->authorize('assignDriver', $order);

        try {
            $order = $this->orderService->assignDriver(
                $order,
                (int) $request->validated()['driver_id']
            );

            ActivityLogger::orderDriverAssigned($request);

            return OrderResource::make(
                $order->load([
                    'customer',
                    'restaurant',
                    'driver.user',
                    'orderItems',
                ])
            );
        } catch (DomainException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to assign driver.',
                500,
                config('app.debug')
                    ? $e->getMessage()
                    : null
            );
        }
    }
}
