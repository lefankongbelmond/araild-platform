<x-layouts.social title="Réels" active="reels" :bare="true">
  <main class="mx-reels" id="contenu" aria-label="Réels" data-reels>
    @foreach (['ok' => 'ok', 'error' => 'err'] as $key => $cls)
      @if (session($key))<div class="mx-alert mx-alert--{{ $cls }} mx-reels__alert" role="status">{{ session($key) }}</div>@endif
    @endforeach
    @if (! $first && $reels->isEmpty())
      <div class="mx-reels__empty">
        <x-mx.icon name="reel" :size="44"/>
        <h1>Aucun réel pour le moment</h1>
        <p>Les réels sont de courtes vidéos verticales ({{ config('mxconnect.social.reel_max_seconds', 90) }} secondes maximum).</p>
        @auth('member')<a class="mx-btn mx-btn--primary" href="{{ route('social.home') }}#composer"><x-mx.icon name="video" :size="16"/> Publier le premier réel</a>@endauth
      </div>
    @else
      @include('social.partials.reels')
    @endif
    <a class="mx-reels__back" href="{{ route('social.home') }}" aria-label="Retour au fil"><x-mx.icon name="arrow-left" :size="20"/></a>
    @auth('member')<a class="mx-reels__new" href="{{ route('social.home') }}#composer" aria-label="Publier un réel"><x-mx.icon name="plus" :size="20"/></a>@endauth
  </main>
</x-layouts.social>
