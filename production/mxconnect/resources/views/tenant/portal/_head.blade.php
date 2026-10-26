{{-- En-tête commun du portail prestataire : établissement + état de la convention. --}}
<x-tenant.hero icon="shield" kicker="Portail prestataire" tone="teal">
  <x-slot:title>{{ $title }}</x-slot:title>
  <x-slot:subtitle>{{ $provider->name }}@if($provider->locality) · {{ $provider->locality }}@endif — {{ tenant('name') }}</x-slot:subtitle>
</x-tenant.hero>
@unless ($convention->inForce())
  <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">La convention de votre établissement avec la mutuelle n’est pas en vigueur : vous pouvez consulter votre historique, mais pas vérifier de droits ni déclarer de soins. Contactez la mutuelle.</div>
@endunless
