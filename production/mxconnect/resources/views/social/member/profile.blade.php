<x-layouts.social title="{{ __('Mon profil d’adhérent') }}" active="mon-adhesion">
  @include('social.member._head')
  @php $K = \App\Models\Tenant\MemberChangeRequest::KINDS; $S = \App\Models\Tenant\MemberChangeRequest::STATUSES; $viewer = auth('member')->user(); @endphp
  <section class="mx-card mx-card__pad">
    <div class="mx-row" style="gap:12px">
      <x-mx.avatar :src="$viewer?->avatarUrl()" :name="$m->first_name . ' ' . $m->last_name" />
      <div><b>{{ mb_strtoupper($m->last_name) }} {{ $m->first_name }}</b><span class="mx-xs mx-muted" style="display:block">{{ __('La photo est celle de votre compte MX-CONNECT (') }}<a href="{{ route('social.settings') }}">{{ __('la changer') }}</a>).</span></div>
    </div>
    <dl class="mx-kv" style="margin-top:12px">
      <dt>{{ __('N° d’adhérent') }}</dt><dd>{{ $m->member_code }}</dd>
      <dt>{{ __('Date de naissance') }}</dt><dd>{{ $m->birth_date?->format('d/m/Y') }}</dd>
      <dt>{{ __('Sexe') }}</dt><dd>{{ $m->sex === 'F' ? __('Féminin') : __('Masculin') }}</dd>
      <dt>{{ __('Téléphone') }}</dt><dd>{{ $m->phone ?: '—' }}</dd>
      <dt>{{ __('Adresse') }}</dt><dd>{{ $m->address ?: '—' }}</dd>
      <dt>{{ __('Formule') }}</dt><dd>{{ $subscription?->guaranteeVersion?->guarantee?->name ?? '—' }}</dd>
      <dt>{{ __('Pièce d’identité') }}</dt><dd>{{ __('Enregistrée par la mutuelle (non affichée, pour votre sécurité)') }}</dd>
    </dl>
    <div class="mx-row" style="gap:8px;flex-wrap:wrap;margin-top:12px">
      <a class="mx-btn mx-btn--soft mx-btn--sm" href="{{ route('social.member.attestation', $mutual->slug) }}">{{ __('Attestation d’adhésion') }}</a>
      <a class="mx-btn mx-btn--soft mx-btn--sm" href="{{ route('social.member.card', $mutual->slug) }}">{{ __('Carte de membre') }}</a>
    </div>
  </section>

  <section class="mx-card mx-card__pad" style="margin-top:14px">
    <h2 class="mx-card__title" style="font-size:16px">{{ __('Mettre à jour mes coordonnées') }}</h2>
    <p class="mx-xs mx-muted">{{ __('Votre demande est vérifiée par la mutuelle avant d’être appliquée. Pour un changement de nom ou de date de naissance, adressez-vous au guichet avec une pièce justificative.') }}</p>
    <form method="POST" action="{{ route('social.member.contact', $mutual->slug) }}" class="mx-grid-2" style="margin-top:8px">@csrf
      <label class="mx-field"><span class="mx-label">{{ __('Nouveau téléphone') }}</span><input class="mx-input" name="phone" maxlength="20" value="{{ old('phone') }}"></label>
      <label class="mx-field"><span class="mx-label">{{ __('Nouvelle adresse') }}</span><input class="mx-input" name="address" maxlength="255" value="{{ old('address') }}"></label>
      <div><button class="mx-btn mx-btn--primary mx-btn--sm">{{ __('Envoyer la demande') }}</button></div>
    </form>
  </section>

  <section class="mx-card mx-card__pad" style="margin-top:14px">
    <h2 class="mx-card__title" style="font-size:16px">{{ __('Historique de mes demandes') }}</h2>
    @forelse ($requests as $r)
      <p class="mx-small" style="margin:6px 0">{{ __($K[$r->kind] ?? $r->kind) }} <span class="mx-xs mx-muted">· {{ $r->created_at?->format('d/m/Y') }}</span>
        <span class="mx-badge mx-badge--{{ $r->status === 'approved' ? 'green' : ($r->status === 'rejected' ? 'danger' : 'warn') }}">{{ __($S[$r->status] ?? $r->status) }}</span>@if ($r->decision_note)<span class="mx-xs mx-muted" style="display:block">{{ $r->decision_note }}</span>@endif</p>
    @empty
      <p class="mx-small mx-muted">{{ __('Aucune demande.') }}</p>
    @endforelse
  </section>
</x-layouts.social>
