<?php

namespace App\Tenancy;

use Illuminate\Contracts\Foundation\Application;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Isolation du cache par mutuelle SANS tags (cache "file" ou "database",
 * typiques de l'hébergement mutualisé où Redis n'existe pas).
 *
 *  - stores "database"/"redis"… : préfixe de clé propre à la mutuelle ;
 *  - stores "file"              : dossier propre à la mutuelle.
 *
 * Le gestionnaire de cache est réinitialisé à chaque bascule pour que les
 * stores soient reconstruits avec la nouvelle configuration.
 */
class PrefixCacheTenancyBootstrapper implements TenancyBootstrapper
{
    protected ?array $original = null;

    public function __construct(protected Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        $config = $this->app['config'];
        $this->original ??= [
            'prefix' => $config->get('cache.prefix'),
            'stores' => $config->get('cache.stores'),
        ];

        $id = $tenant->getTenantKey();
        $config->set('cache.prefix', $this->original['prefix'] . 'tenant_' . $id . '_');

        foreach ($this->original['stores'] as $name => $store) {
            if (($store['driver'] ?? null) === 'file') {
                $config->set("cache.stores.$name.path", rtrim($store['path'], '/') . '/tenant_' . $id);
            }
            if (isset($store['prefix'])) {
                $config->set("cache.stores.$name.prefix", $store['prefix'] . 'tenant_' . $id . '_');
            }
        }

        $this->reset();
    }

    public function revert(): void
    {
        if ($this->original !== null) {
            $this->app['config']->set('cache.prefix', $this->original['prefix']);
            $this->app['config']->set('cache.stores', $this->original['stores']);
        }

        $this->reset();
    }

    protected function reset(): void
    {
        $manager = $this->app['cache'];
        foreach (array_keys($this->app['config']->get('cache.stores', [])) as $name) {
            $manager->forgetDriver($name);
        }
        $this->app->forgetInstance('cache.store');
        \Illuminate\Support\Facades\Cache::clearResolvedInstances();
    }
}
