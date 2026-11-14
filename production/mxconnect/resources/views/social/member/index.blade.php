<x-layouts.social title="Mon adhésion" active="mon-adhesion">
  <h1 style="font-size:22px">Mon adhésion</h1>
  <p class="mx-small mx-muted">Retrouvez ici votre adhésion à une mutuelle : garanties, bénéficiaires, prestations, cotisations, carte de membre et réclamations.</p>

  @foreach ($links as $l)
    <a class="mx-card mx-card__pad mx-row" href="{{ route('social.member.dashboard', $l->mutual->slug) }}" style="color:inherit;gap:12px">
      <x-mx.avatar :name="$l->mutual->name" org />
      <span class="mx-grow"><b>{{ $l->mutual->name }}</b><span class="mx-xs mx-muted" style="display:block">Adhérent n° {{ $l->member_code }}</span></span>
      <span class="mx-btn mx-btn--primary mx-btn--sm">Ouvrir</span>
    </a>
  @endforeach

  <section class="mx-card mx-card__pad" style="margin-top:14px">
    <h2 class="mx-card__title" style="font-size:17px">{{ $links->isEmpty() ? 'Relier mon adhésion' : 'Relier une autre adhésion' }}</h2>
    <p class="mx-small mx-muted">Vous êtes inscrit au guichet de votre mutuelle ? Demandez-lui votre <b>code d’activation</b> (valable 30 jours, une seule fois), puis saisissez-le ici avec la date de naissance de l’adhérent principal.</p>
    @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('social.member.activate') }}" class="mx-grid-2" style="margin-top:10px">@csrf
      <label class="mx-field"><span class="mx-label">Code d’activation</span><input class="mx-input" name="code" required maxlength="20" autocomplete="off" placeholder="XXXX-XXXX-XXXX" style="text-transform:uppercase"></label>
      <label class="mx-field"><span class="mx-label">Date de naissance de l’adhérent</span><input class="mx-input" type="date" name="birth_date" required value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}"></label>
      <div><button class="mx-btn mx-btn--primary">Relier mon adhésion</button></div>
    </form>
    <p class="mx-xs mx-muted" style="margin-top:10px">Vous avez adhéré en ligne ? Votre adhésion apparaît ici dès que la mutuelle a relié votre compte à votre fiche. Pas encore adhérent : <a href="{{ route('social.mutuals') }}">choisir une mutuelle</a>.</p>
  </section>
</x-layouts.social>
