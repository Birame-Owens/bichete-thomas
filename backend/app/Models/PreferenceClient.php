<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id',
    'coiffures_preferees',
    'options_preferees',
    'notes',
    'notifications_whatsapp',
    'notifications_promos',
])]
class PreferenceClient extends Model
{
    use HasFactory;

    protected $table = 'preferences_clients';

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
            'coiffures_preferees' => 'array',
            'options_preferees' => 'array',
            'notifications_whatsapp' => 'boolean',
            'notifications_promos' => 'boolean',
        ];
    }
}
