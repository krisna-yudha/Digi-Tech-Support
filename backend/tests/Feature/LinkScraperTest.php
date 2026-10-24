<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_scrape_link_icon(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://example.com' => Http::response(
                '<html><head><title>Example Domain</title><link rel="icon" href="/favicon.png"></head><body>Hello</body></html>',
                200
            ),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/scrape-link-icon?url=https://example.com');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.domain', 'example.com')
            ->assertJsonPath('data.title', 'Example Domain')
            ->assertJsonPath('data.icon_url', 'https://example.com/favicon.png');
    }

    public function test_link_scraper_falls_back_to_google_favicon_when_unreachable(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://unreachable-domain-xyz.com' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/scrape-link-icon?url=https://unreachable-domain-xyz.com');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.domain', 'unreachable-domain-xyz.com');

        $this->assertStringContainsString('google.com/s2/favicons', $response->json('data.icon_url'));
    }
}
