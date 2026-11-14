<x-layouts.tenant title="Dérogations">
  @php
    $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm';
    $canAsk = \App\Support\TenantAccess::can('derogations.store');
    $canDecide = \App\Support\TenantAccess::can('derogations.approve');
    $tone = ['requested' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-leaf-50 text-leaf-700', 'rejected' => 'bg-mist text-ink/60', 'revoked' => 'bg-mist text-ink/60'];
  @endphp
  <x-tenant.hero icon="shield" kicker="Prestations · contrôle « 4 yeux »" tone="amber">
    <x-slot:title>Dérogations</x-slot:title>
    <x-slot:subtitle>Prise en charge exceptionnelle pour un bénéficiaire : dispense de carence, plafond supplémentaire ou acte exclu accordé. Demandée avec une justification, décidée par une autre personne. La formule n’est jamais modifiée et chaque prestation qui utilise une dérogation en garde la trace.</x-slot:subtitle>
  </x-tenant.hero>

  @if ($errors->any())<div class="mt-4 rounded-lg border border-clay/30 bg-white p-4 text-sm text-clay" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
      <nav class="flex flex-wrap gap-2 text-sm" aria-label="Filtrer">
        <a href="{{ route('derogations.index', array_filter(['adherent' => $member?->member_code])) }}" @class(['rounded-full px-3 py-1', 'bg-pine text-white' => ! $status, 'bg-white text-ink/70 ring-1 ring-ink/10' => $status])>Toutes</a>
        @foreach (\App\Models\Tenant\CoverageException::STATUSES as $k => $t)
          <a href="{{ route('derogations.index', array_filter(['statut' => $k, 'adherent' => $member?->member_code])) }}" @class(['rounded-full px-3 py-1', 'bg-pine text-white' => $status === $k, 'bg-white text-ink/70 ring-1 ring-ink/10' => $status !== $k])>{{ $t }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
      </nav>
      @if ($member)<p class="text-sm text-ink/70">Adhérent : <b>{{ mb_strtoupper($member->last_name) }} {{ $member->first_name }}</b> ({{ $member->member_code }}) · <a href="{{ route('derogations.index') }}" class="text-pine underline">voir toutes</a></p>@endif

      @forelse ($items as $x)
        <article class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
              <b>{{ \App\Models\Tenant\CoverageException::KINDS[$x->kind] ?? $x->kind }}</b>
              <span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$x->status] ?? '' }}">{{ \App\Models\Tenant\CoverageException::STATUSES[$x->status] ?? $x->status }}</span>
              <div class="text-xs text-ink/60">{{ $x->member ? mb_strtoupper($x->member->last_name) . ' ' . $x->member->first_name . ' (' . $x->member->member_code . ')' : '—' }}@if ($x->dependent) · ayant droit : {{ $x->dependent->first_name }}@endif
                · {{ $x->actType?->label ?? $x->category?->label ?? 'toutes les prestations' }}
                @if ($x->amount_minor) · {{ $currency->format($x->amount_minor) }} (utilisé {{ $currency->format($x->used_minor) }})@endif
                · du {{ $x->valid_from?->format('d/m/Y') }}{{ $x->valid_to ? ' au ' . $x->valid_to->format('d/m/Y') : '' }}</div>
            </div>
            <span class="text-xs text-ink/45">n° {{ $x->id }}</span>
          </div>
          <p class="mt-2"><span class="text-ink/50">Motif :</span> {{ $x->reason }}</p>
          <p class="mt-1 whitespace-pre-line text-ink/70"><span class="text-ink/50">Justification :</span> {{ $x->justification }}</p>
          <p class="mt-1 text-xs text-ink/50">Demandée par {{ $users[$x->requested_by] ?? '?' }} le {{ $x->created_at?->format('d/m/Y') }}@if ($x->decided_by) · décidée par {{ $users[$x->decided_by] ?? '?' }} le {{ $x->decided_at?->format('d/m/Y') }}@endif</p>
          @if ($x->decision_note)<p class="mt-1 whitespace-pre-line text-xs text-ink/60">{{ $x->decision_note }}</p>@endif
          @if ($canDecide && $x->status === 'requested')
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
              <form method="POST" action="{{ route('derogations.approve', $x) }}">@csrf<input name="note" maxlength="500" placeholder="Commentaire (facultatif)" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"><button class="mt-2 rounded-md bg-pine px-3 py-1.5 text-xs font-semibold text-white hover:bg-navy">Accorder</button></form>
              <form method="POST" action="{{ route('derogations.reject', $x) }}">@csrf<input name="note" required maxlength="500" placeholder="Motif du refus *" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"><button class="mt-2 rounded-md border border-clay/40 px-3 py-1.5 text-xs font-semibold text-clay hover:bg-mist">Refuser</button></form>
            </div>
          @elseif ($canDecide && $x->status === 'approved')
            <details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-clay">Retirer</summary>
              <form method="POST" action="{{ route('derogations.revoke', $x) }}" class="mt-2">@csrf<input name="note" required maxlength="500" placeholder="Motif du retrait *" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"><button class="mt-2 rounded-md border border-clay/40 px-3 py-1.5 text-xs font-semibold text-clay hover:bg-mist">Confirmer le retrait</button></form>
            </details>
          @endif
        </article>
      @empty
        <p class="rounded-lg border border-ink/10 bg-white p-8 text-center text-sm text-ink/50">Aucune dérogation.</p>
      @endforelse
      {{ $items->links() }}
    </div>

    @if ($canAsk)
      <form method="POST" action="{{ route('derogations.store') }}" class="self-start space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
        <h2 class="font-semibold">Demander une dérogation</h2>
        <label class="block text-sm font-medium">N° d’adhérent *<input name="member_code" required maxlength="40" value="{{ old('member_code', $member?->member_code) }}" class="{{ $f }}"></label>
        @if ($member && $member->dependents()->exists())
          <label class="block text-sm font-medium">Bénéficiaire<select name="dependent_id" class="{{ $f }}"><option value="">L’adhérent lui-même</option>@foreach ($member->dependents()->where('status', 'active')->get() as $d)<option value="{{ $d->id }}">{{ $d->first_name }} {{ $d->last_name }}</option>@endforeach</select></label>
        @endif
        <label class="block text-sm font-medium">Type *<select name="kind" class="{{ $f }}">@foreach (\App\Models\Tenant\CoverageException::KINDS as $k => $t)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $t }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">Catégorie de soins<select name="service_category_id" class="{{ $f }}"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('service_category_id') === $c->id)>{{ $c->label }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">ou acte précis<select name="act_type_id" class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id') === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">Montant supplémentaire ({{ $currency->code }})<input name="amount" inputmode="decimal" value="{{ old('amount') }}" class="{{ $f }}" placeholder="Pour un plafond supplémentaire"></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="block text-sm font-medium">Du *<input type="date" name="valid_from" required value="{{ old('valid_from', now()->toDateString()) }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Au<input type="date" name="valid_to" value="{{ old('valid_to') }}" class="{{ $f }}"></label>
        </div>
        <label class="block text-sm font-medium">Motif *<input name="reason" required maxlength="160" value="{{ old('reason') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Justification *<textarea name="justification" required rows="4" maxlength="2000" class="{{ $f }}">{{ old('justification') }}</textarea></label>
        <p class="text-xs text-ink/50">N’indiquez aucun diagnostic : seulement le contexte administratif (pièce fournie, avis du médecin-conseil, transfert…).</p>
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Demander</button>
      </form>
    @endif
  </div>
</x-layouts.tenant>
