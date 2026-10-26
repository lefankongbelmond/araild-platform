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
}
