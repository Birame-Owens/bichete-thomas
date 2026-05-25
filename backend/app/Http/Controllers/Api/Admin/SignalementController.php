<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignalementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $signalements = Signalement::query()
            ->with('gerante')
            ->when($request->filled('traite'), fn ($q) => $q->where('traite', $request->boolean('traite')))
            ->when($request->filled('urgence'), fn ($q) => $q->where('urgence', $request->string('urgence')->toString()))
            ->latest()
            ->paginate(30);

        return response()->json(['data' => $signalements]);
    }

    public function nonLusCount(): JsonResponse
    {
        return response()->json([
            'count' => Signalement::query()->where('lu_par_admin', false)->count(),
        ]);
    }

    public function marquerLu(Signalement $signalement): JsonResponse
    {
        if (! $signalement->lu_par_admin) {
            $signalement->update([
                'lu_par_admin' => true,
                'lu_at'        => now(),
            ]);
        }

        return response()->json(['data' => $signalement]);
    }

    public function marquerTraite(Request $request, Signalement $signalement): JsonResponse
    {
        $data = $request->validate([
            'note_admin' => ['nullable', 'string', 'max:1000'],
        ]);

        $signalement->update([
            'traite'       => true,
            'traite_at'    => now(),
            'lu_par_admin' => true,
            'lu_at'        => $signalement->lu_at ?? now(),
            'note_admin'   => $data['note_admin'] ?? null,
        ]);

        return response()->json([
            'message' => 'Signalement marque comme traite.',
            'data'    => $signalement->fresh('gerante'),
        ]);
    }
}
