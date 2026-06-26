<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetAdminRole extends Command
{
    protected $signature = 'user:make-admin';
    protected $description = 'Set all users to admin role';

    public function handle()
    {
        try {
            $updated = DB::table('users')->update(['role' => 'admin']);
            $this->info("✅ $updated users updated to admin role");
            
            $users = DB::table('users')->select('id', 'email', 'role')->get();
            $this->info("\nCurrent users:");
            foreach ($users as $user) {
                $this->line("  ID: {$user->id} | Email: {$user->email} | Role: {$user->role}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
