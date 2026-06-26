<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\JsonResponse;

/**
 * 🔄 RESOURCE CRUD TRAIT
 * 
 * Provides standard CRUD methods for any resource
 * Reduce code duplication across all resource controllers
 */
trait ResourceCrudTrait
{
    /**
     * Get repository instance
     */
    abstract protected function getRepository();

    /**
     * Get resource name for messages
     */
    abstract protected function getResourceName(): string;

    /**
     * Get validation rules
     */
    abstract protected function getValidationRules(string $action = 'create'): array;

    /**
     * 📖 List all resources (paginated)
     */
    public function index(): JsonResponse
    {
        try {
            $params = $this->getPaginationParams();
            $filters = request()->only(['search', 'category', 'status', 'sort']);

            $data = $this->getRepository()
                ->filter($filters)
                ->paginate($params['per_page']);

            return $this->respondPaginated($data, "{$this->getResourceName()} list retrieved");
        } catch (\Exception $e) {
            return $this->serverError("Failed to retrieve {$this->getResourceName()}", $e);
        }
    }

    /**
     * 🔍 Show single resource
     */
    public function show($id): JsonResponse
    {
        try {
            $data = $this->getRepository()->findOrFail($id);
            return $this->success($data, "{$this->getResourceName()} retrieved");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound("{$this->getResourceName()} not found");
        } catch (\Exception $e) {
            return $this->serverError("Failed to retrieve {$this->getResourceName()}", $e);
        }
    }

    /**
     * ✅ Create new resource
     */
    public function store(): JsonResponse
    {
        try {
            $validated = $this->validateRequest($this->getValidationRules('create'));
            $data = $this->getRepository()->create($validated);

            $this->auditLog('CREATE', $this->getResourceName(), $data->id, $validated);

            return $this->created($data, "{$this->getResourceName()} created successfully");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationFailed($e->errors());
        } catch (\Exception $e) {
            return $this->serverError("Failed to create {$this->getResourceName()}", $e);
        }
    }

    /**
     * 🔄 Update resource
     */
    public function update($id): JsonResponse
    {
        try {
            $data = $this->getRepository()->findOrFail($id);
            $validated = $this->validateRequest($this->getValidationRules('update'));

            $old_values = $data->toArray();
            $data = $this->getRepository()->update($id, $validated);
            $changes = array_diff_assoc($validated, $old_values);

            $this->auditLog('UPDATE', $this->getResourceName(), $id, $changes);

            return $this->updated($data, "{$this->getResourceName()} updated successfully");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound("{$this->getResourceName()} not found");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationFailed($e->errors());
        } catch (\Exception $e) {
            return $this->serverError("Failed to update {$this->getResourceName()}", $e);
        }
    }

    /**
     * 🗑️ Delete resource
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->getRepository()->delete($id);
            $this->auditLog('DELETE', $this->getResourceName(), $id);

            return $this->deleted($this->getResourceName(), $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound("{$this->getResourceName()} not found");
        } catch (\Exception $e) {
            return $this->serverError("Failed to delete {$this->getResourceName()}", $e);
        }
    }
}
