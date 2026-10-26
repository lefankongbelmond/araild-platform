<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Social\Comment;
use App\Models\Social\Post;
use App\Models\Social\Report;
use App\Services\Social\FeedService;
use Illuminate\Http\Request;

/**
 * Modération du réseau social (signalements). Réservée aux rôles réseau
 * super_admin et moderator. Masquer ≠ supprimer : le contenu reste en base,
 * invisible du public, et peut être réaffiché.
 */
class ModerationController extends Controller
{
    private const ROLES = ['super_admin', 'moderator'];

    public function index(Request $request)
    {
        $this->authorizeRole($request);
        $status = in_array($request->get('statut'), ['open', 'resolved', 'dismissed'], true) ? $request->get('statut') : 'open';

        $reports = Report::where('status', $status)
            ->with(['reporter', 'post.mutual', 'post.account', 'comment.account', 'comment.post', 'reportedAccount', 'reportedMutual.profile'])
            ->latest()->paginate(30)->withQueryString();

        return view('network.moderation.index', [
            'reports' => $reports, 'status' => $status,
            'counts'  => Report::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'hidden'  => Post::where('status', 'hidden')->count(),
        ]);
    }

    /** Masquer le contenu signalé et clore tous les signalements le concernant. */
    public function hide(Request $request, Report $report)
    {
        $this->authorizeRole($request);
        if ($report->comment_id && ($c = Comment::find($report->comment_id))) {
            $c->update(['status' => 'hidden']);
            $c->post?->forceFill(['comments_count' => Comment::where('post_id', $c->post_id)->where('status', 'visible')->count()])->save();
            $this->close(Report::where('comment_id', $c->id), 'resolved', $request);
        } elseif ($report->reported_account_id && ($acc = \App\Models\Central\PublicAccount::find($report->reported_account_id))) {
            // Profil signalé : le compte est suspendu (connexion impossible, publications masquées).
            $acc->forceFill(['suspended_at' => now(), 'suspension_reason' => Report::REASONS[$report->reason] ?? $report->reason])->save();
            $this->close(Report::where('reported_account_id', $acc->id), 'resolved', $request);
        } elseif ($report->reported_mutual_id && ($m = \App\Models\Central\Mutual::with('profile')->find($report->reported_mutual_id))) {
            // Seules les pages créées par des membres sont suspendues ici (une mutuelle se gère dans « Mutuelles »).
            abort_unless($m->profile?->owner_account_id, 422, 'Organisation gérée sur MX-CONNECT : utilisez la page « Mutuelles ».');
            \App\Models\Central\Mutual::withoutEvents(fn () => $m->update(['status' => 'suspended']));
            $this->close(Report::where('reported_mutual_id', $m->id), 'resolved', $request);
        } elseif ($report->post_id && ($p = Post::find($report->post_id))) {
            $p->update(['status' => 'hidden']);
            if ($p->mutual_id) {
                app(FeedService::class)->refreshMutualCounters($p->mutual_id);
            }
            $this->close(Report::where('post_id', $p->id)->whereNull('comment_id'), 'resolved', $request);
        }

        return back()->with('success', 'Contenu masqué.');
    }

    public function dismiss(Request $request, Report $report)
    {
        $this->authorizeRole($request);
        $this->close(Report::whereKey($report->id), 'dismissed', $request);

        return back()->with('success', 'Signalement classé sans suite.');
    }

    /** Réafficher une publication masquée par erreur. */
    public function restore(Request $request, Post $post)
    {
        $this->authorizeRole($request);
        abort_unless($post->status === 'hidden', 422);
        $post->update(['status' => 'published', 'published_at' => $post->published_at ?? now()]);
        if ($post->mutual_id) {
            app(FeedService::class)->refreshMutualCounters($post->mutual_id);
        }

        return back()->with('success', 'Publication réaffichée.');
    }

    public function hiddenPosts(Request $request)
    {
        $this->authorizeRole($request);

        return view('network.moderation.hidden', [
            'posts' => Post::where('status', 'hidden')->with(['mutual', 'account'])->latest('updated_at')->paginate(30),
        ]);
    }

    /** Comptes suspendus : liste et réactivation. */
    public function accounts(Request $request)
    {
        $this->authorizeRole($request);

        return view('network.moderation.accounts', [
            'accounts' => \App\Models\Central\PublicAccount::whereNotNull('suspended_at')->latest('suspended_at')->paginate(30),
        ]);
    }

    public function restoreAccount(Request $request, int $account)
    {
        $this->authorizeRole($request);
        $acc = \App\Models\Central\PublicAccount::findOrFail($account);
        $acc->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

        return back()->with('success', "Compte de {$acc->name} réactivé.");
    }

    /** Pages créées par les membres : contrôle et suspension. */
    public function pages(Request $request)
    {
        $this->authorizeRole($request);
        $pages = \App\Models\Central\Mutual::whereHas('profile', fn ($p) => $p->whereNotNull('owner_account_id'))
            ->with(['profile.owner', 'country'])->latest()->paginate(30);

        return view('network.moderation.pages', ['pages' => $pages]);
    }

    public function togglePage(Request $request, string $mutual)
    {
        $this->authorizeRole($request);
        $page = \App\Models\Central\Mutual::whereKey($mutual)->whereHas('profile', fn ($p) => $p->whereNotNull('owner_account_id'))->firstOrFail();
        $suspend = $page->status !== 'suspended';
        \App\Models\Central\Mutual::withoutEvents(fn () => $page->update(['status' => $suspend ? 'suspended' : 'pending']));

        return back()->with('success', $suspend ? "Page « {$page->name} » suspendue : elle n'est plus visible." : "Page « {$page->name} » réactivée.");
    }

    private function close($query, string $status, Request $request): void
    {
        $query->where('status', 'open')->update(['status' => $status, 'handled_by' => $request->user()->id, 'handled_at' => now()]);
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);
    }
}
