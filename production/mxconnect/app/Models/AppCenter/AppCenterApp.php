<?php

namespace App\Models\AppCenter;

use Illuminate\Database\Eloquent\Model;

/** Application de l'App Center (mutuelle, social) : textes, version, guides, APK signé. */
class AppCenterApp extends Model
{
    protected $connection = 'central';
    protected $table = 'app_center_apps';
    protected $guarded = ['id'];
    protected $casts = [
        'enabled' => 'boolean',
        'version_auto' => 'boolean',
        'features' => 'array',
        'released_on' => 'date',
        'apk_uploaded_at' => 'datetime',
    ];

    public function hasApk(): bool
    {
        return (bool) $this->apk_path && (bool) $this->apk_signer;
    }
}
