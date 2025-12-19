<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_published_posts_paginated_with_user_data()
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
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_cannot_store_post()
    {
        $this->postJson('/posts', [])->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_post_as_draft()
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

        $response->assertJsonPath('title', 'My Draft Post');
        $response->assertJsonPath('author.id', $user->id);
    }

    public function test_store_fails_validation_with_invalid_data()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/posts', [
            'title' => '',
            'content' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_show_returns_404_for_draft_post()
    {
        $post = Post::factory()->create(['is_draft' => true]);

        $this->getJson("/posts/{$post->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_scheduled_post()
    {
        $post = Post::factory()->create([
            'is_draft' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->getJson("/posts/{$post->id}")->assertNotFound();
    }

    public function test_show_returns_post_with_user_data_when_published()
    {
        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson("/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('author.id', $post->user_id);
    }

    public function test_author_can_update_own_post()
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

    public function test_non_author_cannot_update_post()
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->putJson("/posts/{$post->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_update_fails_validation()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson("/posts/{$post->id}", ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_author_can_delete_own_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/posts/{$post->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_non_author_cannot_delete_post()
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/posts/{$post->id}")
            ->assertForbidden();
    }
}
