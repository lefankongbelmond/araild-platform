<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ProviderInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Facture PDF jointe par un prestataire à sa demande de paiement (tiers payant).
 *
 * - Stockée dans le disque privé « local » de la mutuelle (dossier propre à chaque
 *   mutuelle), jamais servie par /media ni par une adresse publique.
 * - Seuls de vrais PDF (en-tête « %PDF- »), 5 Mo au plus.
 * - Téléchargement forcé, isolé du site (CSP sandbox), jamais mis en cache.
 * - La pièce peut contenir des informations médicales : côté mutuelle, elle est réservée
 *   aux rôles qui voient le détail médical (matrice : capacite.donnees-sante).
 */
class InvoiceDocumentService
{
    public const MAX_KB = 5120;

    public static function rules(): array
    {
        return ['document' => ['nullable', 'file', 'max:' . self::MAX_KB, 'mimetypes:application/pdf,application/x-pdf']];
    }

    public static function messages(): array
    {
        return [
            'document.max'       => 'La facture PDF ne doit pas dépasser 5 Mo.',
            'document.mimetypes' => 'La facture doit être un fichier PDF.',
            'document.uploaded'  => 'Le fichier n’a pas pu être envoyé (trop lourd ?). 5 Mo au plus.',
        ];
    }

    public function attach(ProviderInvoice $invoice, UploadedFile $file): void
    {
        $fh = fopen($file->getRealPath(), 'rb');
        $head = $fh ? fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($head !== '%PDF-') {
            throw ValidationException::withMessages(['document' => 'Ce fichier n’est pas un PDF valide.']);
        }

        $old = $invoice->supporting_doc_path;
        $path = $file->storeAs('provider-invoices/' . now()->format('Y/m'), Str::uuid() . '.pdf', 'local');
        $invoice->update(['supporting_doc_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }

    public function download(ProviderInvoice $invoice)
    {
        $disk = Storage::disk('local');
        abort_unless($invoice->supporting_doc_path && $disk->exists($invoice->supporting_doc_path), 404);
        $name = 'facture-' . $invoice->id . ($invoice->provider_reference ? '-' . Str::slug($invoice->provider_reference) : '') . '.pdf';

        // setPrivate() : sans lui, la réponse de téléchargement est marquée « public » (cache partagé possible).
        return response()->download($disk->path($invoice->supporting_doc_path), $name, [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox',
        ])->setPrivate()->setCache(['no_store' => true]);
    }
}
