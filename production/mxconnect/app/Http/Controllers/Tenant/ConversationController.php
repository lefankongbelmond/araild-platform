<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Mutual;
use App\Models\Social\Conversation;
use App\Services\Social\MessagingService;
use Illuminate\Http\Request;

/** Messagerie de la mutuelle avec les membres du réseau (une mutuelle ne voit que ses conversations). */
class ConversationController extends Controller
{
    public const ROLES = ['mutual_admin', 'community_manager', 'enrollment_agent'];

    public function __construct(private MessagingService $messaging) {}

    public function index(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $filter = $request->get('filtre');

        return view('tenant.conversations.index', [
            'items' => Conversation::where('mutual_id', $mutual->id)
                ->when($filter === 'non-lus', fn ($q) => $q->where('mutual_unread', '>', 0))
                ->when($filter === 'clos', fn ($q) => $q->where('status', 'closed'), fn ($q) => $filter === 'non-lus' ? $q : $q->where('status', 'open'))
                ->with(['account', 'lastMessage', 'adhesion'])->orderByDesc('last_message_at')->paginate(30)->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function show(Request $request, int $conversation)
    {
        $mutual = $this->authorizeMutual($request);
        $c = $this->find($mutual, $conversation)->load(['account', 'messages', 'adhesion']);
        $this->messaging->markReadByMutual($c);

        return view('tenant.conversations.show', ['c' => $c]);
    }

    public function reply(Request $request, int $conversation)
    {
        $mutual = $this->authorizeMutual($request);
        $c = $this->find($mutual, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $c->loadMissing('mutual');
        $this->messaging->postFromMutual($c, $data['body'], $request->user()->id);
        if ($c->status === 'closed') {
            $c->update(['status' => 'open']);
        }

        return redirect()->to(route('conversations.show', $c->id) . '#fin')->with('success', 'Réponse envoyée.');
    }

    public function toggle(Request $request, int $conversation)
    {
        $mutual = $this->authorizeMutual($request);
        $c = $this->find($mutual, $conversation);
        $c->update(['status' => $c->status === 'open' ? 'closed' : 'open']);

        return back()->with('success', $c->status === 'closed' ? 'Conversation close.' : 'Conversation rouverte.');
    }

    private function find(Mutual $mutual, int $id): Conversation
    {
        return Conversation::where('mutual_id', $mutual->id)->findOrFail($id);
    }

    private function authorizeMutual(Request $request): Mutual
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);

        return tenant();
    }
}
