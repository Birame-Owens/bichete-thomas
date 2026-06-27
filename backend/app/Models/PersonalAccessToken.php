<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['tokenable_type', 'tokenable_id', 'name', 'token', 'last_used_at', 'expires_at'])]
class PersonalAccessToken extends Model
{
    use HasFactory;

    /**
     * @return MorphTo<User, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo('tokenable', 'tokenable_type', 'tokenable_id')->whereType(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
