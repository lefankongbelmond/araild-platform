@php
  $K = \App\Models\Provider\ProviderDocument::KINDS;
  $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm focus:border-pine focus:outline-none';
@endphp
<x-layouts.provider title="Agréments et documents">
  <x-tenant.hero icon="file" kicker="Gestion de l’établissement" tone="teal">
    <x-slot:title>Agréments et documents</x-slot:title>
    <x-slot:subtitle>Agrément, conventions signées, assurance, documents administratifs : PDF de 5 Mo au plus, conservés en privé. Les dates d’expiration alimentent les alertes du tableau de bord.</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  @if ($canManage)
    <x-tenant.panel class="mt-6" title="Ajouter un document" icon="plus">
      <form method="POST" action="{{ route('pro.documents.store') }}" enctype="multipart/form-data" class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">@csrf
        <label class="block text-sm font-medium">Type<select name="kind" class="{{ $f }}">@foreach ($K as $k => $v)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">Titre<input name="title" required maxlength="160" value="{{ old('title') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Référence / numéro<input name="reference" maxlength="80" value="{{ old('reference') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Délivré le<input type="date" name="issued_on" value="{{ old('issued_on') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Expire le<input type="date" name="expires_on" value="{{ old('expires_on') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Mutuelle concernée <span class="font-normal text-ink/50">(convention)</span><select name="mutual_id" class="{{ $f }}"><option value="">—</option>@foreach ($mutuals as $mu)<option value="{{ $mu->id }}" @selected(old('mutual_id') === $mu->id)>{{ $mu->name }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium sm:col-span-2">Fichier PDF<input type="file" name="file" required accept="application/pdf,.pdf" class="{{ $f }}"></label>
        <div class="flex items-end"><button class="rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Enregistrer le document</button></div>
      </form>
    </x-tenant.panel>
  @endif

  <x-tenant.panel class="mt-6" title="Documents de l’établissement" icon="file">
    <div class="relative overflow-x-auto px-2 pb-4">
      <table class="w-full text-sm">
        <thead class="text-left text-ink/50"><tr><th class="px-3 py-2 font-medium">Document</th><th class="px-3 py-2 font-medium">Type</th><th class="px-3 py-2 font-medium">Validité</th><th class="px-3 py-2"></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($docs as $doc)
            <tr>
              <td class="px-3 py-3"><b>{{ $doc->title }}</b><div class="text-xs text-ink/50">@if($doc->reference)N° {{ $doc->reference }} · @endif{{ number_format($doc->size / 1024, 0, ',', ' ') }} Ko @if($doc->mutual_id && isset($mutuals[$doc->mutual_id])) · {{ $mutuals[$doc->mutual_id]->name }}@endif</div></td>
              <td class="px-3 py-3 text-ink/60">{{ $K[$doc->kind] ?? $doc->kind }}</td>
              <td class="px-3 py-3">
                @if (! $doc->expires_on)<span class="text-ink/50">Sans échéance</span>
                @elseif ($doc->expires_on->lt(today()))<span class="rounded-full bg-clay/10 px-2 py-0.5 text-xs text-clay">Expiré le {{ $doc->expires_on->format('d/m/Y') }}</span>
                @elseif ($doc->expiresSoon())<span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Expire le {{ $doc->expires_on->format('d/m/Y') }}</span>
                @else<span class="text-ink/60">Jusqu’au {{ $doc->expires_on->format('d/m/Y') }}</span>@endif
              </td>
              <td class="px-3 py-3 text-right whitespace-nowrap">
                <a href="{{ route('pro.documents.download', $doc->id) }}" class="font-semibold text-pine hover:underline">Télécharger</a>
                @if ($canManage)<form method="POST" action="{{ route('pro.documents.destroy', $doc->id) }}" class="ml-2 inline" onsubmit="return confirm('Retirer ce document de la liste ?')">@csrf<button class="text-xs text-clay hover:underline">Retirer</button></form>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-3 py-8 text-center text-ink/50">Aucun document. Ajoutez au moins l’agrément de l’établissement : les mutuelles le vérifient.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-tenant.panel>
</x-layouts.provider>
