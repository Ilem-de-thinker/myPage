@extends('layouts.platform')

@section('title', 'Data Request — mypage.ng')
@section('description', 'Request access, correction, deletion or restriction of personal information held by mypage.ng.')

@section('content')
  <section class="section-y">
    <div class="shell max-w-2xl">
      <p class="t-meta">Privacy</p>
      <h1 class="t-display-2 mt-2 text-[var(--ink)]">Make a data request</h1>
      <p class="t-body mt-4 text-[var(--ink-2)]">Use this form to ask mypage.ng for access, correction, deletion or restriction of personal information held by the platform. We will contact you to verify the request.</p>

      @if (session('status'))
        <div class="mt-6 rounded-[var(--radius)] border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-4 py-3 text-sm text-[var(--brand-text)]">{{ session('status') }}</div>
      @endif

      @if ($errors->any())
        <div class="mt-6 rounded-[var(--radius)] border border-[var(--danger-edge)] bg-[var(--danger-wash)] px-4 py-3 text-sm text-[var(--danger-text)]">
          <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('legal.data-request.store') }}" class="mt-8 space-y-5 rounded-2xl border border-[var(--rule)] bg-[var(--card)] p-6 shadow-[var(--shadow-rest)]">
        @csrf
        <div>
          <label for="name" class="block text-xs font-bold text-[var(--ink)]">Name</label>
          <input id="name" name="name" value="{{ old('name') }}" required class="mt-1 h-11 w-full rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] px-3 text-sm text-[var(--ink)]">
        </div>
        <div>
          <label for="email" class="block text-xs font-bold text-[var(--ink)]">Email</label>
          <input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1 h-11 w-full rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] px-3 text-sm text-[var(--ink)]">
        </div>
        <div>
          <label for="request_type" class="block text-xs font-bold text-[var(--ink)]">Request type</label>
          <select id="request_type" name="request_type" required class="mt-1 h-11 w-full rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] px-3 text-sm text-[var(--ink)]">
            <option value="access">Access my information</option>
            <option value="correction">Correct my information</option>
            <option value="deletion">Delete my information</option>
            <option value="restriction">Restrict processing</option>
          </select>
        </div>
        <div>
          <label for="details" class="block text-xs font-bold text-[var(--ink)]">Details (optional)</label>
          <textarea id="details" name="details" rows="4" maxlength="2000" class="mt-1 w-full rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] p-3 text-sm text-[var(--ink)]">{{ old('details') }}</textarea>
        </div>
        <button class="rounded-[var(--radius)] bg-[var(--brand)] px-5 py-3 text-sm font-bold text-[var(--brand-ink)]">Submit request</button>
      </form>
    </div>
  </section>
@endsection
