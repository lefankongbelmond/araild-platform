{{-- En-tête commun du portail prestataire : établissement + état de la convention. --}}
<x-tenant.hero icon="shield" kicker="Portail prestataire" tone="teal">
  <x-slot:title>{{ $title }}</x-slot:title>
  <x-slot:subtitle>{{ $provider->name }}@if($provider->locality) · {{ $provider->locality }}@endif — {{ tenant('name') }}</x-slot:subtitle>
</x-tenant.hero>
@unless ($portal['act'] ?? $convention->inForce())
  <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">La convention de votre établissement avec la mutuelle n’est pas en vigueur : vous pouvez consulter votre historique, mais pas vérifier de droits ni déclarer de soins.
    @if ($portal['bill'] ?? false) Vous pouvez encore demander le paiement des soins déjà validés par la mutuelle. @endif Contactez la mutuelle.</div>
@endunless
@if (! empty($portal['space']))
  <p class="mt-3 text-sm"><a href="{{ $portal['space'] }}" class="font-semibold text-brand-700 hover:underline">← Retour à l’espace prestataire</a> <span class="text-ink/50">(tableau de bord, établissement, autres mutuelles)</span></p>
@endif
