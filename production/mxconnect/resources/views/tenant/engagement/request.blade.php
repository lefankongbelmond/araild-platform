@php
  $S = array_map('__', \App\Models\Social\SocialRequest::STATUSES);
  $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $labels = ['in_review' => __('Passer en étude'), 'accepted' => __('Accepter'), 'rejected' => __('Ne pas retenir'), 'closed' => __('Clôturer')];
@endphp
<x-layouts.tenant :title="__('Demande :ref', ['ref' => $r->reference])">
  <a href="{{ route('engagement.index') }}" class="text-sm text-pine hover:underline">{{ __('← Projets et opportunités') }}</a>
  <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ $r->contact_name }}</h1><p class="text-sm text-ink/60">{{ __(':kindlabel · :reference · reçue le :date', ['kindlabel' => $r->kindLabel(), 'reference' => $r->reference, 'date' => $r->created_at->format('d/m/Y à H:i')]) }}</p></div>
    <span class="rounded-full bg-mist px-3 py-1 text-sm font-medium text-pine">{{ $r->statusLabel() }}</span>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md border border-pine/30 bg-mist px-4 py-3 text-sm text-pine">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">{{ __('Demande') }}</h2>
        <dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2">
          <dt class="text-ink/55">{{ __('Sujet') }}</dt><dd class="col-span-2">{{ $r->subject() }}</dd>
          @if ($r->subtypeLabel())<dt class="text-ink/55">{{ __('Type') }}</dt><dd class="col-span-2">{{ $r->subtypeLabel() }}</dd>@endif
          @if ($r->organisation_name)<dt class="text-ink/55">{{ __('Au nom de') }}</dt><dd class="col-span-2">{{ $r->organisation_name }}</dd>@endif
          @if ($r->availability)<dt class="text-ink/55">{{ __('Disponibilités') }}</dt><dd class="col-span-2">{{ $r->availability }}</dd>@endif
          <dt class="text-ink/55">{{ __('Téléphone') }}</dt><dd class="col-span-2">{{ $r->contact_phone ?: '—' }}</dd>
          <dt class="text-ink/55">{{ __('E-mail') }}</dt><dd class="col-span-2">{{ $r->contact_email ?: '—' }}</dd>
          @if ($r->cv_path)<dt class="text-ink/55">CV</dt><dd class="col-span-2"><a class="text-pine hover:underline" href="{{ route('engagement.requests.cv', $r->reference) }}">{{ $r->cv_name }}</a></dd>@endif
        </dl>
        <div class="mt-4 rounded-md bg-mist p-3 whitespace-pre-line">{{ $r->message }}</div>
      </section>
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">{{ __('Historique') }}</h2>
        <ol class="mt-3 space-y-3">
          @foreach ($r->events as $e)
            <li><b>{{ $S[$e->to_status] ?? $e->to_status }}</b> <span class="text-xs text-ink/50">· {{ $e->created_at?->format('d/m/Y H:i') }} · {{ $e->actor_label }}</span>@if ($e->note)<div class="mt-1 whitespace-pre-line text-ink/75">{{ $e->note }}</div>@endif</li>
          @endforeach
        </ol>
      </section>
    </div>
    <div class="space-y-6">
      @if ($transitions)
        <form method="POST" action="{{ route('engagement.requests.decide', $r->reference) }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
          @csrf
          <h2 class="font-semibold">{{ __('Répondre') }}</h2>
          <label class="block">{{ __('Nouveau statut') }}
            <select name="status" class="{{ $field }}">@foreach ($transitions as $t)<option value="{{ $t }}">{{ $labels[$t] ?? $S[$t] }}</option>@endforeach</select></label>
          <label class="block">{{ __('Message au demandeur (facultatif)') }}<textarea name="note" rows="4" maxlength="2000" class="{{ $field }}">{{ old('note') }}</textarea></label>
          <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">{{ __('Enregistrer') }}</button>
          <p class="text-xs text-ink/45">{{ __('Le demandeur est notifié. Accepter ne déclenche aucun paiement : la suite se convient directement avec lui.') }}</p>
        </form>
      @endif
    </div>
  </div>
</x-layouts.tenant>
