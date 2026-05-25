<?php

namespace App\Http\Controllers\Api\Gerante;

use App\Http\Controllers\Controller;
use App\Jobs\SendSignalementNotification;
use App\Models\Signalement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SignalementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $signalements = Signalement::query()
            ->where('gerante_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $signalements]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'        => ['required', Rule::in(['produit', 'materiel', 'autre'])],
            'titre'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'urgence'     => ['required', Rule::in(['normale', 'urgente'])],
        ]);

        $signalement = Signalement::query()->create([
            ...$data,
            'gerante_id' => $request->user()->id,
        ]);

        SendSignalementNotification::dispatch($signalement->id);

        return response()->json([
            'message' => 'Signalement envoye.',
            'data'    => $signalement->load('gerante'),
        ], 201);
    }
}
