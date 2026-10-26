<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Envoi d'une vidéo par morceaux (fichier temporaire, privé, jamais servi tel quel). */
class Upload extends Model
{
    protected $connection = 'central';
    protected $table = 'social_uploads';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    public function tempPath(): string
    {
        return storage_path('app/uploads-tmp/' . $this->id . '.part');
    }
}
