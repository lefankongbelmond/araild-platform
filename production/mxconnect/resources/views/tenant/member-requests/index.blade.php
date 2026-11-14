<x-layouts.tenant title="Demandes des adhérents">
  @php $K = \App\Models\Tenant\MemberChangeRequest::KINDS; $S = \App\Models\Tenant\MemberChangeRequest::STATUSES; $rel = ['spouse' => 'Conjoint(e)', 'child' => 'Enfant', 'adopted_child' => 'Enfant adopté', 'ascendant' => 'Parent', 'other' => 'Autre']; @endphp
  <x-tenant.hero icon="inbox" kicker="Adhérents · portail adhérent" tone="brand">
    <x-slot:title>Demandes des adhérents</x-slot:title>
    <x-slot:subtitle>Changements demandés par les adhérents depuis leur portail : coordonnées, ajout, modification ou retrait d’un bénéficiaire. Rien n’est appliqué avant votre décision ; un bénéficiaire ajouté est couvert à partir du jour de la validation. Demandez au besoin un justificatif au guichet.</x-slot:subtitle>
  </x-tenant.hero>
  <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="Filtrer">
    @foreach (['requested', 'approved', 'rejected'] as $k)
      <a href="{{ route('member-requests.index', ['statut' => $k]) }}" @class(['rounded-full px-3 py-1', 'bg-pine text-white' => $status === $k, 'bg-white text-ink/70 ring-1 ring-ink/10' => $status !== $k])>{{ $S[$k] }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
  </nav>
  <div class="mt-4 space-y-3">
    @forelse ($items as $r)
      @php $p = $r->payload; @endphp
      <article class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0"><b>{{ $K[$r->kind] ?? $r->kind }}</b>
            <div class="text-xs text-ink/60"><a href="{{ route('members.show', $r->member_id) }}" class="text-pine hover:underline">{{ $r->member?->last_name }} {{ $r->member?->first_name }} ({{ $r->member?->member_code }})</a> · {{ $r->created_at?->format('d/m/Y H:i') }}@if ($r->dependent) · bénéficiaire : {{ $r->dependent->first_name }} {{ $r->dependent->last_name }}@endif</div></div>
          <span class="text-xs text-ink/45">n° {{ $r->id }}</span>
        </div>
        <ul class="mt-2 text-ink/80">
          @foreach (['last_name' => 'Nom', 'first_name' => 'Prénom', 'birth_date' => 'Date de naissance', 'sex' => 'Sexe', 'relationship' => 'Lien', 'is_student' => 'Élève', 'phone' => 'Téléphone', 'address' => 'Adresse', 'note' => 'Message'] as $k => $l)
            @if (isset($p[$k]) && $p[$k] !== '')<li><span class="text-ink/50">{{ $l }} :</span> {{ $k === 'relationship' ? ($rel[$p[$k]] ?? $p[$k]) : ($k === 'is_student' ? 'oui' : ($k === 'birth_date' ? \Illuminate\Support\Carbon::parse($p[$k])->format('d/m/Y') : $p[$k])) }}</li>@endif
          @endforeach
        </ul>
        @if ($r->status === 'requested')
          <form method="POST" action="{{ route('member-requests.decide', $r) }}" class="mt-3 grid gap-2 sm:grid-cols-3">@csrf
            <input name="note" maxlength="500" placeholder="Motif (obligatoire pour un refus)" class="rounded-md border-ink/20 px-3 py-2 text-sm sm:col-span-2">
            <div class="flex gap-2">
              <button name="decision" value="approved" class="rounded-md bg-pine px-3 py-2 text-xs font-semibold text-white hover:bg-navy">Accepter</button>
              <button name="decision" value="rejected" class="rounded-md border border-clay/40 px-3 py-2 text-xs font-semibold text-clay hover:bg-mist">Refuser</button>
            </div>
          </form>
        @else
          <p class="mt-2 text-xs text-ink/55">{{ $S[$r->status] }} le {{ $r->decided_at?->format('d/m/Y') }}@if ($r->decision_note) · {{ $r->decision_note }}@endif</p>
        @endif
      </article>
    @empty
      <p class="rounded-lg border border-ink/10 bg-white p-8 text-center text-sm text-ink/50">Aucune demande.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.tenant>
