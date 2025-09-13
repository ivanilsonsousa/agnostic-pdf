<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
  protected function getPackageProviders($app)
  {
    return [
      \AgnosticPDF\AgnosticPDFServiceProvider::class,
    ];
  }

  protected function defineEnvironment($app): void
  {
    $app['config']->set('app.key', 'base64:c3VwZXItc2VjcmV0LWZvci10ZXN0aW5nLWFnbm9zdGljcGRm');
    $app['config']->set('app.timezone', 'UTC');
  }

  protected function setUp(): void
  {
    parent::setUp();

    $fixturesViews = __DIR__ . '/Fixtures/views';

    if (is_dir($fixturesViews)) {
      View::addLocation($fixturesViews);
    }
  }

  protected function outputDir(): string
  {
    $dir = __DIR__ . '/Output';

    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }

    return is_string(realpath($dir)) ? realpath($dir) : $dir;
  }

  protected function tempDir(): string
  {
    $dir = sys_get_temp_dir() . '/agnostic-pdf-tests';

    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }

    return $dir;
  }
}
