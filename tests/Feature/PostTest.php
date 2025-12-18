<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_active_posts()
    {
        Post::factory()->create(['is_draft' => true]);
        Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/posts');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_store_post()
    {
        $this->postJson('/posts', [])->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_post()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/posts', [
            'title' => 'Test Post',
            'content' => 'Test Content',
            'is_draft' => true,
        ])->assertCreated();
    }

    public function test_show_returns_404_for_draft()
    {
        $post = Post::factory()->create(['is_draft' => true]);

        $this->getJson("/posts/{$post->id}")->assertNotFound();
    }

    public function test_show_returns_200_for_published()
    {
        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson("/posts/{$post->id}")->assertOk();
    }

    public function test_author_can_update_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)->putJson("/posts/{$post->id}", [
            'title' => 'Updated Title',
        ])->assertOk();
    }

    public function test_non_author_cannot_update_post()
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())->putJson("/posts/{$post->id}", [
            'title' => 'Updated Title',
        ])->assertForbidden();
    }

    public function test_author_can_delete_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson("/posts/{$post->id}")->assertNoContent();
    }

    public function test_non_author_cannot_delete_post()
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())->deleteJson("/posts/{$post->id}")->assertForbidden();
    }
}
