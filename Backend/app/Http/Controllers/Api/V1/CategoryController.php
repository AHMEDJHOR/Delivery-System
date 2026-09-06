<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Category\StoreCategoryRequest;
use App\Http\Requests\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\MenuItemResource;
use App\Http\Traits\ApiResponse;
use App\Models\Category;
use App\Services\ActivityLogger;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve all categories.
     *
     * Public endpoint.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->latest()
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Display a category.
     *
     * Public endpoint.
     */
    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    /**
     * Retrieve menu items belonging to a category.
     *
     * Public endpoint.
     */
    public function menuItems(Category $category): AnonymousResourceCollection
    {
        $menuItems = $category->menuItems()
            ->where('is_available', true)
            ->whereHas('restaurant', function ($query): void {
                $query->where('approval_status', 'approved')
                    ->where('status', 'active');
            })
            ->latest()
            ->get();

        return MenuItemResource::collection($menuItems);
    }

    /**
     * Create a category.
     *
     * Admin only.
     */
    public function store(StoreCategoryRequest $request): CategoryResource|JsonResponse
    {
        try {
            $category = Category::create($request->validated());

            ActivityLogger::categoryCreated($request);

            return CategoryResource::make($category)
                ->additional([
                    'message' => 'Category created successfully.',
                ])
                ->response()
                ->setStatusCode(201);
        } catch (Exception $e) {
            return $this->error(
                'Unable to create category.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Update a category.
     *
     * Admin only.
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): CategoryResource|JsonResponse {
        try {
            $category->update($request->validated());

            $category->refresh();

            ActivityLogger::categoryUpdated($request);

            return CategoryResource::make($category)
                ->additional([
                    'message' => 'Category updated successfully.',
                ]);
        } catch (Exception $e) {
            return $this->error(
                'Unable to update category.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Delete a category.
     *
     * Admin only.
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            $category->delete();

            ActivityLogger::categoryDeleted(request());

            return $this->deleted(
                'Category deleted successfully.'
            );
        } catch (Exception $e) {
            return $this->error(
                'Unable to delete category.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}