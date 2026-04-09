<?php

namespace Tests\Acceptance;

use App\Models\User;
use App\Services\MessageTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification point: "Typ-Erweiterung: Neuen Typ in config/herold.php eintragen,
 * pruefen ob UI + Processing funktioniert"
 *
 * NFR-14a-01: Config-Driven Message Types
 * NFR-15b-02: No Preprocessing Prompts in API Responses
 */
class TypeExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'api_key_hash' => hash('sha256', 'test-api-key-for-testing'),
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_confirmed_at' => now(),
        ]);
    }

    public function test_default_types_are_available(): void
    {
        $registry = app(MessageTypeRegistry::class);

        $keys = $registry->keys();

        $this->assertContains('general', $keys);
        $this->assertContains('youtube', $keys);
        $this->assertContains('diary', $keys);
    }

    public function test_types_endpoint_returns_all_types_without_prompts(): void
    {
        $response = $this->actingAs($this->user)->getJson('/types');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertArrayHasKey('general', $data);
        $this->assertArrayHasKey('youtube', $data);
        $this->assertArrayHasKey('diary', $data);

        // NFR-15b-02: No preprocessing prompts in response
        foreach ($data as $type) {
            $this->assertArrayNotHasKey('preprocessing_prompt', $type);
        }
    }

    public function test_types_endpoint_contains_required_fields(): void
    {
        $response = $this->actingAs($this->user)->getJson('/types');

        foreach ($response->json() as $type) {
            $this->assertArrayHasKey('label', $type);
            $this->assertArrayHasKey('icon', $type);
            $this->assertArrayHasKey('extra_fields', $type);
            $this->assertArrayHasKey('github_label', $type);
        }
    }

    public function test_youtube_type_has_url_extra_field(): void
    {
        $response = $this->actingAs($this->user)->getJson('/types');

        $youtube = $response->json('youtube');
        $this->assertNotEmpty($youtube['extra_fields']);

        $urlField = collect($youtube['extra_fields'])->firstWhere('name', 'youtube_url');
        $this->assertNotNull($urlField);
        $this->assertEquals('url', $urlField['type']);
        $this->assertTrue($urlField['required']);
    }

    public function test_new_type_can_be_added_via_config(): void
    {
        config(['herold.types.custom_test' => [
            'label' => 'Custom Test',
            'icon' => 'mdi-test-tube',
            'github_label' => 'type:custom-test',
            'extra_fields' => [
                ['name' => 'priority', 'type' => 'text', 'required' => false, 'label' => 'Priority'],
            ],
            'preprocessing_prompt' => 'Process this custom type.',
        ]]);

        $registry = app(MessageTypeRegistry::class);

        $this->assertContains('custom_test', $registry->keys());

        $type = $registry->get('custom_test');
        $this->assertEquals('Custom Test', $type['label']);

        // Verify it appears in frontend-safe list
        $all = $registry->all();
        $this->assertArrayHasKey('custom_test', $all);
        $this->assertArrayNotHasKey('preprocessing_prompt', $all['custom_test']);
    }

    public function test_recording_page_loads_types(): void
    {
        $response = $this->actingAs($this->user)->get('/notes/create');

        $response->assertStatus(200);
    }
}
