{{-- Carte de membre au format carte bancaire (85,6 × 54 mm). QR = jeton signé, sans donnée médicale. --}}
@php
  $sub = $subscription;
  $logo = rtrim(config('app.url'), '/') . '/brand/mx-mark-128.webp';
@endphp
<div class="mx-card relative overflow-hidden rounded-[14px] bg-gradient-to-br from-[#0B2A5B] via-[#0B4A9C] to-[#1769D2] text-white shadow-xl" style="width:85.6mm;height:54mm">
  <svg class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 text-white/[.07]" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="50" fill="currentColor"/></svg>
  <div class="absolute inset-x-0 bottom-0 h-[3mm] bg-gradient-to-r from-[#1FA463] to-[#7BD389]"></div>
  <div class="relative flex h-full flex-col p-[3.2mm]">
    <div class="flex items-center gap-[2mm]">
      <span class="grid h-[7mm] w-[7mm] place-items-center rounded-[1.6mm] bg-white"><img src="{{ $logo }}" alt="" class="h-[5.6mm] w-[5.6mm]"></span>
      <div class="min-w-0 leading-tight"><div class="truncate text-[2.9mm] font-extrabold tracking-wide">{{ tenant('name') }}</div><div class="text-[1.9mm] uppercase tracking-[.2em] text-white/70">Carte de membre</div></div>
    </div>
    <div class="mt-auto flex items-end justify-between gap-[2mm] pb-[2mm]">
      <div class="min-w-0">
        <div class="truncate text-[3.6mm] font-bold leading-tight">{{ mb_strtoupper($member->last_name) }} {{ $member->first_name }}</div>
        <div class="mt-[.6mm] font-mono text-[2.6mm] tracking-wider text-white/85">{{ $member->member_code }}</div>
        <div class="mt-[1.2mm] text-[2.1mm] leading-snug text-white/75">{{ $sub->guaranteeVersion?->guarantee?->name }}<br>Valable depuis le {{ $sub->effective_date?->format('d/m/Y') }}</div>
      </div>
      <div class="shrink-0 rounded-[1.6mm] bg-white p-[1mm]" style="width:19mm;height:19mm">
        {!! \F9WebLtd\QrCode\Facades\QrCode::size(64)->margin(0)->generate(route('members.card.verify', ['t' => $token])) !!}
      </div>
    </div>
  </div>
</div>
