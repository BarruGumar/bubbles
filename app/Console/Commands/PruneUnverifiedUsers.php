<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified
                            {--hours=1 : Apagar utilizadores não verificados há mais de X horas}
                            {--dry-run : Mostrar quantos seriam apagados sem apagar}';

    protected $description = 'Apaga utilizadores que não verificaram o email dentro do prazo';

    public function handle(): int
    {
        $hours  = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $query = User::whereNull('email_verified_at')
            ->where('created_at', '<=', now()->subHours($hours));

        $count = $query->count();

        if ($count === 0) {
            $this->info('Nenhum utilizador não verificado encontrado.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] {$count} utilizador(es) seriam apagados.");
            $query->get(['id', 'email', 'created_at'])
                ->each(fn ($u) => $this->line("  - #{$u->id} {$u->email} | registado em {$u->created_at}"));
            return Command::SUCCESS;
        }

        $query->delete();

        $this->info("Apagados {$count} utilizador(es) não verificados.");

        return Command::SUCCESS;
    }
}
