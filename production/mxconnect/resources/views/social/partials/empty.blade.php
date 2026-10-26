<div class="mx-card mx-empty">
  <x-mx.icon :name="$icon ?? 'list'" :size="40" />
  <h3>{{ $title }}</h3>
  <p class="mx-small">{{ $text }}</p>
  @isset($action)<p style="margin-top:14px"><a class="mx-btn mx-btn--primary" href="{{ $action[1] }}">{{ $action[0] }}</a></p>@endisset
</div>
