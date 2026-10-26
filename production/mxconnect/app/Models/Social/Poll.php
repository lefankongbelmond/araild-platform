<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Sondage attaché à une publication : la question est le texte de la publication. Résultats anonymes. */
class Poll extends Model
{
    protected $connection = 'central';
    protected $table = 'social_polls';
    protected $guarded = [];
    protected $casts = ['closes_at' => 'datetime'];

    public const DURATIONS = [1 => '1 jour', 3 => '3 jours', 7 => '1 semaine', 30 => '1 mois'];

    public function post()    { return $this->belongsTo(Post::class, 'post_id'); }
    public function options() { return $this->hasMany(PollOption::class, 'poll_id')->orderBy('position'); }

    public function isOpen(): bool { return ! $this->closes_at || $this->closes_at->isFuture(); }

    public function percent(PollOption $o): int
    {
        return $this->votes_count > 0 ? (int) round($o->votes_count / $this->votes_count * 100) : 0;
    }
}
