<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coiffeuse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoiffeuseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $coiffeuses = Coiffeuse::query()
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('nom', 'ilike', "%{$search}%")
                        ->orWhere('prenom', 'ilike', "%{$search}%")
                        ->orWhere('telephone', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($perPage);

        return response()->json([
            'data' => $coiffeuses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'pourcentage_commission' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $coiffeuse = Coiffeuse::query()->create($data);

        return response()->json([
            'message' => 'Coiffeuse creee.',
            'data' => $coiffeuse,
        ], 201);
    }

    public function show(Coiffeuse $coiffeuse): JsonResponse
    {
        return response()->json([
            'data' => $coiffeuse,
        ]);
    }

    public function update(Request $request, Coiffeuse $coiffeuse): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'pourcentage_commission' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $coiffeuse->update($data);

        return response()->json([
            'message' => 'Coiffeuse mise a jour.',
            'data' => $coiffeuse,
        ]);
    }

    public function destroy(Coiffeuse $coiffeuse): JsonResponse
    {
        $coiffeuse->delete();

        return response()->json([
            'message' => 'Coiffeuse supprimee.',
        ]);
    }
}
