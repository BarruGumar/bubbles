<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignSiteOwner extends Command
{
    protected $signature = 'site:owner {email : Email do utilizador a tornar Dono do Site}';

    protected $description = 'Atribui o cargo de site_owner a um utilizador pelo seu email';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Utilizador com email '{$email}' não encontrado.");
            return Command::FAILURE;
        }

        if ($user->isSiteOwner()) {
            $this->warn("{$user->name} já é Dono do Site.");
            return Command::SUCCESS;
        }

        $old = $user->role;
        $user->update(['role' => 'site_owner']);

        $this->info("✓ {$user->name} ({$email}) promovido de '{$old}' para 'site_owner'.");
        $this->line('  Para revogar: php artisan site:owner:revoke '.$email);

        return Command::SUCCESS;
    }
}
