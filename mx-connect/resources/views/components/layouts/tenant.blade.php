<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MX-CONNECT' }} · {{ tenant('name') ?? 'Mutuelle' }}</title>
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22><rect width=%2232%22 height=%2232%22 rx=%228%22 fill=%22%230B2559%22/><path d=%22M6 24V9l6 8 6-8v15%22 fill=%22none%22 stroke=%22%2343B75D%22 stroke-width=%222.6%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/></svg>') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { colors: {
        ink:'#0B1F3A', pine:'#123C78', navy:'#0B2559', 'navy-deep':'#071A3D',
        sage:'#2FAE53', green:'#3AA655', mist:'#EAF6EE', clay:'#C0392B', paper:'#F7FAF8'
      }}}}
    </script>
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
  <div class="min-h-screen flex flex-col">
    <header class="border-b border-ink/10 bg-white/90 backdrop-blur sticky top-0 z-10">
      <div class="mx-auto max-w-6xl px-5 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-navy to-sage text-white text-xs font-bold">MX</span>
          <span class="font-semibold tracking-tight">{{ tenant('name') ?? 'Mutuelle' }}</span>
        </div>
        @auth
        <form method="POST" action="{{ url('/deconnexion') }}" class="flex items-center gap-3">
          @csrf
          <span class="text-sm text-ink/60">{{ auth()->user()->name }}</span>
          <button class="text-sm text-clay hover:underline">{{ __('mxconnect.auth.logout') }}</button>
        </form>
        @endauth
      </div>
      {{-- Parameter sub-nav --}}
      <nav class="mx-auto max-w-6xl px-5 flex gap-5 text-sm">
        @foreach ([
          'members.index'        => 'mxconnect.member.plural',
          'subscriptions.index'  => 'mxconnect.subscription.plural',
          'contributions.index'  => 'mxconnect.contribution.plural',
          'claims.index'         => 'mxconnect.claim.plural',
          'alerts.index'         => 'mxconnect.alert.plural',
          'treasury.dashboard'   => 'mxconnect.treasury.title',
          'accounting.journal'   => 'mxconnect.accounting.journal',
          'audit.index'          => 'mxconnect.audit.title',
          'solvency.dashboard'   => 'mxconnect.solvency.nav',
          'quality.index'        => 'mxconnect.quality.nav',
          'risks.index'          => 'mxconnect.risk.nav',
          'incidents.index'      => 'mxconnect.incident.nav',
          'complaints.index'     => 'mxconnect.complaint.nav',
          'guarantees.index' => 'mxconnect.guarantee.plural',
          'antennas.index'   => 'mxconnect.param.antennas',
          'act-types.index'  => 'mxconnect.param.act_types',
          'medicines.index'  => 'mxconnect.param.medicines',
        ] as $r => $label)
          @if (Route::has($r))
            <a href="{{ route($r) }}"
               class="py-2 border-b-2 {{ request()->routeIs($r) ? 'border-pine text-pine' : 'border-transparent text-ink/60 hover:text-ink' }}">
              {{ __($label) }}
            </a>
          @endif
        @endforeach
      </nav>
    </header>

    <div class="mx-auto w-full max-w-6xl px-5">
      @foreach (['success' => 'pine', 'error' => 'clay'] as $key => $c)
        @if (session($key))
          <div class="mt-4 rounded-md border border-{{ $c }}/30 bg-{{ $c }}/5 px-4 py-3 text-sm text-{{ $c }}">{{ session($key) }}</div>
        @endif
      @endforeach
    </div>

    <main class="mx-auto w-full max-w-6xl px-5 py-8 flex-1">{{ $slot }}</main>
    <footer class="border-t border-ink/10 py-5 text-center text-xs text-ink/40">MX-CONNECT</footer>
  </div>
</body>
</html>
