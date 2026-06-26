<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = DeliveryZone::orderBy('ordre_affichage')->get();
        return response()->json(['success' => true, 'data' => $zones]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'             => 'required|string|max:100',
            'prix'            => 'required|numeric|min:0',
            'est_active'      => 'boolean',
            'ordre_affichage' => 'integer|min:0',
        ]);

        $zone = DeliveryZone::create($data);
        return response()->json(['success' => true, 'data' => $zone], 201);
    }

    public function update(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $data = $request->validate([
            'nom'             => 'sometimes|required|string|max:100',
            'prix'            => 'sometimes|required|numeric|min:0',
            'est_active'      => 'boolean',
            'ordre_affichage' => 'integer|min:0',
        ]);

        $deliveryZone->update($data);
        return response()->json(['success' => true, 'data' => $deliveryZone]);
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->delete();
        return response()->json(['success' => true]);
    }

    public function toggleStatus(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->update(['est_active' => !$deliveryZone->est_active]);
        return response()->json(['success' => true, 'data' => $deliveryZone]);
    }
}
