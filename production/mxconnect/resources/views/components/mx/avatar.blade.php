@props(['src' => null, 'name' => '?', 'org' => false, 'size' => null])
@php
  $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<span {{ $attributes->class(['mx-avatar', 'mx-avatar--org' => $org, 'mx-avatar--'.$size => $size]) }}>
  @if ($src)<img src="{{ $src }}" alt="" loading="lazy">@else{{ $initials ?: '?' }}@endif
</span>
