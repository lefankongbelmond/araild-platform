<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Actualité d'avancement d'un projet (journal public des résultats). */
class ProjectUpdate extends Model
{
    protected $connection = 'central';
    protected $table = 'social_project_updates';
    protected $guarded = [];

    public function project() { return $this->belongsTo(Project::class, 'project_id'); }
}
