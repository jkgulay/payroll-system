<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPayrollSequence extends Command
{
    protected $signature = 'payroll:fix-sequence';
    protected $description = 'Fix the payroll table ID sequence for PostgreSQL';

    public function handle()
    {
        try {
            // Get the database driver
            $driver = DB::connection()->getDriverName();

            if ($driver !== 'pgsql') {
                $this->error('This command is only for PostgreSQL databases.');
                return 1;
            }

            // Get the max ID from payrolls table
            $maxId = DB::table('payrolls')->max('id') ?? 0;

            $this->info("Current max payroll ID: {$maxId}");

            // Reset the sequence
            DB::statement("SELECT setval('payrolls_id_seq', {$maxId})");

            $this->info('✓ Payroll sequence fixed successfully!');

            // Verify
            $nextId = DB::selectOne("SELECT nextval('payrolls_id_seq') as next_id")->next_id;
            $this->info("Next payroll ID will be: {$nextId}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Error fixing sequence: ' . $e->getMessage());
            return 1;
        }
    }
}
