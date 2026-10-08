<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Jarvis\IntentDetectionService;
use App\Services\Jarvis\WebsiteRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentDetectionTest extends TestCase
{
    use RefreshDatabase;

    private IntentDetectionService $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new IntentDetectionService(new WebsiteRegistryService());
    }

    // ── Greeting Tests ─────────────────────────────────────────────────────────

    public function test_detects_hello_jarvis(): void
    {
        $result = $this->detector->detect('hello jarvis');
        $this->assertEquals('greet', $result['intent']);
    }

    public function test_detects_hey_jarvis(): void
    {
        $result = $this->detector->detect('hey jarvis');
        $this->assertEquals('greet', $result['intent']);
    }

    public function test_detects_hindi_greeting(): void
    {
        $result = $this->detector->detect('jarvis namaste');
        $this->assertEquals('greet', $result['intent']);
    }

    // ── Website Open Tests ─────────────────────────────────────────────────────

    public function test_detects_open_instagram(): void
    {
        $result = $this->detector->detect('open instagram');
        $this->assertEquals('open_website', $result['intent']);
        $this->assertEquals('instagram', $result['website']);
        $this->assertFalse($result['new_tab']);
    }

    public function test_detects_instagram_open_karo(): void
    {
        $result = $this->detector->detect('instagram open karo');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('instagram', $result['website']);
    }

    public function test_detects_youtube_kholo(): void
    {
        $result = $this->detector->detect('youtube kholo');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('youtube', $result['website']);
        $this->assertEquals('https://www.youtube.com/', $result['url']);
    }

    public function test_detects_open_yutub_devanagari(): void
    {
        $result = $this->detector->detect('ओपन युटुब');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('youtube', $result['website']);
        $this->assertEquals('https://www.youtube.com/', $result['url']);
    }

    public function test_detects_whatsapp_hinglish(): void
    {
        $result = $this->detector->detect('whatsapp open karo');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('whatsapp', $result['website']);
    }

    public function test_detects_chatgpt(): void
    {
        $result = $this->detector->detect('chatgpt open karo');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('chatgpt', $result['website']);
    }

    public function test_detects_google_kholo(): void
    {
        $result = $this->detector->detect('google kholo');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('google', $result['website']);
    }

    public function test_detects_gemini(): void
    {
        $result = $this->detector->detect('gemini kholo');
        $this->assertStringContainsString('open_website', $result['intent']);
        $this->assertEquals('gemini', $result['website']);
    }

    // ── New Tab Tests ──────────────────────────────────────────────────────────

    public function test_detects_new_tab_intent(): void
    {
        $result = $this->detector->detect('instagram open karo new tab mein');
        $this->assertEquals('open_website_new_tab', $result['intent']);
        $this->assertTrue($result['new_tab']);
    }

    public function test_detects_new_tab_english(): void
    {
        $result = $this->detector->detect('open youtube in new tab');
        $this->assertTrue($result['new_tab']);
    }

    public function test_detects_naye_tab_mein(): void
    {
        $result = $this->detector->detect('youtube naye tab mein kholo');
        $this->assertTrue($result['new_tab']);
    }

    // ── Search Tests ───────────────────────────────────────────────────────────

    public function test_detects_google_search(): void
    {
        $result = $this->detector->detect('google par laravel tutorials search karo');
        $this->assertEquals('search_web', $result['intent']);
        $this->assertStringContainsString('laravel tutorials', $result['query']);
    }

    public function test_detects_youtube_search(): void
    {
        $result = $this->detector->detect('youtube par php tutorials search karo');
        $this->assertEquals('search_youtube', $result['intent']);
        $this->assertStringContainsString('php tutorials', $result['query']);
    }

    public function test_detects_search_for_phrase(): void
    {
        $result = $this->detector->detect('search for laravel interview questions');
        $this->assertEquals('search_web', $result['intent']);
        $this->assertStringContainsString('laravel interview questions', $result['query']);
    }

    // ── Navigation Tests ───────────────────────────────────────────────────────

    public function test_detects_go_back(): void
    {
        $result = $this->detector->detect('go back');
        $this->assertEquals('navigate_back', $result['intent']);
    }

    public function test_detects_back_jao(): void
    {
        $result = $this->detector->detect('back jao');
        $this->assertEquals('navigate_back', $result['intent']);
    }

    public function test_detects_refresh_page(): void
    {
        $result = $this->detector->detect('refresh page');
        $this->assertEquals('refresh_page', $result['intent']);
    }

    public function test_detects_page_refresh_karo(): void
    {
        $result = $this->detector->detect('page refresh karo');
        $this->assertEquals('refresh_page', $result['intent']);
    }

    // ── Control Tests ──────────────────────────────────────────────────────────

    public function test_detects_close_tab(): void
    {
        $result = $this->detector->detect('close this tab');
        $this->assertEquals('close_tab', $result['intent']);
    }

    public function test_detects_tab_band_karo(): void
    {
        $result = $this->detector->detect('tab band karo');
        $this->assertEquals('close_tab', $result['intent']);
    }

    public function test_detects_stop_listening(): void
    {
        $result = $this->detector->detect('stop listening');
        $this->assertEquals('stop_listening', $result['intent']);
    }

    public function test_detects_sunna_band_karo(): void
    {
        $result = $this->detector->detect('sunna band karo');
        $this->assertEquals('stop_listening', $result['intent']);
    }

    public function test_detects_help(): void
    {
        $result = $this->detector->detect('help me');
        $this->assertEquals('help', $result['intent']);
    }

    public function test_returns_unknown_for_gibberish(): void
    {
        $result = $this->detector->detect('xyzzy frobnicator blorp');
        $this->assertEquals('unknown', $result['intent']);
    }
}
