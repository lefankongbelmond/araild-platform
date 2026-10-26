<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    protected $connection = 'central';
    protected $table = 'social_post_media';
    protected $guarded = [];

    public function url(): string      { return \App\Services\Social\MediaService::url($this->path); }
    public function thumbUrl(): string { return \App\Services\Social\MediaService::url($this->thumb_path ?: $this->path); }

    public function isVideo(): bool { return $this->kind === 'video'; }

    /** Affiche (image fixe) d'une vidéo, si elle existe. */
    public function posterUrl(): ?string { return $this->thumb_path ? \App\Services\Social\MediaService::url($this->thumb_path) : null; }

    /** Durée lisible (1:05). */
    public function durationLabel(): ?string
    {
        return $this->duration ? intdiv($this->duration, 60) . ':' . str_pad((string) ($this->duration % 60), 2, '0', STR_PAD_LEFT) : null;
    }
}
