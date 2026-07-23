<?php

namespace App\Console\Commands;

use App\Services\EmailJobQueueService;
use Illuminate\Console\Command;

class RetryFailedEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:retry-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Réessayer les emails échoués';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Remise en file des emails échoués...');
        
        $count = EmailJobQueueService::retryFailed();
        
        $this->line('');
        $this->info("✅ {$count} email(s) remis en file d'attente!");
        $this->line('');
        
        return 0;
    }
}
