<section id="benachrichtigungen">
    <header>
        <h2 class="font-mono text-[11px] uppercase tracking-[0.12em] text-cerulean-600 dark:text-cerulean-400">
            {{ __('Benachrichtigungen') }}
        </h2>

        <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            {{ auth()->user()->customer
                ? __('Eine Mail am Morgen, wenn bei :kunde Zertifikate, Domains, Lizenzen oder Garantien bald ablaufen oder abgelaufen sind.', ['kunde' => auth()->user()->customer->name])
                : __('Eine Mail am Morgen, wenn Zertifikate, Domains, Lizenzen oder Garantien bald ablaufen oder abgelaufen sind – über alle Kunden.') }}
        </p>
    </header>

    <label class="mt-4 flex cursor-pointer select-none items-center gap-2">
        <input type="checkbox" wire:model.live="enabled" @disabled(! auth()->user()->email)
            class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
        <span class="text-sm text-gray-900 dark:text-gray-100">{{ __('Ablaufende Einträge per Mail') }}</span>
    </label>

    @unless (auth()->user()->email)
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Dafür braucht Ihr Zugang eine Mailadresse.') }}</p>
    @endunless
</section>
