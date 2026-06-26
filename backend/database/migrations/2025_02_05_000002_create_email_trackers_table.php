<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_job_queue_id')->nullable()->constrained('email_job_queues')->onDelete('cascade');
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('pixel_loaded')->default(false);
            $table->integer('click_count')->default(0);
            $table->timestamps();
            
            $table->index('email');
            $table->index('token');
            $table->index('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_trackers');
    }
};
