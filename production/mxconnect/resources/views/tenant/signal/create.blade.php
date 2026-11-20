@php $f = 'w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
<x-layouts.tenant title="{{ __('Signaler un incident ou un risque') }}">
  <x-tenant.hero icon="alert" kicker="{{ __('Conformité et qualité') }}" tone="red">
    <x-slot:title>{{ __('Signaler un incident ou un risque') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Une erreur, une fraude soupçonnée, une fuite de données, un dysfonctionnement ? Signalez-le : l’administrateur et le contrôleur-validateur le traitent. Ne mettez aucune donnée médicale dans la description.') }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('incidents.store') }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">
      @csrf <input type="hidden" name="via" value="signal">
      <h2 class="font-semibold">{{ __('Un incident s’est produit') }}</h2>
      <label class="block text-sm font-medium">{{ __('Intitulé *') }}<input name="title" required maxlength="200" value="{{ old('title') }}" class="mt-1 {{ $f }}" placeholder="{{ __('Ex. reçu remis deux fois au même adhérent') }}"></label>
      <label class="block text-sm font-medium">{{ __('Ce qui s’est passé') }}<textarea name="description" rows="3" maxlength="4000" class="mt-1 {{ $f }}">{{ old('description') }}</textarea></label>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm font-medium">{{ __('Catégorie') }}<select name="category" class="mt-1 {{ $f }}">@foreach (['operational','security','fraud','data_breach','service','other'] as $c)<option value="{{ $c }}">{{ __('mxconnect.incident.cat_'.$c) }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('Gravité') }}<select name="severity" class="mt-1 {{ $f }}">@foreach (['low','medium','high','critical'] as $sv)<option value="{{ $sv }}" @selected($sv === 'medium')>{{ __('mxconnect.incident.sev_'.$sv) }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('Survenu le *') }}<input name="occurred_on" type="date" value="{{ date('Y-m-d') }}" required class="mt-1 {{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Constaté le') }}<input name="detected_on" type="date" value="{{ date('Y-m-d') }}" class="mt-1 {{ $f }}"></label>
      </div>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink">{{ __('Signaler l’incident') }}</button>
    </form>

    <form method="POST" action="{{ route('risks.store') }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">
      @csrf <input type="hidden" name="via" value="signal">
      <h2 class="font-semibold">{{ __('Un risque pourrait se produire') }}</h2>
      <label class="block text-sm font-medium">{{ __('Intitulé *') }}<input name="title" required maxlength="200" class="mt-1 {{ $f }}" placeholder="{{ __('Ex. une seule personne connaît le code du coffre') }}"></label>
      <label class="block text-sm font-medium">{{ __('Description') }}<textarea name="description" rows="3" maxlength="4000" class="mt-1 {{ $f }}"></textarea></label>
      <div class="grid gap-3 sm:grid-cols-3">
        <label class="block text-sm font-medium">{{ __('Catégorie') }}<select name="category" class="mt-1 {{ $f }}">@foreach (['operational','financial','compliance','security','strategic','other'] as $c)<option value="{{ $c }}">{{ __('mxconnect.risk.cat_'.$c) }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('Probabilité (1-5)') }}<select name="likelihood" class="mt-1 {{ $f }}">@foreach (range(1, 5) as $n)<option @selected($n === 3)>{{ $n }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('Impact (1-5)') }}<select name="impact" class="mt-1 {{ $f }}">@foreach (range(1, 5) as $n)<option @selected($n === 3)>{{ $n }}</option>@endforeach</select></label>
      </div>
      <button class="w-full rounded-md border border-pine px-4 py-2 text-sm font-medium text-pine hover:bg-mist">{{ __('Signaler le risque') }}</button>
    </form>
  </div>
  <p class="mt-4 text-xs text-ink/45">{{ __('Votre nom est joint au signalement.') }} @acces('incidents.index'){{ __('Vous pouvez suivre le traitement dans') }} <a class="text-pine underline" href="{{ route('incidents.index') }}">{{ __('Incidents') }}</a> {{ __('et') }} <a class="text-pine underline" href="{{ route('risks.index') }}">{{ __('Risques') }}</a>.@endacces</p>
</x-layouts.tenant>
