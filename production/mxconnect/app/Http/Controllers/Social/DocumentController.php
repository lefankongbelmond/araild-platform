<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Document;
use App\Models\Social\Post;
use App\Services\Social\DocumentService;
use App\Services\Social\PageService;

/**
 * Téléchargement d'un document PDF public. Le document suit la visibilité de ce
 * à quoi il est joint : une publication visible par ce visiteur, ou un projet publié
 * (les gestionnaires de la page voient aussi leurs brouillons).
 */
class DocumentController extends SocialController
{
    public function download(Document $document, DocumentService $documents)
    {
        $viewer = $this->viewer();
        $document->loadMissing(['post.mutual', 'project.mutual']);

        $allowed = match (true) {
            (bool) $document->post_id => $document->post
                && (Post::query()->visibleTo($viewer)->whereKey($document->post_id)->exists() || $document->post->canManage($viewer)),
            (bool) $document->project_id => $document->project
                && ($document->project->isPublic() || PageService::canPublish($viewer, $document->project->mutual)),
            default => false,
        };
        abort_unless($allowed, 404);

        return $documents->download($document);
    }
}
