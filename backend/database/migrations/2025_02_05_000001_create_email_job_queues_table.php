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
        Schema::create('email_job_queues', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('subject');
            $table->string('template')->default('emails.generic');
            $table->json('data')->nullable();
            $table->string('signature')->unique(); // Pour déduplication
            $table->enum('status', ['pending', 'processing', 'sent', 'failed'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('email');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_job_queues');
    }
};
