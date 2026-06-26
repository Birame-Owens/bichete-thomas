<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;

class DeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = DeliveryZone::active()->get(['id', 'nom', 'prix', 'ordre_affichage']);
        return response()->json(['success' => true, 'data' => $zones]);
    }
}
