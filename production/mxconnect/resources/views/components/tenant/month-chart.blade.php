@props(['months', 'series', 'currency'])
{{--
  Colonnes groupées par mois (2026-11-04), en SVG pur : un seul axe, deux séries au plus,
  barres ≤ 24 px à bout arrondi partant de la ligne de base, grille fine, légende toujours
  présente, info-bulle au survol de chaque mois (title), tableau des valeurs juste dessous.
  $series = [clé => [libellé, couleur]] ; couleurs validées (contraste, daltonisme).
--}}
@php
  $keys = array_keys($series);
  $n = max(count($months), 1);
  $max = max(1, ...array_map(fn ($m) => max(array_map(fn ($k) => (int) $m[$k], $keys)), array_values($months) ?: [array_fill_keys($keys, 0)]));
  // Graduations « rondes » : 1, 2 ou 5 × 10^k
  $raw = $max / 4; $pow = 10 ** floor(log10($raw)); $step = collect([1, 2, 5, 10])->map(fn ($x) => $x * $pow)->first(fn ($x) => $x >= $raw);
  $top = $step * ceil($max / $step);
  [$W, $H, $L, $B, $T] = [720, 240, 56, 28, 8];   // largeur, hauteur, marge gauche (axe), bas (mois), haut
  $plotW = $W - $L - 8; $plotH = $H - $B - $T;
  $band = $plotW / $n; $bw = min(24, max(4, ($band - 10) / count($keys) - 2));
  $y = fn ($v) => $T + $plotH - ($v / $top) * $plotH;
  $unit = 10 ** $currency->minor_unit;
  $compact = function ($minor) use ($unit) {
      $v = $minor / $unit;
      return $v >= 1e9 ? round($v / 1e9, 1) . ' Md' : ($v >= 1e6 ? round($v / 1e6, 1) . ' M' : ($v >= 1e3 ? round($v / 1e3, 1) . ' k' : (string) round($v)));
  };
  $every = $n > 12 ? 3 : ($n > 6 ? 2 : 1);   // une étiquette de mois sur 1, 2 ou 3 selon la place
  $bar = function ($x, $v, $w) use ($y, $T, $plotH) {
      $y0 = $T + $plotH; $y1 = $y($v); $h = $y0 - $y1;
      if ($h <= 0) { return ''; }
      $r = min(4, $h, $w / 2);
      return "M{$x},{$y0}V" . ($y1 + $r) . "Q{$x},{$y1} " . ($x + $r) . ",{$y1}H" . ($x + $w - $r) . "Q" . ($x + $w) . ",{$y1} " . ($x + $w) . "," . ($y1 + $r) . "V{$y0}Z";
  };
@endphp
<div {{ $attributes }}>
  <div class="flex flex-wrap gap-4 text-xs text-ink/70" aria-hidden="true">
    @foreach ($series as $k => [$label, $color])
      <span class="flex items-center gap-1.5"><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $color }}"></span>{{ $label }}</span>
    @endforeach
  </div>
  <div class="relative overflow-x-auto">
  <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Évolution mensuelle : {{ implode(', ', array_column($series, 0)) }}. Valeurs détaillées dans le tableau ci-dessous."
       style="display:block;width:100%;min-width:560px;height:auto;margin-top:8px;font-family:inherit">
    @for ($v = 0; $v <= $top; $v += $step)
      <line x1="{{ $L }}" x2="{{ $W - 8 }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="#E6E8EC" stroke-width="1"/>
      <text x="{{ $L - 6 }}" y="{{ $y($v) + 4 }}" text-anchor="end" font-size="11" fill="#6B7280">{{ $compact($v) }}</text>
    @endfor
    @foreach (array_values($months) as $i => $m)
      @php $x0 = $L + $i * $band; $gx = $x0 + ($band - count($keys) * ($bw + 2) + 2) / 2; @endphp
      <g>
        <title>{{ $m['label'] }} — @foreach ($series as $k => [$label]){{ $label }} : {{ $currency->format((int) $m[$k]) }}@if (! $loop->last) · @endif @endforeach</title>
        <rect x="{{ $x0 }}" y="{{ $T }}" width="{{ $band }}" height="{{ $plotH }}" fill="transparent"/>
        @foreach ($keys as $j => $k)
          <path d="{{ $bar($gx + $j * ($bw + 2), (int) $m[$k], $bw) }}" fill="{{ $series[$k][1] }}"/>
        @endforeach
        @if ($i % $every === 0)
          <text x="{{ $x0 + $band / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#6B7280">{{ $m['label'] }}</text>
        @endif
      </g>
    @endforeach
    <line x1="{{ $L }}" x2="{{ $W - 8 }}" y1="{{ $T + $plotH }}" y2="{{ $T + $plotH }}" stroke="#C9CDD4" stroke-width="1"/>
  </svg>
  </div>
</div>
