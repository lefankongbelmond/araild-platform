@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm'; @endphp
<x-layouts.tenant title="{{ __('Déclarer une prise en charge') }}">
  <a href="{{ route('portal.index') }}" class="text-sm text-pine hover:underline">{{ __('← Vérifier les droits') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Déclarer une prise en charge') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ $member->last_name }} {{ $member->first_name }} · <span class="font-mono">{{ $member->member_code }}</span> {{ __('· droits vérifiés le :date — :name', ['date' => $check->created_at->format('d/m/Y à H:i'), 'name' => $provider->name]) }}</p>

  @if (session('rights_block'))
    <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><b>{{ __('Prise en charge refusée :') }}</b>
      <ul class="mt-1 list-disc pl-5">@foreach (session('rights_block') as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach</ul>
      @if (in_array('authorization_required', session('rights_block'), true))
        <p class="mt-2 text-ink">{{ __('Ces soins demandent un') }} <b>{{ __('accord préalable') }}</b> {{ __('de la mutuelle :') }} <a href="{{ route('portal.preauth.create', $check->id) }}" class="font-semibold text-pine underline">{{ __('demander l’accord') }}</a>{{ __('. En cas d’') }}<b>{{ __('urgence') }}</b>{{ __(', cochez « Urgence » et déclarez : les soins ne sont jamais bloqués, la mutuelle régularise ensuite.') }}</p>
      @endif</div>
  @endif
  @if (session('duplicate_of'))
    <div class="mt-4 rounded-md border border-clay/30 bg-amber-50 px-4 py-3 text-sm text-ink" role="alert"><b>{{ __('Déclaration peut-être en double.') }}</b>
      {{ __('Votre établissement a déjà déclaré pour cette personne le même acte à la même date (prise en charge n° :value). S’il s’agit bien d’un second acte réellement réalisé, cochez la case ci-dessous et déclarez de nouveau. La mutuelle en sera informée pour contrôle.', ['value' => session('duplicate_of')]) }}</div>
  @endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('portal.declare.store', $check->id) }}" class="mt-6 max-w-2xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <label class="block text-sm font-medium">{{ __('Personne soignée *') }}
      <select name="beneficiary" required class="{{ $f }}">
        <option value="member">{{ __(':last_name :name (adhérent)', ['last_name' => $member->last_name, 'name' => $member->first_name]) }}</option>
        @foreach ($dependents as $d)<option value="{{ $d->id }}" @selected(old('beneficiary', $checkedDependent ?? null) == $d->id)>{{ __(':last_name :name (personne à charge)', ['last_name' => $d->last_name, 'name' => $d->first_name]) }}</option>@endforeach
      </select></label>
    <label class="block text-sm font-medium">{{ __('Acte *') }}
      <select name="act_type_id" required class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id') === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">{{ __('Date des soins *') }}<input type="date" name="care_date" required max="{{ now()->toDateString() }}" min="{{ now()->subDays(30)->toDateString() }}" value="{{ old('care_date', now()->toDateString()) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">{{ __('Montant total des soins (:currency) *', ['currency' => $currency->code]) }}<input name="total" required inputmode="decimal" maxlength="20" value="{{ old('total') }}" class="{{ $f }}" placeholder="{{ __('Ex. 15 000') }}"></label>
    </div>
    <div class="space-y-2.5">
      <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="emergency" value="1" @checked(old('emergency')) class="mt-1"> {{ __('Urgence : soins de stabilisation du patient') }}</label>
      <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="accident" value="1" @checked(old('accident')) class="mt-1"> {{ __('Accident') }}</label>
    </div>
    <p class="rounded-md bg-mist px-3 py-2 text-xs text-ink/60">{{ __('Une urgence n’est jamais retardée faute de réponse de la mutuelle : soignez d’abord, déclarez ensuite. La part de la mutuelle et la part à régler par le patient sont calculées selon la garantie de l’adhérent et affichées après l’enregistrement. La mutuelle valide ensuite la prise en charge ; vous pourrez alors en demander le paiement dans « Mes factures ». N’indiquez aucun diagnostic.') }}</p>
    @if (session('duplicate_of'))
      <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm_distinct" value="1" required class="mt-1"> {{ __('Je confirme qu’il s’agit d’un acte distinct, réellement réalisé, et non d’une seconde saisie de la prise en charge n° :value.', ['value' => session('duplicate_of')]) }}</label>
    @endif
    <button class="rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">{{ session('duplicate_of') ? __('Déclarer quand même') : __('Déclarer') }}</button>
  </form>
</x-layouts.tenant>
