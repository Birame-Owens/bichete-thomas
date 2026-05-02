<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'raison', 'actif', 'blackliste_at', 'retire_at'])]
class ListeNoireClient extends Model
{
    use HasFactory;

    protected $table = 'liste_noire_clients';

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'blackliste_at' => 'datetime',
            'retire_at' => 'datetime',
        ];
    }
}
