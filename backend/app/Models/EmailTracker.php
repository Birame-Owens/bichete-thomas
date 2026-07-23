<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model pour tracker les interactions avec les emails
 */
class EmailTracker extends Model
{
    use HasFactory;

    protected $table = 'email_trackers';

    protected $fillable = [
        'email_job_queue_id',
        'email',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'bounce_reason',
        'ip_address',
        'user_agent',
        'pixel_loaded', // true si pixel de tracking chargé (ouverture)
        'click_count',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship
     */
    public function jobQueue()
    {
        return $this->belongsTo(EmailJobQueue::class, 'email_job_queue_id');
    }
}
