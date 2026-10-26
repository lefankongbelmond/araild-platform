{{-- Sondage d'une publication. Résultats = totaux anonymes. $poll, $post, $myVote (id du choix du visiteur ou null). --}}
@php
  $viewer = auth('member')->user();
  $open = $poll->isOpen();
  $showResults = ! $open || $myVote || ! $viewer;
@endphp
<div class="mx-poll" id="sondage-{{ $post->id }}">
  @if ($open && $viewer && ! $myVote)
    <form method="POST" action="{{ route('social.poll.vote', $post) }}" class="mx-stack" style="gap:8px">
      @csrf
      <fieldset class="mx-stack" style="gap:8px;border:0;margin:0;padding:0">
        <legend class="mx-sr">Votre choix</legend>
        @foreach ($poll->options as $o)
          <label class="mx-poll__choice"><input type="radio" name="option" value="{{ $o->id }}" required> <span>{{ $o->label }}</span></label>
        @endforeach
      </fieldset>
      <div><button class="mx-btn mx-btn--primary mx-btn--sm">Voter</button></div>
    </form>
  @else
    <div class="mx-stack" style="gap:8px">
      @foreach ($poll->options as $o)
        @php $pc = $poll->percent($o); @endphp
        <div @class(['mx-poll__result', 'is-mine' => (int) $myVote === $o->id])>
          <span class="mx-poll__fill" style="width:{{ $pc }}%"></span>
          <span class="mx-poll__label">{{ $o->label }}@if((int) $myVote === $o->id) <x-mx.icon name="check" :size="14"/>@endif</span>
          <b>{{ $pc }} %</b>
        </div>
      @endforeach
    </div>
    @if ($open && $myVote)
      <form method="POST" action="{{ route('social.poll.vote', $post) }}" class="mx-row mx-row--wrap" style="gap:6px;margin-top:8px">
        @csrf
        <label class="mx-sr" for="pv-{{ $post->id }}">Changer mon vote</label>
        <select class="mx-select" id="pv-{{ $post->id }}" name="option" style="width:auto;height:34px;padding:0 30px 0 10px;font-size:13px">@foreach ($poll->options as $o)<option value="{{ $o->id }}" @selected((int) $myVote === $o->id)>{{ $o->label }}</option>@endforeach</select>
        <button class="mx-btn mx-btn--ghost mx-btn--sm">Changer mon vote</button>
      </form>
    @endif
  @endif
  <p class="mx-xs mx-muted" style="margin-top:8px">{{ $poll->votes_count }} vote{{ $poll->votes_count > 1 ? 's' : '' }} ·
    @if ($open) se termine {{ $poll->closes_at?->diffForHumans() }} @else sondage terminé @endif
    @unless ($viewer) · <a href="{{ route('social.login') }}">connectez-vous pour voter</a>@endunless · résultats anonymes</p>
</div>
