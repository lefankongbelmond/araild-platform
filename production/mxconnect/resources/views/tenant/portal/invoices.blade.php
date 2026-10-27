@php $S = ['captured' => 'Transmise', 'processed' => 'En traitement', 'prepared' => 'Mise en paiement', 'paid' => 'Payée', 'rejected' => 'Rejetée']; @endphp
<x-layouts.tenant title="Mes factures">
  @include('tenant.portal._head', ['title' => 'Mes factures'])

  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif
  <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm">
    <h2 class="font-semibold">Prises en charge validées à facturer</h2>
    <p class="mt-1 text-xs text-ink/55">Montant = part de la mutuelle des prestations validées (tiers payant). Le paiement est fait par la mutuelle, hors de la plateforme. N’indiquez pas de diagnostic sur la facture si ce n’est pas indispensable.</p>
    <ul class="mt-3 divide-y divide-ink/5">
      @forelse ($billable as $c)
        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
          <span><b>{{ $c->member?->last_name }} {{ $c->member?->first_name }}</b> <span class="text-xs text-ink/50">soins du {{ $c->opened_on?->format('d/m/Y') }}</span>
            <span class="block font-medium tabular-nums">{{ $currency->format((int) $c->due_minor) }}</span></span>
          @if ($convention->inForce())
            <form method="POST" action="{{ route('portal.invoices.store', $c->id) }}" enctype="multipart/form-data" class="grid w-full gap-2" style="max-width:22rem">@csrf
              <label class="sr-only" for="ref-{{ $c->id }}">Votre n° de facture</label>
              <input id="ref-{{ $c->id }}" name="provider_reference" maxlength="60" placeholder="Votre n° de facture" class="w-full rounded-md border border-ink/15 px-3 py-2 text-sm">
              <label class="min-w-0 text-xs text-ink/60">Facture PDF (facultatif, 5 Mo)<input type="file" name="document" accept="application/pdf,.pdf" class="mt-1 block w-full text-xs" style="max-width:100%"></label>
              <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Demander le paiement</button>
            </form>
          @endif
        </li>
      @empty
        <li class="py-2 text-ink/50">Rien à facturer pour l’instant : les prises en charge apparaissent ici une fois validées par la mutuelle.</li>
      @endforelse
    </ul>
  </section>

  <div class="relative mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">Facture</th><th class="px-4 py-2 font-medium">Patient</th><th class="px-4 py-2 font-medium">Montant</th><th class="px-4 py-2 font-medium">État</th><th class="px-4 py-2 font-medium">Pièce</th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($invoices as $inv)
          <tr>
            <td class="px-4 py-3">n° {{ $inv->id }}@if($inv->provider_reference)<div class="text-xs text-ink/50">Votre réf. {{ $inv->provider_reference }}</div>@endif<div class="text-xs text-ink/50">{{ $inv->created_at?->format('d/m/Y') }}</div></td>
            <td class="px-4 py-3">{{ $inv->careClaim?->member?->last_name }} {{ $inv->careClaim?->member?->first_name }}</td>
            <td class="px-4 py-3 font-medium tabular-nums">{{ $currency->format($inv->amount_minor) }}</td>
            <td class="px-4 py-3 text-xs {{ $inv->status === 'paid' ? 'text-pine' : ($inv->status === 'rejected' ? 'text-clay' : 'text-ink/60') }}">{{ $S[$inv->status] ?? $inv->status }}@if($inv->status === 'paid') le {{ ($inv->decided_at ?? $inv->updated_at)?->format('d/m/Y') }}@endif
              @if ($inv->status === 'rejected' && $inv->decision_note)<div class="mt-1 text-clay">Motif : {{ $inv->decision_note }}</div>@endif</td>
            <td class="px-4 py-3 text-xs">
              @if ($inv->supporting_doc_path)<a class="text-pine underline" href="{{ route('portal.invoices.document', $inv->id) }}">Facture PDF</a>@endif
              @if (in_array($inv->status, ['captured', 'processed'], true) && $convention->inForce())
                <form method="POST" action="{{ route('portal.invoices.document.store', $inv->id) }}" enctype="multipart/form-data" class="mt-1 flex flex-wrap items-center gap-2">@csrf
                  <label class="sr-only" for="doc-{{ $inv->id }}">Facture PDF</label>
                  <input id="doc-{{ $inv->id }}" type="file" name="document" accept="application/pdf,.pdf" required class="w-40 text-xs">
                  <button class="text-pine underline">{{ $inv->supporting_doc_path ? 'Remplacer' : 'Joindre' }}</button>
                </form>
              @elseif (! $inv->supporting_doc_path)—@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">Aucune facture.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.tenant>
