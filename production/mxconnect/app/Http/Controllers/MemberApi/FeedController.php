<?php

namespace App\Http\Controllers\MemberApi;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FeedPost;
use App\Services\FeedService;
use Illuminate\Http\Request;

/**
 * Member social feed API (CDC Phase 5). Every endpoint resolves the caller's
 * member from their Sanctum token within this mutual; a member can only act on
 * their own mutual's feed. New posts are broadcast live via Reverb.
 */
class FeedController extends Controller
{
    use ResolvesMember;

    public function __construct(private FeedService $feed) {}

    public function index(Request $request)
    {
        $member = $this->requireMember($request);

        return response()->json(['posts' => $this->feed->feed(30, $member->id)]);
    }

    public function store(Request $request)
    {
        $member = $this->requireMember($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $post = $this->feed->createPost($member, $data['body']);

        return response()->json(['id' => $post->id], 201);
    }

    public function like(Request $request, FeedPost $post)
    {
        $member = $this->requireMember($request);

        return response()->json($this->feed->toggleLike($member, $post));
    }

    public function comment(Request $request, FeedPost $post)
    {
        $member = $this->requireMember($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $comment = $this->feed->addComment($member, $post, $data['body']);

        return response()->json(['id' => $comment->id], 201);
    }
}
