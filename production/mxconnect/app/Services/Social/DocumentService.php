<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Documents PDF publics des pages. Contrôles : vrai PDF (signature %PDF), taille,
 * nombre maximum ; fichier rangé sous un nom aléatoire (UUID) sur le disque public
 * « social », téléchargé par une route qui force l'enregistrement (jamais affiché
 * « en ligne » sur le domaine du site).
 */
class DocumentService
{
    public const MAX_PER_POST = 3;
    public const MAX_PER_PROJECT = 10;

    public static function maxKb(): int
    {
        return (int) config('mxconnect.social.document_max_kb', 10240);
    }

    public static function rules(int $max): array
    {
        return [
            'documents'        => ['nullable', 'array', 'max:' . $max],
            'documents.*'      => ['file', 'mimes:pdf', 'max:' . self::maxKb()],
            'document_titles'  => ['nullable', 'array'],
            'document_titles.*'=> ['nullable', 'string', 'max:180'],
        ];
    }

    public static function messages(): array
    {
        return [
            'documents.max'               => 'Trop de documents à la fois.',
            'documents.*.mimes'           => 'Seuls les documents PDF sont acceptés.',
            'documents.*.max'             => 'Chaque document doit faire moins de ' . round(self::maxKb() / 1024) . ' Mo.',
        ];
    }

    /** Si des documents sont joints : la case « documents publics, sans donnée médicale » est obligatoire. */
    public static function requireConsent(\Illuminate\Http\Request $request, ?string $bag = null): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }
        $rules = ['documents_public' => ['accepted']];
        $msg = ['documents_public.accepted' => 'Confirmez que les documents sont publics et ne contiennent aucune donnée médicale ou personnelle.'];
        $bag ? $request->validateWithBag($bag, $rules, $msg) : $request->validate($rules, $msg);
    }

    /**
     * @param UploadedFile[] $files
     * @param array<int,?string> $titles
     * @return Document[]
     */
    public function store(Mutual $page, array $files, array $titles, array $link, ?PublicAccount $by = null, ?int $tenantUserId = null): array
    {
        $stored = [];
        try {
            foreach (array_values($files) as $i => $file) {
                $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 5);
                if ($head !== '%PDF-') {
                    throw ValidationException::withMessages(['documents' => 'Ce fichier n’est pas un PDF valide : ' . $file->getClientOriginalName()]);
                }
                $path = 'social/documents/' . now()->format('Y/m') . '/' . Str::uuid() . '.pdf';
                Storage::disk('social')->put($path, file_get_contents($file->getRealPath()));
                $original = Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 170, '');
                $title = trim((string) ($titles[$i] ?? '')) ?: str_replace('_', ' ', $original) ?: 'Document';
                $stored[] = Document::create($link + [
                    'mutual_id' => $page->id, 'title' => Str::limit($title, 180, ''), 'path' => $path,
                    'original_name' => $original . '.pdf', 'size' => $file->getSize(),
                    'uploaded_by_account_id' => $by?->id, 'tenant_user_id' => $tenantUserId,
                ]);
            }
        } catch (\Throwable $e) {
            foreach ($stored as $d) { $this->delete($d); }
            throw $e;
        }

        return $stored;
    }

    public function delete(Document $doc): void
    {
        Storage::disk('social')->delete($doc->path);
        $doc->delete();
    }

    /** Téléchargement forcé (« attachment »), isolé par une CSP « sandbox ». */
    public function download(Document $doc)
    {
        $disk = Storage::disk('social');
        abort_unless($disk->exists($doc->path), 404);
        Document::whereKey($doc->id)->increment('downloads_count');
        $name = Str::slug(Str::limit($doc->title, 80, '')) ?: 'document';

        return response()->download($disk->path($doc->path), $name . '.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox',
        ])->setPublic()->setMaxAge(3600);
    }
}
