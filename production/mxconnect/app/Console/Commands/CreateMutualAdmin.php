<?php

namespace App\Console\Commands;

use App\Models\Central\Mutual;
use Illuminate\Console\Command;

/**
 * Crée (ou réactive) un utilisateur du back-office d'une mutuelle, dans SA base.
 *
 *   php artisan mxconnect:mutual-user together admin@together.cm --role=mutual_admin
 *
 * <mutual> accepte l'id (mut_01) ou le slug (together). Sans --password, un mot
 * de passe aléatoire est généré et affiché une seule fois.
 */
class CreateMutualAdmin extends Command
{
    protected $signature = 'mxconnect:mutual-user
        {mutual : id (mut_01) ou slug (together)}
        {email}
        {--first=Admin} {--last=Mutuelle}
        {--role=mutual_admin}
        {--password= : mot de passe (sinon généré)}';

    protected $description = "Crée un utilisateur (par défaut l'administrateur) dans la base d'une mutuelle.";

    public function handle(): int
    {
        $key = $this->argument('mutual');
        $mutual = Mutual::find($key) ?? Mutual::where('slug', $key)->first();
        if (! $mutual) {
            $this->error("Mutuelle introuvable : {$key}");
            return self::FAILURE;
        }

        $role = $this->option('role');
        $email = strtolower($this->argument('email'));
        $password = app(\App\Services\MutualProvisioningService::class)->createUser(
            $mutual, $email, $this->option('first'), $this->option('last'), $role, $this->option('password') ?: null
        );

        $domain = optional($mutual->domains()->first())->domain;
        $this->info("Utilisateur prêt dans {$mutual->id} ({$mutual->name}).");
        $this->line("  URL      : https://{$domain}/connexion");
        $this->line("  E-mail   : {$email}");
        $this->line("  Rôle     : {$role}");
        if (! $this->option('password')) {
            $this->warn("  Mot de passe (affiché une seule fois) : {$password}");
        }
        $this->line('  La MFA (TOTP) sera demandée à la première connexion pour les rôles sensibles.');

        return self::SUCCESS;
    }
}
