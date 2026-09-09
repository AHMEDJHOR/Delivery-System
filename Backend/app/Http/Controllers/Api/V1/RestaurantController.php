<?php

namespace App\Http\Controllers\Api\V1;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Restaurant\StoreRestaurantRequest;
use App\Http\Requests\V1\Restaurant\UpdateRestaurantApprovalStatusRequest;
use App\Http\Requests\V1\Restaurant\UpdateRestaurantRequest;
use App\Http\Resources\V1\RestaurantResource;
use App\Http\Traits\ApiResponse;
use App\Models\Restaurant;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RestaurantController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve all approved and active restaurants.
     *
     * Public endpoint.
     */
    public function index(): AnonymousResourceCollection
    {
        $restaurants = Restaurant::query()
            ->where('approval_status', 'approved')
            ->where('status', 'active')
            ->latest()
            ->get();

        return RestaurantResource::collection($restaurants);
    }

    /**
     * Display an approved and active restaurant.
     *
     * Public endpoint.
     */
    public function show(Restaurant $restaurant): RestaurantResource|JsonResponse
    {
        if (
            $restaurant->approval_status !== 'approved' ||
            $restaurant->status !== 'active'
        ) {
            return $this->notFound('Restaurant not found.');
        }

        return RestaurantResource::make($restaurant);
    }

    /**
     * Retrieve available menu items for an approved and active restaurant.
     *
     * Public endpoint.
     *
     * MenuItemResource can be added later.
     */
    public function menuItems(Restaurant $restaurant): JsonResponse
    {
        if (
            $restaurant->approval_status !== 'approved' ||
            $restaurant->status !== 'active'
        ) {
            return $this->notFound('Restaurant not found.');
        }

        return $this->success(
            $restaurant->menuItems()
                ->where('is_available', true)
                ->get()
        );
    }

    /**
     * Create a restaurant.
     *
     * The restaurant starts as pending and inactive.
     *
     * Restaurant Manager only.
     */
    public function store(StoreRestaurantRequest $request): RestaurantResource|JsonResponse
{
    try {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store(
                'restaurants',
                'public'
            );
        }

        $restaurant = new Restaurant($data);

        $restaurant->manager_id = $request->user()->id;
        $restaurant->approval_status = 'pending';
        $restaurant->status = 'inactive';
        $restaurant->save();

        ActivityLogger::restaurantCreated($request);

        return RestaurantResource::make($restaurant);
    } catch (\Exception $e) {
        return $this->error(
            'Unable to create restaurant.',
            500,
            config('app.debug') ? $e->getMessage() : null
        );
    }
}

/**
 * Update restaurant information.
 *
 * Restaurant Manager who owns the restaurant only.
 */
public function update(
    UpdateRestaurantRequest $request,
    Restaurant $restaurant
): RestaurantResource|JsonResponse {
    $this->authorize('update', $restaurant);

    $newLogo = null;
    $oldLogo = $restaurant->logo;

    try {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            // Store the new logo first.
            $newLogo = $request->file('logo')->store(
                'restaurants',
                'public'
            );

            $data['logo'] = $newLogo;

            // Update the database before removing the old logo.
            $restaurant->update($data);

            // Remove the old logo only after the new logo is stored
            // and the database update succeeds.
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        } elseif (
            $request->has('logo') &&
            $request->input('logo') === null
        ) {
            // Remove the logo from the database first.
            $restaurant->update([
                ...$data,
                'logo' => null,
            ]);

            // Delete the old file only after the database update succeeds.
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        } else {
            $restaurant->update($data);
        }

        ActivityLogger::restaurantUpdated($request);

        return RestaurantResource::make(
            $restaurant->fresh()
        );
    } catch (\Exception $e) {
        // If the new file was successfully stored but the database update
        // failed, remove the new file so it does not become an orphan.
        if ($newLogo) {
            Storage::disk('public')->delete($newLogo);
        }

        return $this->error(
            'Unable to update restaurant.',
            500,
            config('app.debug') ? $e->getMessage() : null
        );
    }
}

    /**
     * Approve or reject a restaurant.
     *
     * Admin only.
     *
     * Approved restaurants become active.
     * Rejected restaurants become inactive.
     */
    public function updateApprovalStatus(
        UpdateRestaurantApprovalStatusRequest $request,
        Restaurant $restaurant
    ): RestaurantResource|JsonResponse {
        $this->authorize('updateApprovalStatus', $restaurant);

        try {
            $approvalStatus = $request->validated()['approval_status'];

            $restaurant->approval_status = $approvalStatus;
            $restaurant->status = $approvalStatus === 'approved'
                ? 'active'
                : 'inactive';

            $restaurant->save();

            ActivityLogger::restaurantApprovalUpdated($request);

            return RestaurantResource::make($restaurant->fresh());
        } catch (\Exception $e) {
            return $this->error(
                'Unable to update restaurant approval status.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Delete a restaurant.
     *
     * Admin only.
     */
public function destroy(Restaurant $restaurant): JsonResponse
{
    $this->authorize('delete', $restaurant);

    try {
        $logo = $restaurant->logo;

        $restaurant->delete();

        if ($logo) {
            Storage::disk('public')->delete($logo);
        }

        ActivityLogger::restaurantDeleted(request());

        return $this->deleted(
            'Restaurant deleted successfully.'
        );
    } catch (\Exception $e) {
        return $this->error(
            'Unable to delete restaurant.',
            500,
            config('app.debug') ? $e->getMessage() : null
        );
    }
}
    /**
 * Retrieve the authenticated manager's restaurants.
 *
 * Restaurant Manager only.
 */
public function myRestaurants(Request $request): AnonymousResourceCollection
{
    $restaurants = $request->user()
        ->restaurants()
        ->latest()
        ->get();

    return RestaurantResource::collection($restaurants);
}
}