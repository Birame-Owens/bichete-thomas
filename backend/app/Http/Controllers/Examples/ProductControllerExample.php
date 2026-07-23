<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ResourceCrudTrait;
use App\Repositories\ProductRepository;
use App\Data\Transfer\ProductDTO;
use App\Events\LowStockAlert;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

/**
 * 📦 EXAMPLE: PRODUCT CONTROLLER - USING NEW ARCHITECTURE
 * 
 * Demonstrates:
 * - BaseController inheritance
 * - ResourceCrudTrait for CRUD
 * - DTOs for validation
 * - Audit logging
 * - Event dispatching
 */
class ProductControllerExample extends BaseController
{
    use ResourceCrudTrait;

    protected ProductRepository $repository;

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    // ========================
    // REQUIRED BY TRAIT
    // ========================

    protected function getRepository()
    {
        return $this->repository;
    }

    protected function getResourceName(): string
    {
        return 'Product';
    }

    protected function getValidationRules(string $action = 'create'): array
    {
        return ProductDTO::rules();
    }

    // ========================
    // CUSTOM METHODS
    // ========================

    /**
     * Get trending products
     */
    public function trending(): JsonResponse
    {
        try {
            $products = $this->repository->getTrending(10);

            AuditLogService::logPerformance('trending_products', 50);

            return $this->success($products, 'Trending products retrieved');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve trending products', $e);
        }
    }

    /**
     * Search products with filters
     */
    public function search(): JsonResponse
    {
        try {
            $query = request()->input('q', '');
            $filters = request()->only(['category', 'min_price', 'max_price', 'sort']);
            $params = $this->getPaginationParams();

            $results = $this->repository
                ->search($query, $filters)
                ->paginate($params['per_page']);

            return $this->respondPaginated($results, 'Products found');
        } catch (\Exception $e) {
            return $this->serverError('Search failed', $e);
        }
    }

    /**
     * Get product by category
     */
    public function byCategory(int $categoryId): JsonResponse
    {
        try {
            $params = $this->getPaginationParams();
            $products = $this->repository
                ->getByCategory($categoryId)
                ->paginate($params['per_page']);

            return $this->respondPaginated($products, 'Category products retrieved');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve category products', $e);
        }
    }

    /**
     * Get similar products
     */
    public function similar(int $productId): JsonResponse
    {
        try {
            $product = $this->repository->findOrFail($productId);
            $similar = $this->repository->getSimilar($product, 5);

            return $this->success($similar, 'Similar products retrieved');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Product not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve similar products', $e);
        }
    }

    /**
     * Update stock
     */
    public function updateStock(int $productId): JsonResponse
    {
        try {
            $validated = $this->validateRequest([
                'quantity' => 'required|integer|min:-999|max:999',
                'reason' => 'required|string|in:sale,return,adjustment,damaged',
            ]);

            $product = $this->repository->findOrFail($productId);
            $oldStock = $product->stock;

            // Update stock
            $product->increment('stock', $validated['quantity']);

            // Log the change
            AuditLogService::logStockChange(
                $productId,
                $oldStock,
                $product->stock,
                $validated['reason']
            );

            // Check low stock
            if ($product->stock < 10) {
                event(new LowStockAlert($product, 10));
            }

            return $this->updated($product, 'Stock updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Product not found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationFailed($e->errors());
        } catch (\Exception $e) {
            return $this->serverError('Failed to update stock', $e);
        }
    }

    /**
     * Get product statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->repository->getStatistics();

            return $this->success($stats, 'Statistics retrieved');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve statistics', $e);
        }
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(): JsonResponse
    {
        try {
            $validated = $this->validateRequest([
                'products' => 'required|array|min:1',
                'products.*.id' => 'required|exists:produits,id',
                'products.*.price' => 'nullable|numeric|min:0',
                'products.*.stock' => 'nullable|integer|min:0',
            ]);

            $updated = 0;

            foreach ($validated['products'] as $productData) {
                $this->repository->update($productData['id'], $productData);
                $updated++;

                AuditLogService::logAdminAction(
                    'BULK_UPDATE',
                    'Product',
                    $productData['id'],
                    $productData
                );
            }

            return $this->success(
                ['updated' => $updated],
                "{$updated} products updated"
            );
        } catch (\Exception $e) {
            return $this->serverError('Bulk update failed', $e);
        }
    }
}

/**
 * 📝 ROUTES POUR CE CONTROLLER
 * 
 * Ajouter dans routes/api.php:
 * 
 * Route::apiResource('products', ProductControllerExample::class);
 * Route::get('products/trending', [ProductControllerExample::class, 'trending']);
 * Route::get('products/search', [ProductControllerExample::class, 'search']);
 * Route::get('products/category/{id}', [ProductControllerExample::class, 'byCategory']);
 * Route::get('products/{id}/similar', [ProductControllerExample::class, 'similar']);
 * Route::patch('products/{id}/stock', [ProductControllerExample::class, 'updateStock']);
 * Route::get('products/statistics', [ProductControllerExample::class, 'statistics']);
 * Route::post('products/bulk-update', [ProductControllerExample::class, 'bulkUpdate']);
 */
