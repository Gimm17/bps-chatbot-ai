<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_resolver_accepts_absolute_ca_bundle_path(): void
    {
        $ca = tempnam(sys_get_temp_dir(), 'ca-');
        file_put_contents($ca, 'certificate');

        $this->assertSame($ca, AppServiceProvider::resolveCaPath($this->app, $ca));

        unlink($ca);
    }

    public function test_resolver_accepts_relative_ca_bundle_path(): void
    {
        $relative = 'storage/app/ca/test-resolver.pem';
        $absolute = $this->app->basePath($relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }
        file_put_contents($absolute, 'certificate');

        $this->assertSame(realpath($absolute), AppServiceProvider::resolveCaPath($this->app, $relative));

        unlink($absolute);
    }
}
