@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm'; @endphp
<x-layouts.tenant title="Demander un accord préalable">
  <a href="{{ route('portal.index') }}" class="text-sm text-pine hover:underline">← Vérifier les droits</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Demander un accord préalable</h1>
  <p class="mt-1 text-sm text-ink/60">{{ $member->last_name }} {{ $member->first_name }} · <span class="font-mono">{{ $member->member_code }}</span> · droits vérifiés le {{ $check->created_at->format('d/m/Y à H:i') }} — {{ $provider->name }}</p>

  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('portal.preauth.store', $check->id) }}" class="mt-6 max-w-2xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <label class="block text-sm font-medium">Personne concernée *
      <select name="beneficiary" required class="{{ $f }}">
        <option value="member">{{ $member->last_name }} {{ $member->first_name }} (adhérent)</option>
        @foreach ($dependents as $d)<option value="{{ $d->id }}" @selected(old('beneficiary', $checkedDependent ?? null) == $d->id)>{{ $d->last_name }} {{ $d->first_name }} (personne à charge)</option>@endforeach
      </select></label>
    <label class="block text-sm font-medium">Acte prévu *
      <select name="act_type_id" required class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id') === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Date prévue *<input type="date" name="planned_on" required min="{{ now()->toDateString() }}" value="{{ old('planned_on', now()->addDay()->toDateString()) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Montant prévu ({{ $currency->code }}) *<input name="total" required inputmode="decimal" maxlength="20" value="{{ old('total') }}" class="{{ $f }}" placeholder="Ex. 150 000"></label>
    </div>
    <label class="block text-sm font-medium">Justification médicale (facultative)
      <textarea name="clinical_info" rows="4" maxlength="2000" class="{{ $f }}">{{ old('clinical_info') }}</textarea></label>
    <p class="rounded-md bg-mist px-3 py-2 text-xs text-ink/60">La justification médicale est lue seulement par les personnes habilitées de la mutuelle (médecin-conseil, contrôle). À l’envoi, MX-CONNECT vérifie automatiquement les droits, la garantie, la carence et les plafonds ; la mutuelle examine ensuite la demande et vous répond dans « Accords préalables ». <b>Une urgence n’attend pas l’accord</b> : soignez, puis déclarez en cochant « Urgence ».</p>
    <button class="rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">Envoyer la demande</button>
  </form>
</x-layouts.tenant>
