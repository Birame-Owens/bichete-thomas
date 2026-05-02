<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'module',
    'description',
    'subject_type',
    'subject_id',
    'before',
    'after',
    'metadata',
    'ip_address',
    'user_agent',
    'created_at',
])]
class LogSysteme extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'logs_systeme';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
