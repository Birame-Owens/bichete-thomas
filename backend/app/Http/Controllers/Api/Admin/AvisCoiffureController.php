<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvisCoiffure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvisCoiffureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AvisCoiffure::query()
            ->with(['coiffure:id,nom,image', 'client:id,nom,prenom,telephone,email', 'reservation:id,date_reservation,statut'])
            ->when($request->filled('statut'), fn (Builder $query) => $query->where('statut', $request->string('statut')->toString()))
            ->when($request->integer('coiffure_id'), fn (Builder $query, int $coiffureId) => $query->where('coiffure_id', $coiffureId))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nom_client', 'ilike', "%{$search}%")
                        ->orWhere('telephone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('commentaire', 'ilike', "%{$search}%")
                        ->orWhereHas('coiffure', fn (Builder $coiffureQuery) => $coiffureQuery->where('nom', 'ilike', "%{$search}%"));
                });
            })
            ->latest('created_at');

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return response()->json([
            'data' => $query->paginate($perPage),
            'meta' => [
                'total' => AvisCoiffure::query()->count(),
                'en_attente' => AvisCoiffure::query()->where('statut', 'en_attente')->count(),
                'approuves' => AvisCoiffure::query()->where('statut', 'approuve')->count(),
                'rejetes' => AvisCoiffure::query()->where('statut', 'rejete')->count(),
            ],
        ]);
    }

    public function show(AvisCoiffure $avisCoiffure): JsonResponse
    {
        return response()->json([
            'data' => $avisCoiffure->load(['coiffure', 'client', 'reservation']),
        ]);
    }

    public function update(Request $request, AvisCoiffure $avisCoiffure): JsonResponse
    {
        $data = $request->validate([
            'note' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['sometimes', 'string', 'min:8', 'max:800'],
            'statut' => ['sometimes', Rule::in(['en_attente', 'approuve', 'rejete'])],
            'verifie' => ['sometimes', 'boolean'],
        ]);

        if (($data['statut'] ?? null) === 'approuve' && $avisCoiffure->publie_at === null) {
            $data['publie_at'] = now();
        }

        if (($data['statut'] ?? null) !== null && $data['statut'] !== 'approuve') {
            $data['publie_at'] = null;
        }

        $avisCoiffure->update($data);

        return response()->json([
            'message' => 'Avis mis a jour.',
            'data' => $avisCoiffure->fresh(['coiffure', 'client', 'reservation']),
        ]);
    }

    public function approve(AvisCoiffure $avisCoiffure): JsonResponse
    {
        $avisCoiffure->update([
            'statut' => 'approuve',
            'publie_at' => $avisCoiffure->publie_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Avis approuve et publie.',
            'data' => $avisCoiffure->fresh(['coiffure', 'client', 'reservation']),
        ]);
    }

    public function reject(AvisCoiffure $avisCoiffure): JsonResponse
    {
        $avisCoiffure->update([
            'statut' => 'rejete',
            'publie_at' => null,
        ]);

        return response()->json([
            'message' => 'Avis rejete.',
            'data' => $avisCoiffure->fresh(['coiffure', 'client', 'reservation']),
        ]);
    }

    public function destroy(AvisCoiffure $avisCoiffure): JsonResponse
    {
        $avisCoiffure->delete();

        return response()->json(['message' => 'Avis supprime.']);
    }
}
