<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubscriberNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create());
    }

    public function test_creation_uses_highest_remaining_id_instead_of_deleted_high_id(): void
    {
        DB::table('subscribers')->insert([
            ['id' => 7, 'name' => 'Existing', 'email' => 'existing@example.com'],
            ['id' => 90000, 'name' => 'Old import', 'email' => 'old@example.com'],
        ]);
        DB::table('subscribers')->where('id', 90000)->delete();

        $this->postJson('/api/subscribers', $this->details('first'))
            ->assertOk()->assertJsonPath('subscriber.id', 8);
        $this->postJson('/api/subscribers', $this->details('second'))
            ->assertOk()->assertJsonPath('subscriber.id', 9);
        $this->assertDatabaseHas('subscribers', ['id' => 7, 'name' => 'Existing']);
    }

    public function test_creation_starts_at_one_after_all_subscribers_are_deleted(): void
    {
        DB::table('subscribers')->insert([
            'id' => 90000, 'name' => 'Old import', 'email' => 'old@example.com',
        ]);
        DB::table('subscribers')->delete();

        $this->postJson('/api/subscribers', $this->details('first'))
            ->assertOk()->assertJsonPath('subscriber.id', 1);
    }

    public function test_deleting_a_middle_id_does_not_change_the_next_number(): void
    {
        foreach (['first', 'second', 'third'] as $name) {
            $this->postJson('/api/subscribers', $this->details($name))->assertOk();
        }

        $this->deleteJson('/api/subscribers/2')->assertOk();
        $this->postJson('/api/subscribers', $this->details('fourth') + ['id' => 99])
            ->assertOk()->assertJsonPath('subscriber.id', 4);
    }

    private function details(string $name): array
    {
        return ['name' => $name, 'email' => $name.'@example.com'];
    }
}
