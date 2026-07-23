<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model pour tracker les emails en file d'attente
 */
class EmailJobQueue extends Model
{
    use HasFactory;

    protected $table = 'email_job_queues';

    protected $fillable = [
        'email',
        'subject',
        'template',
        'data',
        'signature',
        'status', // pending, processing, sent, failed
        'attempts',
        'max_attempts',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Tracer les ouvertures/clics
     */
    public function tracker()
    {
        return $this->hasOne(EmailTracker::class, 'email_job_queue_id');
    }
}
