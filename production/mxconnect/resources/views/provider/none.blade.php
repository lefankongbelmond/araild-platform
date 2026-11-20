<x-layouts.provider title="{{ __('Aucun établissement') }}" :bare="true">
  <div class="mx-auto max-w-md rounded-3xl bg-white p-6 text-center shadow-soft ring-1 ring-ink/[.05] sm:p-8">
    <h1 class="text-xl font-bold text-[#0B1F3A]">{{ __('Aucun établissement actif') }}</h1>
    <p class="mt-2 text-sm text-ink/60">{{ __('Bonjour :name, votre compte n’est rattaché à aucun établissement actif (accès retiré par son administrateur, ou établissement désactivé). Contactez le responsable de votre établissement.', ['name' => $account->first_name]) }}</p>
  </div>
</x-layouts.provider>
