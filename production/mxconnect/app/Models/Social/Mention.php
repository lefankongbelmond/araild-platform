<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

/** Mention (@) d'un membre ou d'une page dans une publication ou un commentaire. */
class Mention extends Model
{
    protected $connection = 'central';
    protected $table = 'social_mentions';
    protected $guarded = [];

    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }

    /** Adresse de la personne ou de la page mentionnée. */
    public function url(): ?string
    {
        if ($this->mutual_id) {
            return $this->mutual ? route('social.mutual', $this->mutual->slug) : null;
        }

        return $this->public_account_id ? route('social.user', $this->public_account_id) : null;
    }
}
