@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm'; @endphp
<x-layouts.tenant title="Déclarer une prise en charge">
  <a href="{{ route('portal.index') }}" class="text-sm text-pine hover:underline">← Vérifier les droits</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Déclarer une prise en charge</h1>
  <p class="mt-1 text-sm text-ink/60">{{ $member->last_name }} {{ $member->first_name }} · <span class="font-mono">{{ $member->member_code }}</span> · droits vérifiés le {{ $check->created_at->format('d/m/Y à H:i') }} — {{ $provider->name }}</p>

  @if (session('rights_block'))
    <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><b>Prise en charge refusée :</b>
      <ul class="mt-1 list-disc pl-5">@foreach (session('rights_block') as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach</ul></div>
  @endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('portal.declare.store', $check->id) }}" class="mt-6 max-w-2xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <label class="block text-sm font-medium">Personne soignée *
      <select name="beneficiary" required class="{{ $f }}">
        <option value="member">{{ $member->last_name }} {{ $member->first_name }} (adhérent)</option>
        @foreach ($dependents as $d)<option value="{{ $d->id }}" @selected(old('beneficiary') == $d->id)>{{ $d->last_name }} {{ $d->first_name }} (personne à charge)</option>@endforeach
      </select></label>
    <label class="block text-sm font-medium">Acte *
      <select name="act_type_id" required class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id') === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Date des soins *<input type="date" name="care_date" required max="{{ now()->toDateString() }}" min="{{ now()->subDays(30)->toDateString() }}" value="{{ old('care_date', now()->toDateString()) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Montant total des soins ({{ $currency->code }}) *<input name="total" required inputmode="decimal" maxlength="20" value="{{ old('total') }}" class="{{ $f }}" placeholder="Ex. 15 000"></label>
    </div>
    <p class="rounded-md bg-mist px-3 py-2 text-xs text-ink/60">La part de la mutuelle et la part à régler par le patient sont calculées selon la garantie de l’adhérent et affichées après l’enregistrement. La mutuelle valide ensuite la prise en charge ; vous pourrez alors en demander le paiement dans « Mes factures ». N’indiquez aucun diagnostic.</p>
    <button class="rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">Déclarer</button>
  </form>
</x-layouts.tenant>
