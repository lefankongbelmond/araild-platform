{{-- Paiement EFFECTUÉ hors plateforme (trésorier) : mode, référence, date. Phase 5 (2026-11-26). --}}
<details class="w-full text-left" style="max-width:22rem">
  <summary class="cursor-pointer rounded-md bg-pine px-3 py-1.5 text-center text-sm font-medium text-white">Enregistrer le paiement…</summary>
  <form method="POST" action="{{ route('invoices.pay', $inv->id) }}" class="mt-2 grid gap-2 rounded-md border border-ink/10 bg-white p-3"
        onsubmit="return window.confirm('Enregistrer le paiement de {{ $currency->format($inv->amount_minor) }} ? Seulement si le paiement a été effectué.')">@csrf
    <p class="text-xs text-ink/55">À enregistrer une fois le paiement effectué (hors de la plateforme).</p>
    <label class="text-xs text-ink/60">Mode<select name="mode" class="mt-1 block w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm">
      @foreach ($modes as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
    <label class="text-xs text-ink/60">Référence du paiement<input name="payment_reference" required minlength="3" maxlength="60" placeholder="N° de virement, de transaction ou de reçu" class="mt-1 block w-full rounded-md border border-ink/15 px-3 py-2 text-sm"></label>
    <label class="text-xs text-ink/60">Date du paiement<input type="date" name="paid_on" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-md border border-ink/15 px-3 py-2 text-sm"></label>
    <button class="rounded-md bg-pine px-3 py-2 text-sm font-medium text-white">Confirmer le paiement</button>
  </form>
</details>
