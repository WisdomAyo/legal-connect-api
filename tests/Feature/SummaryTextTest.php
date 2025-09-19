<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SummaryTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_text_with_defaults(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $text = str_repeat('This is a sentence. ', 10);

        $response = $this->postJson(route('summaries.text'), [
            'text' => $text,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'timeframe',
                    'metrics',
                    'highlights',
                    'risks',
                    'anomalies',
                    'nextActions',
                    'generatedAt',
                ],
            ]);

        $highlights = $response->json('data.highlights');
        $this->assertIsArray($highlights);
        $this->assertCount(5, $highlights, 'Default should return first 5 sentences.');
    }

    public function test_it_validates_short_input(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(route('summaries.text'), [
            'text' => 'Too short.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }
}
