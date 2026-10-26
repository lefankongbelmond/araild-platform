{{-- Liste paginée par curseur. Rendue seule (fragment) pour « Charger plus ». --}}
@foreach ($posts as $i => $post)
  @include('social.partials.post', ['post' => $post, 'state' => $state])
  @if ($i === 2 && ! empty($sponsored))
    @include('social.partials.post', ['post' => $sponsored->post, 'state' => $state, 'sponsor' => $sponsored])
  @endif
@endforeach
@if ($posts->hasMorePages())
  <div class="mx-row" style="justify-content:center" data-more-wrap>
    <a class="mx-btn mx-btn--ghost" href="{{ $posts->nextPageUrl() }}" data-more>Charger plus de publications</a>
  </div>
@endif
