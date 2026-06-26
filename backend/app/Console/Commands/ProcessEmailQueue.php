<?php

namespace App\Console\Commands;

use App\Services\EmailJobQueueService;
use Illuminate\Console\Command;

class ProcessEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process {--batch=5 : Nombre d\'emails à traiter par batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traiter les emails en attente de la file d\'attente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        
        $this->info("📧 Traitement de la file d'attente des emails (batch: {$batchSize})...");
        
        $result = EmailJobQueueService::processPending($batchSize);
        
        $this->line('');
        $this->info('✅ Traitement terminé!');
        $this->line("   Traités: {$result['processed']}");
        $this->line("   Échoués: {$result['failed']}");
        $this->line('');
        
        return 0;
    }
}
