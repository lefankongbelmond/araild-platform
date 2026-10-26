<x-layouts.social :title="\Illuminate\Support\Str::limit($post->body ?: $post->authorName(), 60)">
  <x-slot:head>
    <meta property="og:title" content="{{ $post->authorName() }} · MX-CONNECT">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($post->body, 180) }}">
    @if ($post->media->isNotEmpty())<meta property="og:image" content="{{ url($post->media->first()->url()) }}">@endif
  </x-slot:head>
  <a class="mx-small" href="{{ route('social.home') }}"><x-mx.icon name="arrow-left" :size="14"/> Retour au fil</a>
  @include('social.partials.post', ['post' => $post, 'state' => $state, 'detail' => true, 'repostOf' => $repostOf ?? null])
</x-layouts.social>
