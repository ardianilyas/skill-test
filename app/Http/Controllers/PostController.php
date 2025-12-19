<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostCollection;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::query()->active()->with('user')->latest('published_at')->paginate(20);

        return response()->json(new PostCollection($posts));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return 'posts.create';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {
        $post = $request->user()->posts()->create($request->all());

        return response()->json(new PostResource($post->load('user')), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        if ($post->is_draft || ($post->published_at && $post->published_at->isFuture())) {
            abort(404);
        }

        return response()->json(new PostResource($post->load('user')));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        return 'posts.edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        Gate::authorize('update', $post);

        $post->update($request->all());

        return response()->json(new PostResource($post->load('user')), Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}
