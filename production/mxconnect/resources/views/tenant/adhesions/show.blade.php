@php
  $S = \App\Models\Social\AdhesionRequest::STATUSES; $Rel = \App\Models\Social\AdhesionRequest::RELATIONS;
  $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $canDecide = auth()->user()->hasAnyRole(['mutual_admin', 'controller_validator']);
  $canEnroll = auth()->user()->hasAnyRole(['mutual_admin', 'enrollment_agent']);
  $labels = ['in_review' => 'Passer en étude', 'info_requested' => 'Demander un complément', 'accepted' => 'Accepter', 'rejected' => 'Refuser'];
@endphp
<x-layouts.tenant :title="'Dossier ' . $r->reference">
  <a href="{{ route('online-adhesions.index') }}" class="text-sm text-pine hover:underline">← Adhésions en ligne</a>
  <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ $r->name }}</h1><p class="text-sm text-ink/60">Dossier {{ $r->reference }} · reçu le {{ $r->created_at->format('d/m/Y à H:i') }}</p></div>
    <span class="rounded-full bg-mist px-3 py-1 text-sm font-medium text-pine">{{ $r->statusLabel() }}</span>
  </div>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Demandeur</h2>
        <dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2">
          <dt class="text-ink/55">Identité</dt><dd class="col-span-2">{{ $r->name }} · {{ $r->sex === 'F' ? 'F' : 'M' }} · né(e) le {{ $r->birth_date->format('d/m/Y') }} ({{ $r->birth_date->age }} ans)</dd>
          <dt class="text-ink/55">Contact</dt><dd class="col-span-2">{{ $r->phone }}@if($r->email) · {{ $r->email }}@endif</dd>
          <dt class="text-ink/55">Adresse</dt><dd class="col-span-2">{{ collect([$r->address, $r->city])->filter()->implode(', ') }}</dd>
          @if ($r->profession)<dt class="text-ink/55">Profession</dt><dd class="col-span-2">{{ $r->profession }}</dd>@endif
          @if ($r->id_document_number)<dt class="text-ink/55">N° pièce d’identité</dt><dd class="col-span-2">{{ $r->id_document_number }}</dd>@endif
          <dt class="text-ink/55">Foyer</dt><dd class="col-span-2">{{ $r->household_size }} personne(s)@if($r->formula) · formule souhaitée : {{ $r->formula }}@endif</dd>
          @if (! empty($r->beneficiaries))
            <dt class="text-ink/55">Bénéficiaires</dt><dd class="col-span-2">@foreach ($r->beneficiaries as $b)<div>{{ $b['name'] }} — {{ $Rel[$b['relation'] ?? 'autre'] ?? 'Autre' }}@if(! empty($b['birth_date'])), {{ \Illuminate\Support\Carbon::parse($b['birth_date'])->format('d/m/Y') }}@endif</div>@endforeach</dd>
          @endif
        </dl>
        @if ($r->message)<div class="mt-4 rounded-md bg-mist p-3 whitespace-pre-line">{{ $r->message }}</div>@endif
      </section>

      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Pièces jointes</h2>
        <ul class="mt-2 space-y-1">
          @forelse ($r->documents as $d)
            <li><a class="text-pine hover:underline" href="{{ route('online-adhesions.document', [$r->reference, $d->id]) }}">{{ $d->kindLabel() }} — {{ $d->original_name }}</a> <span class="text-xs text-ink/45">({{ number_format($d->size / 1024, 0, ',', ' ') }} Ko)</span></li>
          @empty <li class="text-ink/50">Aucune pièce jointe.</li> @endforelse
        </ul>
      </section>

      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Historique</h2>
        <ol class="mt-3 space-y-3">
          @foreach ($r->events as $e)
            <li><b>{{ $S[$e->to_status] ?? $e->to_status }}</b> <span class="text-xs text-ink/50">· {{ $e->created_at?->format('d/m/Y H:i') }} · {{ $e->actor === 'mutual' ? $e->actor_label : 'le demandeur' }}</span>@if ($e->note)<div class="mt-1 whitespace-pre-line text-ink/75">{{ $e->note }}</div>@endif</li>
          @endforeach
        </ol>
      </section>
    </div>

    <div class="space-y-6">
      @if ($transitions)
        <form method="POST" action="{{ route('online-adhesions.decide', $r->reference) }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
          @csrf
          <h2 class="font-semibold">Décision</h2>
          <label class="block">Nouveau statut
            <select name="status" class="{{ $field }}">
              @foreach ($transitions as $t)
                @if (! in_array($t, ['accepted', 'rejected'], true) || $canDecide)<option value="{{ $t }}">{{ $labels[$t] ?? $t }}</option>@endif
              @endforeach
            </select></label>
          <label class="block">Message au demandeur <span class="text-ink/45">(obligatoire pour un complément ou un refus)</span>
            <textarea name="note" rows="4" maxlength="2000" class="{{ $field }}">{{ old('note') }}</textarea></label>
          @unless ($canDecide)<p class="text-xs text-ink/50">Accepter ou refuser : réservé à l’administrateur et au contrôleur-validateur.</p>@endunless
          <button class="w-full rounded-md bg-pine px-4 py-2 font-medium text-white hover:bg-navy">Enregistrer</button>
        </form>
      @endif

      @if ($r->status === 'accepted' && $canEnroll)
        <section class="space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
          <h2 class="font-semibold">Créer l’adhérent</h2>
          <p class="text-ink/60">1. Ouvrez la fiche « nouvel adhérent » préremplie, vérifiez puis enregistrez-la.</p>
          <form method="POST" action="{{ route('online-adhesions.prefill', $r->reference) }}">@csrf<button class="w-full rounded-md border border-pine px-4 py-2 font-medium text-pine hover:bg-mist">Ouvrir la fiche préremplie</button></form>
          <p class="text-ink/60">2. Reliez ensuite le compte du demandeur à sa fiche (accès à l’espace adhérent).</p>
          @if ($r->member_code)
            <p class="rounded-md bg-mist px-3 py-2">Relié à la fiche <b>{{ $r->member_code }}</b>.</p>
          @else
            <form method="POST" action="{{ route('online-adhesions.link', $r->reference) }}" class="flex gap-2">@csrf
              <input name="member_code" required maxlength="40" placeholder="Code adhérent" class="{{ $field }} mt-0">
              <button class="rounded-md bg-pine px-3 py-2 text-white">Relier</button></form>
          @endif
          <p class="text-xs text-ink/50">La cotisation se règle selon vos procédures habituelles : aucun paiement n’est encaissé par la plateforme sociale.</p>
        </section>
      @endif

      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Échanges</h2>
        @if ($r->conversation && \Illuminate\Support\Facades\Route::has('conversations.show') && \App\Support\TenantAccess::can('conversations.show'))
          <a class="mt-2 inline-block text-pine hover:underline" href="{{ route('conversations.show', $r->conversation->id) }}">Ouvrir la conversation avec le demandeur</a>
        @else
          <p class="mt-2 text-ink/55">Pas encore de conversation. Utilisez « Demander un complément » pour solliciter le demandeur.</p>
        @endif
      </section>
    </div>
  </div>
</x-layouts.tenant>
