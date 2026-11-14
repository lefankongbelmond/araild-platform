@php
  $S = \App\Models\Provider\ProviderAffiliation::STATUSES;
  $badge = ['approved' => 'bg-leaf-50 text-leaf-700', 'requested' => 'bg-amber-50 text-amber-700', 'suspended' => 'bg-clay/10 text-clay', 'rejected' => 'bg-clay/10 text-clay', 'withdrawn' => 'bg-mist text-ink/60'];
@endphp
<x-layouts.provider title="Conventions et tarifs">
  <x-tenant.hero icon="book" kicker="Conventions et tarifs" tone="teal">
    <x-slot:title>Mes mutuelles</x-slot:title>
    <x-slot:subtitle>Conventions en place, demandes d’affiliation et mutuelles disponibles. La convention de chaque mutuelle (dates, délais, conditions, tarifs) est tenue par la mutuelle elle-même.</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <x-tenant.panel class="mt-6" title="Conventions et demandes" icon="globe">
    <div class="relative overflow-x-auto px-2 pb-4">
      <table class="w-full text-sm">
        <thead class="text-left text-ink/50"><tr><th class="px-3 py-2 font-medium">Mutuelle</th><th class="px-3 py-2 font-medium">Situation</th><th class="px-3 py-2 font-medium">Depuis</th><th class="px-3 py-2"></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($affiliations as $a)
            <tr>
              <td class="px-3 py-3"><b>{{ $a->mutual?->name }}</b><div class="text-xs text-ink/50">{{ $a->mutual?->city }}</div></td>
              <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $badge[$a->status] ?? 'bg-mist' }}">{{ $S[$a->status] ?? $a->status }}</span>
                @if ($a->status === 'rejected' && $a->decision_note)<div class="mt-1 text-xs text-clay">Motif : {{ $a->decision_note }}</div>@endif</td>
              <td class="px-3 py-3 text-ink/60">{{ ($a->decided_at ?? $a->created_at)?->format('d/m/Y') }}</td>
              <td class="px-3 py-3 text-right">
                @if (in_array($a->status, ['approved', 'suspended'], true))<a href="{{ route('pro.mutuals.show', $a->mutual_id) }}" class="font-semibold text-pine hover:underline">Convention et tarifs</a>
                @elseif ($a->status === 'requested' && \App\Support\ProviderAccess::allowed('affiliation.request'))
                  <form method="POST" action="{{ route('pro.affiliations.withdraw', $a->id) }}" onsubmit="return confirm('Retirer cette demande ?')">@csrf<button class="text-xs text-clay hover:underline">Retirer la demande</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-3 py-8 text-center text-ink/50">Aucune convention ni demande pour l’instant.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-tenant.panel>

  @if (\App\Support\ProviderAccess::allowed('affiliation.request'))
    <x-tenant.panel class="mt-6" title="Demander une affiliation" icon="send">
      @if ($requestable->isEmpty())
        <p class="px-5 pb-5 text-sm text-ink/55 sm:px-6">Aucune autre mutuelle disponible dans le pays de l’établissement.</p>
      @else
        <form method="POST" action="{{ route('pro.affiliations.store') }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6">@csrf
          <label class="block text-sm font-medium">Mutuelle<select name="mutual_id" required class="mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm"><option value="">—</option>@foreach ($requestable as $mu)<option value="{{ $mu->id }}">{{ $mu->name }}@if($mu->city) · {{ $mu->city }}@endif</option>@endforeach</select></label>
          <label class="block text-sm font-medium sm:col-span-2">Message <span class="font-normal text-ink/50">(services, spécialités, agrément…)</span><textarea name="message" rows="3" maxlength="1000" class="mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm"></textarea></label>
          <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Envoyer la demande</button>
            <p class="mt-1 text-xs text-ink/50">La mutuelle vérifie l’établissement avant d’accepter ; elle peut refuser en indiquant un motif.</p></div>
        </form>
      @endif
    </x-tenant.panel>
  @endif
</x-layouts.provider>
