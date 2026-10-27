@php $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
<x-layouts.tenant :title="__('mxconnect.accounting.chart')">
  @include('tenant.accounting._nav')
  <x-tenant.hero icon="book" kicker="Comptabilité" tone="violet">
    <x-slot:title>{{ __('mxconnect.accounting.chart') }}</x-slot:title>
    <x-slot:subtitle>Plan comptable SYSCOHADA de la mutuelle, par classe. Un compte qui a des écritures, figure dans un budget ou sert à la comptabilisation automatique ne peut pas être supprimé.</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  @if ($canManage)
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
      <form method="POST" action="{{ route('accounting.accounts.store') }}" class="min-w-0 rounded-lg border border-ink/10 bg-white p-4">@csrf
        <h2 class="font-medium">Ajouter un compte</h2>
        <div class="mt-2 flex flex-wrap items-end gap-2">
          <label class="text-xs text-ink/60">Numéro<input name="number" value="{{ old('number') }}" inputmode="numeric" maxlength="10" placeholder="ex. 6055" class="{{ $f }} mt-1 block" style="width:7rem"></label>
          <label class="min-w-0 flex-1 text-xs text-ink/60">Intitulé<input name="label" value="{{ old('label') }}" maxlength="180" class="{{ $f }} mt-1 block w-full"></label>
          <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">Ajouter</button>
        </div>
        <p class="mt-2 text-xs text-ink/50">La classe est le premier chiffre du numéro (1 à 8).</p>
      </form>
      <form method="POST" action="{{ route('accounting.accounts.import') }}" class="min-w-0 rounded-lg border border-ink/10 bg-white p-4">@csrf
        <h2 class="font-medium">Comptes usuels d’une mutuelle</h2>
        <p class="mt-1 text-sm text-ink/60">{{ count(\App\Support\SyscohadaAccounts::USUAL) }} comptes SYSCOHADA courants (fonds propres, réserves, report à nouveau, résultat, matériel, fournisseurs, personnel, charges et produits usuels), sans montant. {{ $missingUsual ? $missingUsual . ' ne sont pas encore dans votre plan.' : 'Ils sont tous déjà dans votre plan.' }} À adapter avec votre expert-comptable.</p>
        @if ($missingUsual)<button class="mt-3 rounded-md border border-ink/15 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Ajouter les comptes manquants</button>@endif
      </form>
    </div>
  @endif

  <div class="mt-6 space-y-4">
    @foreach ($accounts as $class => $lines)
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.accounting.class') }} {{ $class }}</h2>
        <table class="mt-2 w-full text-sm">
          <tbody class="divide-y divide-ink/5">
            @foreach ($lines as $a)
              <tr>
                <td class="py-1.5 pr-3 font-mono w-24 align-top">{{ $a->number }}</td>
                <td class="py-1.5">
                  @if ($canManage)
                    <details>
                      <summary class="cursor-pointer">{{ $a->label }}</summary>
                      <div class="mt-2 flex flex-wrap items-end gap-2">
                        <form method="POST" action="{{ route('accounting.accounts.update', $a) }}" class="flex min-w-0 flex-wrap items-end gap-2">@csrf @method('PUT')
                          <label class="min-w-0 text-xs text-ink/60">Nouvel intitulé<input name="label" value="{{ $a->label }}" maxlength="180" class="{{ $f }} mt-1 block" style="max-width:100%"></label>
                          <button class="rounded-md border border-ink/15 bg-white px-3 py-2 text-xs font-medium">Renommer</button>
                        </form>
                        @unless (isset($used[$a->id]))
                          <form method="POST" action="{{ route('accounting.accounts.destroy', $a) }}" onsubmit="return confirm('Supprimer le compte {{ $a->number }} ?')">@csrf @method('DELETE')
                            <button class="rounded-md border border-clay/30 bg-white px-3 py-2 text-xs font-medium text-clay">Supprimer</button>
                          </form>
                        @endunless
                      </div>
                    </details>
                  @else
                    {{ $a->label }}
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach
  </div>
</x-layouts.tenant>
