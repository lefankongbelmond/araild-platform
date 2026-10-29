{{-- Maquette 14 — Mon profil et paramètres : uniquement des rubriques qui existent réellement. --}}
@php
  $rows = [
    ['edit', 'Modifier mon profil', route('social.settings'), null],
    ['share-out', 'Inviter un ami', route('social.invite'), 'WhatsApp, Facebook, SMS, e-mail…'],
    ['list', 'Mes publications', route('social.user', $me), null],
    ['building', 'Mes organisations', route('social.pages.mine'), 'Pages que je gère'],
    ['groups', 'Mes groupes', route('social.groups'), null],
    ['calendar', 'Mes événements', route('social.events'), 'Événements à venir du réseau'],
    ['briefcase', 'Mes opportunités', route('social.requests'), 'Candidatures, engagements et soutiens'],
    ['file', 'Mes adhésions', route('social.adhesions'), null],
    ['bookmark', 'Enregistrements', route('social.saved'), null],
    ['users', 'Mes connexions', route('social.connections'), 'Abonnés et abonnements'],
    ['settings', 'Paramètres et confidentialité', route('social.settings'), null],
    ['shield', 'Conditions et confidentialité', route('social.legal.history'), $legalPending ? $legalPending . ' document(s) à valider' : 'Documents et acceptations'],
    ['help', 'Centre d’aide', route('social.help'), 'Questions fréquentes et retours bêta'],
  ];
@endphp
<x-layouts.social title="Mon profil" active="menu">
  <section class="mx-card mx-card__pad" style="text-align:center">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h1 style="font-size:20px;margin:0">Mon profil</h1>
      <a class="mx-iconbtn" href="{{ route('social.settings') }}" aria-label="Paramètres"><x-mx.icon name="settings" :size="20"/></a>
    </div>
    <div style="display:flex;justify-content:center;margin:12px 0 8px"><x-mx.avatar :src="$me->avatarUrl()" :name="$me->name" size="lg" /></div>
    <b style="font-size:19px;color:var(--mx-heading)">{{ $me->name }}</b>
    <div class="mx-small mx-muted">Membre depuis {{ $me->created_at?->translatedFormat('F Y') }}</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);margin-top:14px">
      <a href="{{ route('social.user', $me) }}" style="text-decoration:none;color:inherit"><b style="display:block;font-size:18px">{{ $posts }}</b><span class="mx-xs mx-muted">Publications</span></a>
      <a href="{{ route('social.connections') }}" style="text-decoration:none;color:inherit"><b style="display:block;font-size:18px">{{ $followers }}</b><span class="mx-xs mx-muted">Abonnés</span></a>
      <a href="{{ route('social.connections') }}" style="text-decoration:none;color:inherit"><b style="display:block;font-size:18px">{{ $following }}</b><span class="mx-xs mx-muted">Abonnements</span></a>
    </div>
  </section>
  <nav class="mx-card" style="margin-top:12px" aria-label="Mon espace">
    @foreach ($rows as [$icon, $label, $url, $sub])
      <a href="{{ $url }}" style="display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--mx-border);text-decoration:none;color:var(--mx-text)">
        <x-mx.icon :name="$icon" :size="20"/><span style="flex:1;min-width:0"><span style="display:block;font-weight:600">{{ $label }}</span>@if ($sub)<span class="mx-xs mx-muted">{{ $sub }}</span>@endif</span><x-mx.icon name="chevron-right" :size="18"/>
      </a>
    @endforeach
    <form method="POST" action="{{ route('social.logout') }}">@csrf
      <button style="display:flex;align-items:center;gap:12px;padding:13px 16px;width:100%;border:0;background:none;color:var(--mx-danger);font:600 15px Inter,system-ui,sans-serif;cursor:pointer"><x-mx.icon name="logout" :size="20"/> Déconnexion</button>
    </form>
  </nav>
</x-layouts.social>
