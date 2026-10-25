<x-admin.layout title="Platform settings">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-text">Control room</p>
      <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight">Platform settings</h1>
      <p class="mt-2 max-w-2xl text-sm text-ink-2">
        Shape the public mypage.ng identity and decide which advanced storefront
        tools sellers can use.
      </p>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
        class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf

    <div class="space-y-6">
      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Public identity</h2>
        <p class="mt-1 text-sm text-ink-3">This appears in the platform header, footer, and page metadata.</p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="text-xs font-semibold">Platform name</span>
            <input name="platform_name" value="{{ old('platform_name', $settings['platform_name']) }}" required
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
            @error('platform_name') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>
          <label class="block">
            <span class="text-xs font-semibold">Support email</span>
            <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email']) }}"
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
            @error('support_email') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>
        </div>

        <label class="mt-4 block">
          <span class="text-xs font-semibold">Platform tagline</span>
          <textarea name="platform_tagline" rows="3" required
                    class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">{{ old('platform_tagline', $settings['platform_tagline']) }}</textarea>
          @error('platform_tagline') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
        </label>
      </section>

      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Seller subscriptions</h2>
        <p class="mt-1 text-sm text-ink-3">When disabled, sellers keep their storefronts and dashboard access without subscription restrictions. Existing subscription records are preserved.</p>
        <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4">
          <input type="hidden" name="subscriptions_enabled" value="0">
          <input type="checkbox" name="subscriptions_enabled" value="1" @checked(old('subscriptions_enabled', $subscriptionsEnabled)) class="mt-0.5 size-4 accent-brand">
          <span><span class="block text-sm font-semibold">Allow seller subscriptions</span><span class="mt-1 block text-xs text-ink-3">Turn this back on to restore plan selection, payment and plan-based restrictions.</span></span>
        </label>
      </section>

      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Theme availability</h2>
        <p class="mt-1 text-sm text-ink-3">These switches control what new and existing storefronts may use.</p>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
          @foreach ([
              'store_theme_enabled' => ['Store theme', 'Retail catalog and checkout experience.'],
              'service_theme_enabled' => ['Business theme', 'Services, staff, and booking experience.'],
              'store_carousel_enabled' => ['Store carousel', 'Allow sellers to manage multiple hero slides.'],
              'service_carousel_enabled' => ['Business carousel', 'Allow studios and providers to manage hero slides.'],
              'store_hero_styles_enabled' => ['Store hero styles', 'Expose premium hero layouts and alignment controls.'],
              'service_hero_styles_enabled' => ['Business hero styles', 'Expose premium hero layouts and alignment controls.'],
          ] as $key => [$label, $description])
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
              <input type="hidden" name="{{ $key }}" value="0">
              <input type="checkbox" name="{{ $key }}" value="1"
                     @checked(old($key, $settings[$key])) class="mt-0.5 size-4 accent-brand">
              <span>
                <span class="block text-sm font-semibold">{{ $label }}</span>
                <span class="mt-1 block text-xs leading-relaxed text-ink-3">{{ $description }}</span>
              </span>
            </label>
          @endforeach
        </div>
        @error('themes') <p class="mt-3 text-xs text-danger-text">{{ $message }}</p> @enderror
      </section>
    </div>

    <aside class="h-fit rounded-2xl border border-rule bg-card p-6 xl:sticky xl:top-6">
      <h2 class="text-base font-bold">Platform logo</h2>
      <p class="mt-1 text-sm text-ink-3">A small square mark for the public platform shell.</p>

      @if ($logoPath)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
             alt="Current platform logo" class="mt-5 size-28 rounded-2xl border border-rule object-cover">
        <label class="mt-3 flex items-center gap-2 text-xs text-ink-2">
          <input type="checkbox" name="remove_logo" value="1" class="size-4 accent-brand">
          Remove current logo
        </label>
      @else
        <div class="mt-5 flex size-28 items-center justify-center rounded-2xl bg-ink text-sm font-bold text-white">Logo</div>
      @endif

      <label class="mt-5 block">
        <span class="text-xs font-semibold">Upload a new logo</span>
        <input type="file" name="platform_logo" accept="image/png,image/jpeg,image/webp"
               class="mt-2 block w-full text-xs text-ink-2">
      </label>
      @error('platform_logo') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror

      <button class="mt-6 w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-brand-ink hover:opacity-90">
        Save platform settings
      </button>
    </aside>
  </form>
</x-admin.layout>
