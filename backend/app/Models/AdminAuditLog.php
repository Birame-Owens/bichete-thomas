<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    use MassPrunable;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_id',
        'admin_email',
        'method',
        'url',
        'action',
        'request_data',
        'response_status',
        'ip_address',
        'user_agent',
        'duration_ms',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(90));
    }
}
