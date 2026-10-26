<a href="{{ route('social.post', $p) }}" style="color:inherit;display:block">
  <div class="mx-post__head" style="padding-top:12px">
    <x-mx.avatar :src="$p->authorAvatar()" :name="$p->authorName()" :org="(bool) $p->mutual_id" size="sm" />
    <div class="mx-grow"><b class="mx-small">{{ $p->authorName() }}</b><div class="mx-post__meta">{{ $p->published_at?->diffForHumans() }}</div></div>
  </div>
  @if ($p->body)<div class="mx-post__body mx-small">{{ \Illuminate\Support\Str::limit($p->body, 280) }}</div>@endif
  @php $m0 = $p->media->first(); $src = $m0 ? ($m0->isVideo() ? $m0->posterUrl() : $m0->thumbUrl()) : null; @endphp
  @if ($src)<img src="{{ $src }}" alt="" loading="lazy" style="width:100%;max-height:280px;object-fit:cover">@endif
</a>
