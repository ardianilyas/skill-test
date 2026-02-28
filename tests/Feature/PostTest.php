<?php

namespace Tests\Feature;

use App\Console\Commands\PublishScheduledPosts;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    // ─── INDEX ────────────────────────────────────────────────────

    public function test_index_returns_only_published_posts_paginated_with_user_data(): void
    {
        Post::factory()->create(['is_draft' => true]);

        Post::factory()->create([
            'is_draft' => true,
            'published_at' => now()->addDay(),
        ]);

        Post::factory()->count(3)->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/posts');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    // ─── CREATE ───────────────────────────────────────────────────

    public function test_create_returns_string(): void
    {
        $response = $this->get('/posts/create');

        $response->assertOk();
        $response->assertSee('posts.create');
    }

    // ─── STORE ────────────────────────────────────────────────────

    public function test_guest_cannot_store_post(): void
    {
        $this->postJson('/posts', [])->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_post_as_draft(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'My Draft Post',
            'content' => 'This is a draft',
            'is_draft' => true,
            'published_at' => null,
        ];

        $response = $this->actingAs($user)->postJson('/posts', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('posts', [
            'title' => 'My Draft Post',
            'user_id' => $user->id,
            'is_draft' => true,
        ]);

        $response->assertJsonPath('data.title', 'My Draft Post');
        $response->assertJsonPath('data.author.id', $user->id);
    }

    public function test_authenticated_user_can_store_scheduled_post(): void
    {
        $user = User::factory()->create();
        $futureDate = now()->addDays(3)->format('Y-m-d H:i:s');

        $payload = [
            'title' => 'Scheduled Post',
            'content' => 'Will be published later',
            'is_draft' => false,
            'published_at' => $futureDate,
        ];

        $response = $this->actingAs($user)->postJson('/posts', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('posts', [
            'title' => 'Scheduled Post',
            'user_id' => $user->id,
            'is_draft' => false,
        ]);
    }

    public function test_store_fails_validation_with_invalid_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/posts', [
            'title' => '',
            'content' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_show_returns_404_for_draft_post(): void
    {
        $post = Post::factory()->create(['is_draft' => true]);

        $this->getJson("/posts/{$post->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_scheduled_post(): void
    {
        $post = Post::factory()->create([
            'is_draft' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->getJson("/posts/{$post->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_nonexistent_post(): void
    {
        $this->getJson('/posts/99999')->assertNotFound();
    }

    public function test_show_returns_post_with_user_data_when_published(): void
    {
        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson("/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.author.id', $post->user_id);
    }

    // ─── EDIT ─────────────────────────────────────────────────────

    public function test_edit_returns_string(): void
    {
        $post = Post::factory()->create();

        $response = $this->get("/posts/{$post->id}/edit");

        $response->assertOk();
        $response->assertSee('posts.edit');
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_guest_cannot_update_post(): void
    {
        $post = Post::factory()->create();

        $this->putJson("/posts/{$post->id}", ['title' => 'Hacked'])
            ->assertUnauthorized();
    }

    public function test_author_can_update_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['title' => 'Old Title']);

        $this->actingAs($user)
            ->putJson("/posts/{$post->id}", ['title' => 'New Title'])
            ->assertOk();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'New Title',
        ]);
    }

    public function test_non_author_cannot_update_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->putJson("/posts/{$post->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_update_fails_validation(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson("/posts/{$post->id}", ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    // ─── DESTROY ──────────────────────────────────────────────────

    public function test_guest_cannot_delete_post(): void
    {
        $post = Post::factory()->create();

        $this->deleteJson("/posts/{$post->id}")
            ->assertUnauthorized();
    }

    public function test_author_can_delete_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/posts/{$post->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_non_author_cannot_delete_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/posts/{$post->id}")
            ->assertForbidden();
    }

    // ─── SCHEDULED PUBLISHING COMMAND ─────────────────────────────

    public function test_publish_scheduled_posts_command(): void
    {
        $duePost = Post::factory()->create([
            'is_draft' => true,
            'published_at' => now()->subHour(),
        ]);

        $futurePost = Post::factory()->create([
            'is_draft' => true,
            'published_at' => now()->addDay(),
        ]);

        $draftPost = Post::factory()->create([
            'is_draft' => true,
            'published_at' => null,
        ]);

        $this->artisan(PublishScheduledPosts::class)
            ->assertSuccessful();

        $this->assertFalse($duePost->fresh()->is_draft);
        $this->assertTrue($futurePost->fresh()->is_draft);
        $this->assertTrue($draftPost->fresh()->is_draft);
    }
}
