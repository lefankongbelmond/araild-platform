<?php

namespace App\Models\Central;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Grand-public identity/login (PWA members). A person may belong to several mutuals
 * via member_links; this is their single cross-mutual account.
 */
class PublicAccount extends Authenticatable implements CanResetPasswordContract
{
    use HasApiTokens, CanResetPassword;

    protected $connection = 'central';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'password' => 'hashed', 'phone_verified_at' => 'datetime',
        'interests' => 'array', 'is_demo' => 'boolean', 'email_notifications' => 'boolean', 'suspended_at' => 'datetime',
    ];

    public function links() { return $this->hasMany(MemberLink::class); }

    /** Lien « mot de passe oublié » : e-mail MX-CONNECT envoyé immédiatement (pas de file d'attente). */
    public function sendPasswordResetNotification($token): void
    {
        $url = rtrim((string) config('app.url'), '/') . '/compte/reinitialiser/' . $token . '?email=' . urlencode($this->email);
        app(\App\Services\Social\MailService::class)->send($this->email, new \App\Mail\SocialMail(
            'Réinitialisation de votre mot de passe MX-CONNECT', 'Réinitialiser votre mot de passe',
            ["Bonjour {$this->first_name},", 'Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable 60 minutes.'],
            'Choisir un nouveau mot de passe', $url,
            'Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.',
        ));
    }

    // --- Réseau social ---
    public function follows() { return $this->hasMany(\App\Models\Social\Follow::class, 'public_account_id'); }
    public function mutes()   { return $this->hasMany(\App\Models\Social\Mute::class, 'public_account_id'); }
    public function posts()   { return $this->hasMany(\App\Models\Social\Post::class, 'public_account_id'); }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function avatarUrl(): ?string
    {
        return \App\Services\Social\MediaService::url($this->avatar_path);
    }

    /** Compte suspendu par la modération (tolère un modèle chargé sans cette colonne). */
    public function isSuspended(): bool
    {
        return ! empty($this->getAttributes()['suspended_at'] ?? null);
    }

    public function coverUrl(): ?string
    {
        return \App\Services\Social\MediaService::url($this->cover_path);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr($this->last_name, 0, 1));
    }
}
