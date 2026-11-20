<x-layouts.social :title="$doc->label()" wide>
  <article class="mx-card mx-card__pad mx-stack" style="max-width:760px">
    <h1 style="font-size:26px">{{ $doc->label() }}</h1>
    <p class="mx-small mx-muted">{{ __('Version :version', ['version' => $doc->version]) }}@if ($doc->published_at) · {{ __('publiée le :date', ['date' => $doc->published_at->format('d/m/Y')]) }}@endif
      @if ($current && $current->id !== $doc->id) · <b>{{ __('ancienne version') }}</b> — <a href="{{ $current->url() }}">{{ __('voir la version en vigueur (:version)', ['version' => $current->version]) }}</a>@endif</p>
    @if ($doc->is_template)<div class="mx-legal__tpl">{{ __('MODÈLE — À FAIRE VALIDER : ce texte n’est pas définitif.') }}</div>@endif
    <div class="mx-legal">{!! \App\Services\Social\LegalService::render($doc->body) !!}</div>
    @if ($versions->count() > 1)
      <h2 style="font-size:16px">{{ __('Versions publiées') }}</h2>
      <ul class="mx-legal__list">
        @foreach ($versions as $v)<li><a href="{{ $v->url() }}">{{ __('Version :version', ['version' => $v->version]) }}</a> — {{ $v->published_at?->format('d/m/Y') }}@if ($v->id === $doc->id) {{ __('(affichée)') }}@endif</li>@endforeach
      </ul>
    @endif
  </article>
  <x-slot:head><link rel="stylesheet" href="/css/mx-app.css?v={{ config('mxconnect.asset_version') }}"></x-slot:head>
</x-layouts.social>
