<?php

namespace App\Models\Social;

use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $connection = 'central';
    protected $table = 'social_reports';
    protected $guarded = [];
    protected $casts = ['handled_at' => 'datetime'];

    public const REASONS = [
        'spam'          => 'Spam ou publicité abusive',
        'fausse_info'   => 'Fausse information de santé',
        'haine'         => 'Propos haineux ou harcèlement',
        'donnees'       => 'Données personnelles exposées',
        'inapproprie'   => 'Contenu inapproprié',
        'autre'         => 'Autre',
    ];

    public function reporter() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function post()     { return $this->belongsTo(Post::class, 'post_id')->withTrashed(); }
    public function comment()  { return $this->belongsTo(Comment::class, 'comment_id')->withTrashed(); }
    public function reportedAccount() { return $this->belongsTo(PublicAccount::class, 'reported_account_id'); }
    public function reportedMutual()  { return $this->belongsTo(\App\Models\Central\Mutual::class, 'reported_mutual_id'); }

    /** Nature de l'élément signalé. */
    public function targetLabel(): string
    {
        return match (true) {
            (bool) $this->comment_id          => 'Commentaire',
            (bool) $this->post_id             => 'Publication',
            (bool) $this->reported_account_id => 'Profil',
            (bool) $this->reported_mutual_id  => 'Page',
            default                           => 'Contenu',
        };
    }
}
