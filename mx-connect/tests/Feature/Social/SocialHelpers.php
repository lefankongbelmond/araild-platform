<?php

use App\Enums\OrganizationManagerRole;
use App\Models\Central\Organization;
use App\Models\Central\PublicAccount;
use App\Services\Social\OrganizationService;
use App\Services\Social\PostService;

function socialAccount(string $first = 'Awa'): PublicAccount
{
    static $n = 0;
    $n++;

    return PublicAccount::create([
        'first_name' => $first,
        'last_name' => 'Test'.$n,
        'phone' => '+2376500'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
        'email' => strtolower($first).$n.'@example.test',
        'password' => 'secret-password',
    ])->refresh();
}

function socialOrg(PublicAccount $owner, array $attributes = []): Organization
{
    return app(OrganizationService::class)->create($owner, [
        'name' => 'Mutuelle Espoir Santé',
        'type' => 'mutual',
        'domains' => ['sante', 'protection_sociale'],
        'region' => 'Centre',
        'city' => 'Yaoundé',
        ...$attributes,
    ]);
}

function addManager(Organization $org, PublicAccount $account, OrganizationManagerRole $role): void
{
    $org->managers()->attach($account->getKey(), ['role' => $role->value]);
}

function orgPost(Organization $org, PublicAccount $author, array $data = [])
{
    return app(PostService::class)->publish($author, ['as' => (string) $org->id, 'type' => 'text', 'body' => 'Campagne de sensibilisation', ...$data]);
}
