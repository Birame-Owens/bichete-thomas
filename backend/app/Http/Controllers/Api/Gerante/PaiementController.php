<?php

namespace App\Http\Controllers\Api\Gerante;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $paiements = $this->filteredQuery($request)
            ->with($this->relations())
            ->latest('date_paiement')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paiements,
        ]);
    }

    public function show(Paiement $paiement): JsonResponse
    {
        $paiement->load($this->relations());

        return response()->json([
            'data' => $paiement,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'client',
            'reservation.client',
            'reservation.details',
        ];
    }

    private function filteredQuery(Request $request): Builder
    {
        return Paiement::query()
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('statut', $request->string('statut')->toString()))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('mode_paiement'), fn (Builder $q) => $q->where('mode_paiement', $request->string('mode_paiement')->toString()))
            ->when($request->integer('reservation_id'), fn (Builder $q, int $id) => $q->where('reservation_id', $id))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('date_paiement', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('date_paiement', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $search = trim($request->string('search')->toString());
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('numero_recu', 'ilike', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $c) => $c
                            ->where('nom', 'ilike', "%{$search}%")
                            ->orWhere('prenom', 'ilike', "%{$search}%")
                            ->orWhere('telephone', 'ilike', "%{$search}%"))
                        ->orWhereHas('reservation.client', fn (Builder $c) => $c
                            ->where('nom', 'ilike', "%{$search}%")
                            ->orWhere('prenom', 'ilike', "%{$search}%"));
                    if (ctype_digit($search)) {
                        $sub->orWhere('id', (int) $search)
                            ->orWhere('reservation_id', (int) $search);
                    }
                });
            });
    }
}
