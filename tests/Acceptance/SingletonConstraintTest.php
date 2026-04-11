<?php

namespace Tests\Acceptance;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification point: "Single-user system — DB enforces exactly one user row"
 */
class SingletonConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_second_user_insert_is_rejected_by_database(): void
    {
        User::factory()->create();

        $this->expectException(QueryException::class);

        User::factory()->create();
    }

    public function test_user_can_be_updated_without_constraint_violation(): void
    {
        $user = User::factory()->create(['name' => 'Herold']);

        $user->update(['email' => 'updated@example.com']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_user_can_be_deleted_and_recreated(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertDatabaseCount('users', 0);

        $newUser = User::factory()->create();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $newUser->id]);
    }
}
