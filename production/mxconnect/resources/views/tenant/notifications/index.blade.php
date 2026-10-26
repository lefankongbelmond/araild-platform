@php
  $f = 'h-11 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
  $aud = old('audience', $prefill['audience'] ?? 'all');
@endphp
<x-layouts.tenant title="Notifications aux membres">
  <x-tenant.hero icon="send" kicker="Gestion des adhérents · Communication" tone="leaf">
    <x-slot:title>Notifications aux membres</x-slot:title>
    <x-slot:subtitle>Informez un adhérent ou un groupe d’adhérents : appel de cotisation, rappel, campagne de santé, assemblée générale… Chaque envoi est tracé.</x-slot:subtitle>
  </x-tenant.hero>

  <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-tenant.kpi label="Adhérents actifs" :value="number_format($stats['active'], 0, ',', ' ')" icon="users" tone="brand" />
    <x-tenant.kpi label="Joignables dans l’application" :value="number_format($stats['linked'], 0, ',', ' ')" icon="globe" tone="leaf" hint="compte du réseau relié à la fiche" />
    <x-tenant.kpi label="Avec numéro de téléphone" :value="number_format($stats['phones'], 0, ',', ' ')" icon="message" tone="teal" hint="WhatsApp ou SMS" />
    <x-tenant.kpi label="Destinataires notifiés" :value="number_format($stats['sent'], 0, ',', ' ')" icon="send" tone="violet" hint="depuis le début" />
  </div>

  <div class="mt-5 grid gap-5 xl:grid-cols-[400px_minmax(0,1fr)]">
    <x-tenant.panel title="Nouvelle notification" icon="plus" class="h-max">
      <form method="POST" action="{{ route('member-notifications.store') }}" class="space-y-4 p-5 sm:p-6">
        @csrf
        <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Destinataires <span class="text-clay">*</span></span>
          <select name="audience" class="{{ $f }}" data-audience>@foreach ($audiences as $k => $l)<option value="{{ $k }}" @selected($aud === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="block" data-show="member"><span class="mb-1.5 block text-[13px] font-semibold">Code de l’adhérent</span><input name="member_code" value="{{ old('member_code', $prefill['membre'] ?? '') }}" class="{{ $f }} font-mono" placeholder="Ex. MUT-000123"></label>
        <label class="block" data-show="antenna"><span class="mb-1.5 block text-[13px] font-semibold">Antenne</span><select name="antenna_id" class="{{ $f }}"><option value="">—</option>@foreach ($antennas as $a)<option value="{{ $a->id }}" @selected((string) old('antenna_id') === (string) $a->id)>{{ $a->name }}</option>@endforeach</select></label>
        <label class="block" data-show="guarantee"><span class="mb-1.5 block text-[13px] font-semibold">Garantie</span><select name="guarantee_id" class="{{ $f }}"><option value="">—</option>@foreach ($guarantees as $g)<option value="{{ $g->id }}" @selected((string) old('guarantee_id') === (string) $g->id)>{{ $g->name }}</option>@endforeach</select></label>
        <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Titre <span class="text-clay">*</span></span><input name="title" value="{{ old('title') }}" required maxlength="150" class="{{ $f }}" placeholder="Ex. Rappel : cotisation d’octobre"></label>
        <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Message <span class="text-clay">*</span></span>
          <textarea name="body" required maxlength="1000" rows="5" class="w-full rounded-xl border border-ink/10 px-3.5 py-2.5 text-sm" placeholder="Votre message, sans information médicale personnelle.">{{ old('body') }}</textarea></label>
        @if ($errors->any())<p class="text-xs text-clay">{{ $errors->first() }}</p>@endif
        <div class="rounded-xl bg-paper p-3 text-xs leading-relaxed text-ink/60">
          <b class="text-[#0B1F3A]">Canaux</b><br>
          • <b>Application MX-CONNECT</b> : immédiat pour les adhérents dont le compte est relié à leur fiche (et par e-mail s’ils l’acceptent).<br>
          • <b>WhatsApp</b> : lien d’envoi pour chaque adhérent, sur la page de suivi.<br>
          • <b>SMS</b> : export du fichier des numéros, à charger dans votre outil d’envoi. L’envoi automatique de SMS demande un fournisseur : il n’est pas encore raccordé.
        </div>
        <button class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 text-sm font-bold text-white shadow-lg shadow-leaf-500/25"><x-tenant.icon name="send" :size="17"/> Envoyer la notification</button>
      </form>
    </x-tenant.panel>

    <x-tenant.panel title="Historique des envois" icon="history">
      @if ($history->isEmpty())
        <p class="px-6 py-14 text-center text-sm text-ink/50">Aucune notification envoyée pour le moment.</p>
      @else
        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80"><th class="py-3 pl-6 pr-3">Notification</th><th class="px-3 py-3">Public</th><th class="px-3 py-3 text-right">Destinataires</th><th class="px-3 py-3 text-right">Application</th><th class="py-3 pl-3 pr-6"></th></tr></thead>
            <tbody class="divide-y divide-ink/[.06]">
              @foreach ($history as $n)
                <tr>
                  <td class="py-3 pl-6 pr-3"><div class="font-semibold text-[#0B1F3A]">{{ $n->title }}</div><div class="text-xs text-ink/50">{{ $n->created_at->format('d/m/Y H:i') }}</div></td>
                  <td class="px-3 py-3 text-ink/70">{{ $n->audience_label }}</td>
                  <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $n->recipients }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-leaf-700">{{ $n->in_app }}</td>
                  <td class="py-3 pl-3 pr-6 text-right"><a href="{{ route('member-notifications.show', $n) }}" class="font-semibold text-brand-600 hover:underline">Suivi</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="px-6 py-4">{{ $history->links() }}</div>
      @endif
    </x-tenant.panel>
  </div>

  <x-slot:scripts>
    <script>
      (function () {
        var s = document.querySelector('[data-audience]');
        var sync = function () { document.querySelectorAll('[data-show]').forEach(function (el) { el.hidden = el.getAttribute('data-show') !== s.value; }); };
        s.addEventListener('change', sync); sync();
      })();
    </script>
  </x-slot:scripts>
</x-layouts.tenant>
