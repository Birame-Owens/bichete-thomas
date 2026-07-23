<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAdminRoles extends Command
{
    protected $signature = 'admin:fix-roles';
    protected $description = 'Set all users to admin role';

    public function handle()
    {
        $this->info('=== CURRENT USERS ===');
        
        $users = DB::table('users')->select('id', 'name', 'email', 'role')->get();
        
        if ($users->isEmpty()) {
            $this->warn('No users found');
            return;
        }
        
        foreach ($users as $user) {
            $this->info(sprintf('ID: %d | Email: %-30s | Role: %s', $user->id, $user->email, $user->role));
        }
        
        $this->line('');
        $this->info('=== UPDATING USERS TO ADMIN ===');
        
        $updated = DB::table('users')->update(['role' => 'admin']);
        $this->info("✅ Updated $updated users to admin role");
        
        $this->line('');
        $this->info('=== VERIFICATION ===');
        
        $users = DB::table('users')->select('id', 'name', 'email', 'role')->get();
        foreach ($users as $user) {
            $this->info(sprintf('ID: %d | Email: %-30s | Role: %s', $user->id, $user->email, $user->role));
        }
    }
}
