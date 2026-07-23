<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['role_id', 'name', 'email', 'password', 'actif', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasOne<Client, $this>
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /**
     * @return HasMany<PersonalAccessToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    /**
     * @return HasMany<LogSysteme, $this>
     */
    public function logsSysteme(): HasMany
    {
        return $this->hasMany(LogSysteme::class);
    }

    public function createApiToken(string $name = 'api'): string
    {
        $plainTextToken = Str::random(80);

        // last_used_at = now() : sert d ancre pour la verification d inactivite
        // (I1). Sans ca, un token frais avec last_used_at NULL ne pourrait
        // jamais etre invalide pour inactivite, meme si le compte est laisse
        // a l abandon entre la creation et la 1re requete.
        $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        return $plainTextToken;
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role !== null && in_array($this->role->nom, $roles, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }
}
