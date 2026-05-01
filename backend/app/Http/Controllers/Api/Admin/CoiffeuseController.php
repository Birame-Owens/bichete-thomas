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
        $coiffeuses = Coiffeuse::query()
            ->when($request->boolean('actif'), fn ($query) => $query->where('actif', true))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                });
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(15);

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
