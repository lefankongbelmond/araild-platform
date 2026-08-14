<x-layouts.tenant :title="__('mxconnect.complaint.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.complaint.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.complaint.sub') }}</p>

  <div class="mt-4 flex gap-2 text-sm">
    <a href="{{ route('complaints.index') }}" class="rounded-md px-3 py-1.5 {{ !$status ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.all') }}</a>
    @foreach (['received','in_progress','resolved','closed'] as $st)
      <a href="{{ route('complaints.index', ['status'=>$st]) }}" class="rounded-md px-3 py-1.5 {{ $status===$st ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.complaint.st_'.$st) }}</a>
    @endforeach
  </div>

  <div class="mt-4 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.complaint.ref') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.complaint.subject') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.complaint.due') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($complaints as $c)
            <tr>
              <td class="px-4 py-2 font-mono text-xs"><a href="{{ route('complaints.show',$c) }}" class="text-pine hover:underline">{{ $c->ref }}</a></td>
              <td class="px-4 py-2">{{ $c->subject }}<div class="text-xs text-ink/40">{{ $c->member?->member_code ?? __('mxconnect.complaint.anonymous') }} · {{ __('mxconnect.complaint.ch_'.$c->channel) }}</div></td>
              <td class="px-4 py-2 {{ $c->isOverdue() ? 'text-clay font-medium' : 'text-ink/60' }}">{{ $c->due_on?->format('d/m/Y') }}{{ $c->isOverdue() ? ' ⚠' : '' }}</td>
              <td class="px-4 py-2 text-ink/60">{{ __('mxconnect.complaint.st_'.$c->status) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.complaint.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('complaints.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.complaint.new') }}</h2>
      <input name="subject" placeholder="{{ __('mxconnect.complaint.subject') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <textarea name="description" placeholder="{{ __('mxconnect.complaint.field_desc') }}" rows="2" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"></textarea>
      <select name="channel" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['in_person','phone','written','other'] as $ch)<option value="{{ $ch }}">{{ __('mxconnect.complaint.ch_'.$ch) }}</option>@endforeach
      </select>
      <input name="member_id" type="number" placeholder="{{ __('mxconnect.complaint.member_id') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <label class="block text-xs text-ink/60">{{ __('mxconnect.complaint.received') }}<input name="received_on" type="date" value="{{ date('Y-m-d') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      <p class="text-xs text-ink/40">{{ __('mxconnect.complaint.sla_note', ['n' => config('mxconnect.governance.complaint_sla_days')]) }}</p>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.complaint.add') }}</button>
    </form>
  </div>
  <div class="mt-4">{{ $complaints->links() }}</div>
</x-layouts.tenant>
