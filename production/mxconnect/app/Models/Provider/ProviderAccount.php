<?php

namespace App\Models\Provider;

use App\Models\Central\CareProvider;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Personne de l'espace prestataire central (garde « provider », base CENTRALE).
 * Un compte peut appartenir à plusieurs établissements (provider_memberships), avec un
 * rôle propre à chacun. Double authentification obligatoire (comme tout le personnel).
 */
class ProviderAccount extends Authenticatable implements Auditable, CanResetPasswordContract
{
    use AuditableTrait, CanResetPassword;

    protected $connection = 'central';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token', 'mfa_secret'];
    protected $auditExclude = ['password', 'remember_token', 'mfa_secret'];
    protected $casts = [
        'password' => 'hashed', 'mfa_secret' => 'encrypted', 'mfa_enabled' => 'boolean',
        'must_change_password' => 'boolean', 'active' => 'boolean', 'last_login_at' => 'datetime',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function memberships()
    {
        return $this->hasMany(ProviderMembership::class);
    }

    /** Rattachements actifs à des établissements actifs. */
    public function activeMemberships()
    {
        return $this->memberships()->where('active', true)
            ->whereIn('care_provider_id', CareProvider::on('central')->where('active', true)->select('id'));
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = rtrim((string) config('app.url'), '/') . '/espace-prestataire/reinitialiser/' . $token . '?email=' . urlencode($this->email);
        app(\App\Services\Social\MailService::class)->send($this->email, new \App\Mail\SocialMail(
            'Réinitialisation de votre mot de passe — espace prestataire MX-CONNECT', 'Réinitialiser votre mot de passe',
            ["Bonjour {$this->first_name},", 'Vous avez demandé à réinitialiser le mot de passe de votre espace prestataire. Ce lien est valable 60 minutes.'],
            'Choisir un nouveau mot de passe', $url,
            'Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.',
        ));
    }
}
