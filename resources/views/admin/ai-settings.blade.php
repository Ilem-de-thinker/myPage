<x-admin.layout title="AI settings">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-text">Control room</p>
      <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight">AI settings</h1>
      <p class="mt-2 max-w-2xl text-sm text-ink-2">
        Choose the provider and model every AI feature uses. The API key is encrypted,
        write-only, and never shown again after it is saved.
      </p>
    </div>

    <div class="rounded-2xl border border-rule bg-card px-4 py-3 text-xs">
      <span class="font-semibold {{ $values['enabled'] ? 'text-ok-text' : 'text-danger-text' }}">
        {{ $values['enabled'] ? 'AI enabled' : 'AI disabled' }}
      </span>
      <span class="mx-2 text-ink-3">·</span>
      <span class="text-ink-2">{{ $values['provider'] }} / {{ $values['model'] }}</span>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.ai-settings.update') }}" class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf

    <div class="space-y-6">
      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Provider</h2>
        <p class="mt-1 text-sm text-ink-3">
          This pair overrides the per-task provider and model from the environment.
          Set both to the provider you want every feature to use.
        </p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="text-xs font-semibold">Provider</span>
            <select name="provider" required class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
              @foreach ($providers as $value => $label)
                <option value="{{ $value }}" @selected(old('provider', $values['provider']) === $value)>{{ $label }}</option>
              @endforeach
            </select>
            @error('provider') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>

          <label class="block">
            <span class="text-xs font-semibold">Model</span>
            <input name="model" value="{{ old('model', $values['model']) }}" required
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
            @error('model') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>
        </div>

        <label class="mt-4 block">
          <span class="text-xs font-semibold">API key</span>
          <input type="password" name="api_key" value="" autocomplete="new-password"
                 placeholder="{{ $values['maskedKey'] ? 'Saved key: '.$values['maskedKey'].' — leave blank to keep it' : 'Paste the provider API key' }}"
                 class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
          <span class="mt-1 block text-xs text-ink-3">
            Leave blank to keep the current key. A saved key is never rendered in full.
          </span>
          @error('api_key') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
        </label>

        <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4">
          <input type="hidden" name="enabled" value="0">
          <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $values['enabled'])) class="mt-0.5 size-4 accent-brand">
          <span>
            <span class="block text-sm font-semibold">Enable AI across the platform</span>
            <span class="mt-1 block text-xs text-ink-3">Turning this off makes AI endpoints return 503 without changing merchant data.</span>
          </span>
        </label>
      </section>

      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Timeouts and retries</h2>
        <p class="mt-1 text-sm text-ink-3">A global bound prevents one slow provider from holding requests open indefinitely.</p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="text-xs font-semibold">Timeout (seconds)</span>
            <input type="number" name="timeout" min="1" max="120" required
                   value="{{ old('timeout', $values['timeout']) }}"
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
            @error('timeout') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>

          <label class="block">
            <span class="text-xs font-semibold">Retries</span>
            <input type="number" name="retries" min="0" max="5" required
                   value="{{ old('retries', $values['retries']) }}"
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
            <span class="mt-1 block text-xs text-ink-3">Number of retry attempts after a rate limit or provider error.</span>
            @error('retries') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Per-feature monthly caps</h2>
        <p class="mt-1 text-sm text-ink-3">
          Optional. Leave a field blank for no task-specific cap. Zero blocks that task
          without blocking other features. The plan-wide monthly cap still applies.
        </p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          @foreach ($tasks as $task => $label)
            <label class="block">
              <span class="text-xs font-semibold">{{ $label }}</span>
              <input type="number" name="feature_limits[{{ $task }}]" min="0" max="1000000"
                     value="{{ old('feature_limits.'.$task, $values['limits'][$task] ?? '') }}"
                     placeholder="No cap"
                     class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
              @error('feature_limits.'.$task) <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
            </label>
          @endforeach
        </div>
        @error('feature_limits') <p class="mt-3 text-xs text-danger-text">{{ $message }}</p> @enderror
      </section>
    </div>

    <aside class="h-fit space-y-4 xl:sticky xl:top-6">
      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Connection</h2>
        <p class="mt-1 text-sm text-ink-3">
          {{ $values['configured'] ? 'An API key is configured.' : 'No API key is configured yet.' }}
        </p>

        @if ($values['maskedKey'])
          <p class="mt-3 rounded-xl border border-rule bg-surface px-3 py-2 font-mono text-xs text-ink-2">{{ $values['maskedKey'] }}</p>
        @endif

        <button class="mt-5 w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-brand-ink hover:opacity-90">
          Save AI settings
        </button>
      </section>

      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Test connection</h2>
        <p class="mt-1 text-sm text-ink-3">Uses the saved provider, model, key, timeout, and retries.</p>

        <button form="ai-test-form" class="mt-5 w-full rounded-xl border border-rule-strong bg-surface px-4 py-3 text-sm font-bold text-ink hover:bg-tint">
          Test saved settings
        </button>
        @error('ai_test') <p class="mt-3 text-xs text-danger-text">{{ $message }}</p> @enderror
      </section>
    </aside>
  </form>

  <form id="ai-test-form" method="POST" action="{{ route('admin.ai-settings.test') }}" class="hidden">
    @csrf
  </form>
</x-admin.layout>
