<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Cart\StoreCartItemRequest;
use App\Http\Requests\V1\Cart\UpdateCartItemRequest;
use App\Http\Resources\V1\CartResource;
use App\Http\Traits\ApiResponse;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Services\ActivityLogger;
use DomainException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve the authenticated customer's cart.
     *
     * Customer only.
     */
    public function index(): AnonymousResourceCollection
    {
        $cartItems = CartItem::query()
            ->where('customer_id', request()->user()->id)
            ->with('menuItem.restaurant')
            ->latest()
            ->get();

        return CartResource::collection($cartItems);
    }

    /**
     * Add a menu item to the authenticated customer's cart.
     *
     * Customer only.
     */
    public function store(StoreCartItemRequest $request): CartResource|JsonResponse
    {
        try {
            $data = $request->validated();
            $customer = $request->user();

            $cartItem = DB::transaction(function () use (
                $data,
                $customer
            ): CartItem {
                $menuItem = MenuItem::query()
                    ->with('restaurant')
                    ->findOrFail($data['menu_item_id']);

                if (
                    ! $menuItem->is_available ||
                    $menuItem->restaurant->approval_status !== 'approved' ||
                    $menuItem->restaurant->status !== 'active'
                ) {
                    throw new DomainException(
                        'This menu item is not currently available.'
                    );
                }

                $cartRestaurantId = CartItem::query()
                    ->join(
                        'menu_items',
                        'cart_items.menu_item_id',
                        '=',
                        'menu_items.id'
                    )
                    ->where('cart_items.customer_id', $customer->id)
                    ->value('menu_items.restaurant_id');

                if (
                    $cartRestaurantId !== null &&
                    (int) $cartRestaurantId !== (int) $menuItem->restaurant_id
                ) {
                    throw new DomainException(
                        'Your cart can contain items from only one restaurant.'
                    );
                }

                $existingCartItem = $customer->cartItems()
                    ->where('menu_item_id', $menuItem->id)
                    ->first();

                if ($existingCartItem !== null) {
                    $newQuantity = $existingCartItem->quantity + $data['quantity'];

                    if ($newQuantity > 99) {
                        throw new DomainException(
                            'Cart item quantity cannot exceed 99.'
                        );
                    }

                    $existingCartItem->update([
                        'quantity' => $newQuantity,
                    ]);

                    return $existingCartItem;
                }

                return $customer->cartItems()->create([
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $data['quantity'],
                ]);
            });

            ActivityLogger::cartItemAdded($request);

            return CartResource::make(
                $cartItem->load('menuItem.restaurant')
            )->additional([
                'message' => 'Item added to cart successfully.',
            ]);
        } catch (DomainException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to add item to cart.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Update the quantity of a cart item.
     *
     * Customer who owns the cart item only.
     */
    public function update(
        UpdateCartItemRequest $request,
        CartItem $cartItem
    ): CartResource|JsonResponse {
        $this->authorize('update', $cartItem);

        try {
            $cartItem->update([
                'quantity' => $request->validated()['quantity'],
            ]);

            ActivityLogger::cartItemUpdated($request);

            return CartResource::make(
                $cartItem->refresh()->load('menuItem.restaurant')
            )->additional([
                'message' => 'Cart item updated successfully.',
            ]);
        } catch (Exception $e) {
            return $this->error(
                'Unable to update cart item.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Remove an item from the authenticated customer's cart.
     *
     * Customer who owns the cart item only.
     */
    public function destroy(CartItem $cartItem): JsonResponse
    {
        $this->authorize('delete', $cartItem);

        try {
            $cartItem->delete();

            ActivityLogger::cartItemDeleted(request());

            return $this->deleted(
                'Cart item removed successfully.'
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to remove cart item.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Clear all items from the authenticated customer's cart.
     *
     * Customer only.
     */
    public function clear(): JsonResponse
    {
        try {
            CartItem::query()
                ->where('customer_id', request()->user()->id)
                ->delete();

            ActivityLogger::cartCleared(request());

            return $this->deleted(
                'Cart cleared successfully.'
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to clear cart.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}

