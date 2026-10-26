<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * Document PDF PUBLIC d'une page (joint à une publication ou à un projet) :
 * rapport d'activité, affiche, compte rendu… Jamais de donnée médicale ou personnelle.
 */
class Document extends Model
{
    protected $connection = 'central';
    protected $table = 'social_documents';
    protected $guarded = [];

    public function post()    { return $this->belongsTo(Post::class, 'post_id'); }
    public function project() { return $this->belongsTo(Project::class, 'project_id'); }

    /** Téléchargement (compté), par la route dédiée. */
    public function url(): string { return route('social.document', $this->id); }

    /** Taille lisible : « 850 Ko », « 2,4 Mo ». */
    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1, ',', ' ') . ' Mo' : max(1, (int) round($this->size / 1024)) . ' Ko';
    }
}
