<x-layouts.tenant title="Accord préalable n° {{ $p->id }}">
  @php
    $S = array_map('__', \App\Models\Tenant\Preauthorization::STATUSES); $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm';
    $who = $p->dependent ?? $p->member; $ck = $p->auto_checks ?? [];
    $name = fn ($id) => $id && isset($users[$id]) ? trim($users[$id]->first_name . ' ' . $users[$id]->last_name) : '—';
    $canDecide = \App\Support\TenantAccess::can('preauth.decide') && $p->status === 'requested';
  @endphp
  <a href="{{ route('preauth.index') }}" class="text-sm text-ink/50 hover:underline">{{ __('&larr; Accords préalables') }}</a>
  <x-tenant.hero icon="shield" kicker="Accord préalable · {{ $p->review_type === 'medical' ? __('examen médical') : __('examen administratif') }}" tone="teal" class="mt-2">
    <x-slot:title>{{ __('Demande n° :id — :status', ['id' => $p->id, 'status' => $S[$p->status] ?? $p->status]) }}</x-slot:title>
    <x-slot:subtitle>{{ $p->actType?->label }} · {{ $providers[$p->provider_id] ?? __('Établissement n° :id', ['id' => $p->provider_id]) }} · {{ __('prévu le :date', ['date' => $p->planned_on?->format('d/m/Y')]) }}@if ($p->number) · <b>{{ $p->number }}</b>@endif</x-slot:subtitle>
  </x-tenant.hero>

  @if ($p->emergency)
    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><b>{{ __('Urgence soignée sans accord préalable.') }}</b> {{ __('Les soins n’ont pas été bloqués. Cette demande régularise la prise en charge n° :prestation_id : tant qu’elle n’est pas examinée, la prestation ne peut pas être validée.', ['prestation_id' => $p->prestation_id]) }}</p>
  @endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-6 lg:col-span-2">
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">{{ __('Demande') }}</h2>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
          <div><dt class="text-ink/50">{{ __('Bénéficiaire') }}</dt><dd class="font-semibold">{{ mb_strtoupper($who?->last_name ?? '') }} {{ $who?->first_name }}@if ($p->dependent) <span class="text-xs font-normal text-ink/55">{{ __('(ayant droit de :name :last_name)', ['name' => $p->member?->first_name, 'last_name' => $p->member?->last_name]) }}</span>@endif</dd></div>
          <div><dt class="text-ink/50">{{ __('N° d’adhérent') }}</dt><dd class="font-mono">{{ $p->member?->member_code }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Montant prévu') }}</dt><dd>{{ $currency->format($p->estimated_minor) }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Part mutuelle estimée') }}</dt><dd>{{ $p->estimated_mutual_minor !== null ? $currency->format($p->estimated_mutual_minor) : __('non calculable (voir les contrôles)') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Demandée par') }}</dt><dd>{{ $p->source === 'provider' ? __('le prestataire') : __('la mutuelle') }} · {{ $name($p->requested_by) }} · {{ $p->created_at?->format('d/m/Y H:i') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Garantie concernée') }}</dt><dd>{{ $p->rule?->label ?? '—' }}</dd></div>
        </dl>
        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink/50">{{ __('Justification médicale') }}</h3>
        @if ($health)
          <p class="mt-1 whitespace-pre-line text-ink/80">{{ $p->clinical_info ?: __('Aucune justification transmise.') }}</p>
        @else
          <p class="mt-1 text-ink/55">{{ __('Réservée aux personnes habilitées (données de santé).') }}</p>
        @endif
      </section>

      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">{{ __('Contrôles automatiques') }}</h2>
        <p class="text-xs text-ink/55">{{ __('Faits par le moteur de prise en charge à l’enregistrement (:at), pour la date prévue, sans tenir compte de l’accord lui-même.', ['at' => $ck['at'] ?? '—']) }}</p>
        <p class="mt-2 font-bold {{ ($ck['status'] ?? '') === 'active' ? 'text-pine' : 'text-clay' }}">{{ $ck['label'] ?? '—' }}</p>
        @if (! empty($ck['reasons']))<ul class="mt-1 list-disc pl-5 text-ink/70">@foreach ($ck['reasons'] as $r)<li>{{ __('mxconnect.rights.' . $r) }}</li>@endforeach</ul>@endif
        <dl class="mt-2 space-y-1">
          @if (! empty($ck['rule']))<div class="flex justify-between gap-2"><dt>{{ __('Garantie appliquée') }}</dt><dd>{{ $ck['rule'] }}@if (isset($ck['rate'])) ({{ rtrim(rtrim(number_format((float) $ck['rate'], 2, ',', ''), '0'), ',') }} %)@endif</dd></div>@endif
          @if (isset($ck['mutual']))<div class="flex justify-between gap-2"><dt>{{ __('Part mutuelle / part patient') }}</dt><dd class="tabular-nums">{{ $currency->format((int) $ck['mutual']) }} / {{ $currency->format((int) ($ck['patient'] ?? 0)) }}</dd></div>@endif
          @if (! empty($ck['waiting_until']))<div class="flex justify-between gap-2"><dt>{{ __('Fin de carence') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($ck['waiting_until'])->format('d/m/Y') }}</dd></div>@endif
          @foreach ($ck['limits'] ?? [] as $l)
            <div class="flex justify-between gap-2 text-xs text-ink/60"><dt>{{ $l['label'] }}</dt><dd class="tabular-nums">@if ($l['remaining_before'] !== null) {{ __('reste :amount', ['amount' => $currency->format((int) $l['remaining_before'])]) }}@endif @if ($l['remaining_count'] !== null) · {{ __(':count acte(s)', ['count' => $l['remaining_count']]) }}@endif</dd></div>
          @endforeach
        </dl>
      </section>
    </div>

    <div class="space-y-6 self-start">
      @if ($p->status !== 'requested')
        <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
          <h2 class="font-semibold">{{ __('Décision') }}</h2>
          <p class="mt-2"><b>{{ $S[$p->status] ?? $p->status }}</b>@if ($p->number) · <span class="font-mono">{{ $p->number }}</span>@endif</p>
          <p class="text-xs text-ink/55">{{ __('par :decided_by le :date', ['decided_by' => $name($p->decided_by), 'date' => $p->decided_at?->format('d/m/Y à H:i')]) }}</p>
          @if ($p->approved_minor !== null)<p class="mt-1">{{ __('Part mutuelle accordée au plus : :amount', ['amount' => $currency->format($p->approved_minor)]) }}</p>@endif
          @if ($p->valid_until)<p class="mt-1">{{ __('Valable jusqu’au :date', ['date' => $p->valid_until->format('d/m/Y')]) }}</p>@endif
          @if ($p->decision_note)<p class="mt-2 whitespace-pre-line text-ink/70">{{ $p->decision_note }}</p>@endif
          @if ($p->prestation_id)<p class="mt-2 text-xs text-ink/60">{{ __('Utilisé par la prise en charge n° :prestation_id.', ['prestation_id' => $p->prestation_id]) }}</p>@endif
        </section>
      @elseif ($canDecide)
        <form method="POST" action="{{ route('preauth.decide', $p) }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
          <h2 class="font-semibold">{{ __('Décider') }}</h2>
          @if ($p->review_type === 'medical')<p class="text-xs text-ink/55">{{ __('Examen médical : seul un médecin-conseil peut décider.') }}</p>@endif
          <p class="text-xs text-ink/55">{{ __('Vous ne pouvez pas décider une demande que vous avez saisie.') }}</p>
          <label class="block text-sm font-medium">{{ __('Part mutuelle accordée au plus (:currency)', ['currency' => $currency->code]) }}<input name="approved" inputmode="decimal" value="{{ old('approved') }}" class="{{ $f }}" placeholder="{{ __('Vide = calcul de la garantie') }}"></label>
          <label class="block text-sm font-medium">{{ __('Valable jusqu’au') }}<input type="date" name="valid_until" min="{{ now()->toDateString() }}" value="{{ old('valid_until') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">{{ __('Commentaire ou motif') }}<textarea name="note" rows="3" maxlength="1000" class="{{ $f }}" placeholder="{{ __('Obligatoire en cas de rejet : il est communiqué au prestataire') }}">{{ old('note') }}</textarea></label>
          <div class="flex flex-wrap gap-2">
            <button name="decision" value="approved" class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Accorder') }}</button>
            <button name="decision" value="rejected" class="rounded-md border border-clay/40 px-4 py-2 text-sm font-semibold text-clay hover:bg-mist">{{ __('Rejeter') }}</button>
          </div>
        </form>
      @else
        <p class="rounded-lg border border-ink/10 bg-white p-5 text-sm text-ink/60">{{ __('En attente d’examen.') }}</p>
      @endif
    </div>
  </div>
</x-layouts.tenant>
