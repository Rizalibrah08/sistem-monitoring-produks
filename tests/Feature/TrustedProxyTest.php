<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    /**
     * Caddy menghentikan HTTPS lalu meneruskan permintaan ke Nginx lewat HTTP,
     * sehingga URL absolut yang dibangun aplikasi harus mengikuti header X-Forwarded-*.
     */
    public function test_absolute_urls_keep_the_https_address_forwarded_by_the_reverse_proxy(): void
    {
        $location = $this->get('/dashboard', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'test.aisha.example',
            'X-Forwarded-Port' => '443',
        ])->assertRedirect()->headers->get('Location');

        $this->assertSame('https://test.aisha.example/login', $location);
    }

    public function test_direct_requests_without_forwarded_headers_keep_their_own_address(): void
    {
        $location = $this->get('/dashboard')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('http://', $location);
        $this->assertStringEndsWith('/login', $location);
        $this->assertStringNotContainsString('test.aisha.example', $location);
    }
}
