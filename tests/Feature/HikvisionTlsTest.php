<?php

namespace Tests\Feature;

use App\Services\HikvisionFaceIdClient;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class HikvisionTlsTest extends TestCase
{
    public function test_legacy_certificate_requires_an_explicit_valid_pin(): void
    {
        $pin = 'sha256//'.base64_encode(hash('sha256', 'test-key', true));
        config(['hikvision.pinned_public_key' => $pin]);
        $options = (new ReflectionMethod(HikvisionFaceIdClient::class, 'tlsOptions'))
            ->invoke(new HikvisionFaceIdClient);

        $this->assertFalse($options['verify']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame($pin, $options['curl'][CURLOPT_PINNEDPUBLICKEY]);
    }

    public function test_invalid_pin_fails_closed(): void
    {
        config(['hikvision.pinned_public_key' => 'invalid']);
        $this->expectException(RuntimeException::class);
        (new ReflectionMethod(HikvisionFaceIdClient::class, 'tlsOptions'))
            ->invoke(new HikvisionFaceIdClient);
    }

    public function test_production_without_pin_keeps_certificate_verification(): void
    {
        $this->app['env'] = 'production';
        config(['hikvision.pinned_public_key' => null, 'hikvision.ca_bundle' => null, 'hikvision.verify_tls' => false]);
        $options = (new ReflectionMethod(HikvisionFaceIdClient::class, 'tlsOptions'))
            ->invoke(new HikvisionFaceIdClient);

        $this->assertTrue($options['verify']);
    }
}
