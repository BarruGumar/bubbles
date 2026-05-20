<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditPrune extends Command
{
    protected $signature = 'audit:prune
                            {--days=180 : Eliminar logs com mais de N dias}
                            {--dry-run  : Mostrar quantos seriam eliminados sem apagar}';

    protected $description = 'Remove audit logs mais antigos que N dias (default: 180)';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        if ($days < 1) {
            $this->error('--days deve ser um inteiro positivo.');
            return Command::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = AuditLog::where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("Nenhum audit log com mais de {$days} dias encontrado.");
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] {$count} audit log(s) seriam eliminados (anteriores a {$cutoff->toDateString()}).");
            return Command::SUCCESS;
        }

        $query->delete();

        $this->info("Eliminados {$count} audit log(s) anteriores a {$cutoff->toDateString()}.");

        return Command::SUCCESS;
    }
}
