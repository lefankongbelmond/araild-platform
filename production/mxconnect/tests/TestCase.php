<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Comme sur LWS : la racine web est le dossier parent de « mxconnect ».
        $app->usePublicPath(dirname(__DIR__, 2));

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Le client de test annonce par défaut un navigateur anglophone (Accept-Language: en-us) :
        // les tests sont écrits en français, comme la langue source (multilingue M1, 2026-11-27).
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');
    }
}
