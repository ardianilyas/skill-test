<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-scheduled-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled posts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $publishedCount = Post::query()
            ->where('is_draft', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update(['is_draft' => false]);

        if ($publishedCount > 0) {
            $this->info("{$publishedCount} scheduled posts have been published.");
        } else {
            $this->info('No scheduled posts to publish.');
        }

        return self::SUCCESS;
    }
}
