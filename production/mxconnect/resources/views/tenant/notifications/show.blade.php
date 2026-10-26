@php
  $text = $n->title . ' : ' . $n->body;
  $wa = function ($phone) use ($text) {
      $d = preg_replace('/\D+/', '', (string) $phone);
      if ($d === '') return null;
      return 'https://wa.me/' . $d . '?text=' . rawurlencode($text);
  };
@endphp
<x-layouts.tenant title="Suivi de notification">
  <a href="{{ route('member-notifications.index') }}" class="text-sm text-ink/50 hover:underline">&larr; Notifications aux membres</a>
  <x-tenant.hero icon="send" kicker="Suivi d’envoi" tone="leaf" class="mt-2">
    <x-slot:title>{{ $n->title }}</x-slot:title>
    <x-slot:subtitle>{{ $n->audience_label }} · {{ $n->created_at->format('d/m/Y à H:i') }}</x-slot:subtitle>
    <x-slot:actions>
      <a href="{{ route('member-notifications.export', $n) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-ink/70 hover:bg-paper"><x-tenant.icon name="download" :size="16"/> Export SMS (CSV)</a>
    </x-slot:actions>
  </x-tenant.hero>

  <div class="mt-5 grid grid-cols-3 gap-3">
    <x-tenant.kpi label="Adhérents ciblés" :value="$n->recipients" icon="users" tone="brand" />
    <x-tenant.kpi label="Reçus dans l’application" :value="$n->in_app" icon="globe" tone="leaf" />
    <x-tenant.kpi label="Joignables par téléphone" :value="$n->with_phone" icon="message" tone="teal" />
  </div>

  <x-tenant.panel class="mt-5" title="Message" icon="message">
    <p class="whitespace-pre-line px-6 pb-6 pt-3 text-sm text-ink/75">{{ $n->body }}</p>
  </x-tenant.panel>

  <x-tenant.panel class="mt-5" title="Destinataires" icon="users">
    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80"><th class="py-3 pl-6 pr-3">Adhérent</th><th class="px-3 py-3">Téléphone</th><th class="px-3 py-3">Application</th><th class="py-3 pl-3 pr-6 text-right">WhatsApp</th></tr></thead>
        <tbody class="divide-y divide-ink/[.06]">
          @foreach ($rows as $r)
            @php $m = $r->member; $link = $m ? $wa($m->phone) : null; @endphp
            <tr>
              <td class="py-3 pl-6 pr-3"><div class="font-semibold text-[#0B1F3A]">{{ $m ? $m->last_name . ' ' . $m->first_name : '—' }}</div><div class="font-mono text-xs text-ink/50">{{ $m?->member_code }}</div></td>
              <td class="px-3 py-3 text-ink/70">{{ $m?->phone ?: '—' }}</td>
              <td class="px-3 py-3">@if($r->in_app)<span class="rounded-full bg-leaf-50 px-2.5 py-1 text-xs font-bold text-leaf-700 ring-1 ring-leaf-100">Reçue</span>@else<span class="text-xs text-ink/45">Compte non relié</span>@endif</td>
              <td class="py-3 pl-3 pr-6 text-right">@if($link)<a href="{{ $link }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#25D366]/10 px-3 text-xs font-bold text-[#128C4A] hover:bg-[#25D366]/20">Envoyer sur WhatsApp</a>@else<span class="text-xs text-ink/40">—</span>@endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="px-6 py-4">{{ $rows->links() }}</div>
  </x-tenant.panel>
</x-layouts.tenant>
