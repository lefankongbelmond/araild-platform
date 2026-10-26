<?php

namespace App\Events;

use App\Models\Tenant\FeedPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a member publishes a post, so every connected member of the same
 * mutual sees it live (CDC Phase 5). Scoped to the tenant's private feed channel.
 */
class FeedPostCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FeedPost $post, public string $tenantId, public string $authorName) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.feed");
    }

    public function broadcastAs(): string
    {
        return 'feed.post.created';
    }

    /** No medical or sensitive data — display fields only. */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->post->id,
            'author'     => $this->authorName,
            'body'       => $this->post->body,
            'created_at' => $this->post->created_at?->toIso8601String(),
        ];
    }
}
