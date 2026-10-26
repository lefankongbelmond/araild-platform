<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Mutual;
use App\Models\Social\Conversation;
use App\Services\Social\FeedService;
use App\Services\Social\MessagingService;
use Illuminate\Http\Request;

/** Messagerie privée du membre avec les mutuelles. */
class MessageController extends SocialController
{
    public function __construct(FeedService $feed, private MessagingService $messaging)
    {
        parent::__construct($feed);
    }

    public function index()
    {
        $conversations = Conversation::where('public_account_id', $this->viewer()->id)
            ->with(['mutual.profile', 'lastMessage'])->orderByDesc('last_message_at')->paginate(30);

        return view('social.messages.index', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        $this->own($conversation);
        $this->messaging->markReadByAccount($conversation);
        $conversation->load(['mutual.profile', 'messages', 'adhesion']);

        return view('social.messages.show', ['c' => $conversation]);
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $this->own($conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $this->messaging->postFromAccount($conversation, $data['body']);

        return redirect()->to(route('social.messages.show', $conversation) . '#fin');
    }

    /** Nouvelle conversation depuis la page d'une mutuelle. */
    public function start(Request $request, string $slug)
    {
        $mutual = Mutual::where('slug', $slug)->with('profile')->firstOrFail();
        abort_if($mutual->status === 'suspended', 404);
        abort_if($mutual->profile?->owner_account_id, 403, 'La messagerie privée est réservée aux mutuelles gérées sur MX-CONNECT.');
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body'    => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        $c = $this->messaging->start($mutual, $this->viewer(), $data['subject'], $data['body']);

        return redirect()->route('social.messages.show', $c)->with('ok', "Message envoyé à {$mutual->name}.");
    }

    private function own(Conversation $c): void
    {
        abort_unless($c->public_account_id === $this->viewer()->id, 404);
    }
}
