<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class AdhesionDocument extends Model
{
    protected $connection = 'central';
    protected $guarded = [];

    public function request() { return $this->belongsTo(AdhesionRequest::class, 'adhesion_request_id'); }
    public function kindLabel(): string { return AdhesionRequest::DOC_KINDS[$this->kind] ?? 'Document'; }
}
