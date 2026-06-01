<?php

namespace App\Console\Commands;

use App\Models\UserPunishment;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class ExpirePunishments extends Command
{
    protected $signature = 'punishments:expire
                            {--dry-run : Mostrar quantas expirariam sem aplicar}';

    protected $description = 'Expira automaticamente punições com ends_at no passado';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $expired = UserPunishment::whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereNull('revoked_at')
            ->with('user')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Nenhuma punição expirada encontrada.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] {$expired->count()} punição(ões) expiradas seriam processadas.");
            $expired->each(fn ($p) => $this->line(
                "  - #{$p->id} {$p->type} | user_id={$p->user_id} | ends_at={$p->ends_at}"
            ));
            return Command::SUCCESS;
        }

        $roleRestored = 0;

        foreach ($expired as $punishment) {
            $punishment->update([
                'revoked_at'     => now(),
                'revoked_reason' => 'Expirado automaticamente.',
            ]);

            if (in_array($punishment->type, ['ban', 'suspension']) && $punishment->user) {
                // Only restore role if no other active ban/suspension remains
                $stillActive = UserPunishment::where('user_id', $punishment->user_id)
                    ->whereIn('type', ['ban', 'suspension'])
                    ->whereNull('revoked_at')
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->exists();

                if (! $stillActive) {
                    $punishment->user->update(['role' => 'user']);
                    $roleRestored++;
                }
            }

            AuditLogger::log(
                'user.punish.auto_expired',
                'moderation',
                $punishment->user,
                [
                    'punishment_id' => $punishment->id,
                    'type'          => $punishment->type,
                    'ended_at'      => $punishment->ends_at->toDateTimeString(),
                ]
            );
        }

        $this->info("Expiradas: {$expired->count()} punição(ões). Papéis restaurados: {$roleRestored}.");

        return Command::SUCCESS;
    }
}
