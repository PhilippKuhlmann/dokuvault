<div class="p-3 sm:p-5 space-y-6">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Sicherheit') }}</div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Kennwörter') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Gilt für die Kennwörter, mit denen sich Benutzer anmelden — im eigenen Profil, beim Anlegen durch einen Administrator, beim Zurücksetzen und beim Einlösen einer Einladung.') }}
        </p>

        <div>
            <x-input.label for="pwMin" :value="__('Mindestlänge')" />
            <x-input.field id="pwMin" type="number" min="8" max="64"
                wire:model.live.debounce.600ms="pwMin" class="mt-1 w-40" />

            <div class="mt-1 flex items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Acht Zeichen sind die Vorgabe des Gerüsts — sie galt hier bisher, ohne dass es irgendwo stand.') }}
                </p>
                <span wire:loading wire:target="pwMin" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('pwMin')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6 space-y-3 border-t border-gray-100 pt-5 dark:border-gray-700">
            @foreach ([
                ['pwMixed', __('Groß- und Kleinbuchstaben verlangen'), null],
                ['pwNumbers', __('Mindestens eine Ziffer verlangen'), null],
                ['pwSymbols', __('Mindestens ein Sonderzeichen verlangen'), null],
                ['pwUncompromised', __('Gegen bekannte Datenlecks prüfen'),
                    __('Fragt eine fremde Liste bekannter Datenlecks. Ist sie nicht erreichbar, lässt die Prüfung durch — sie fällt still aus, statt zu blockieren.')],
            ] as [$feld, $label, $fussnote])
                <label class="flex cursor-pointer select-none items-start gap-3" wire:key="regel-{{ $feld }}">
                    <input type="checkbox" wire:model.live="{{ $feld }}"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    <span class="text-sm">
                        <span class="text-gray-900 dark:text-gray-100">{{ $label }}</span>
                        @if ($fussnote)
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $fussnote }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>

        {{-- Derselbe Satz, den die Benutzer unter ihrem Kennwortfeld sehen. Ohne
             ihn müsste man die Häkchen im Kopf zusammensetzen. --}}
        <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-700/40">
            <p class="text-gray-500 dark:text-gray-400">{{ __('Benutzer lesen dann unter dem Kennwortfeld:') }}</p>
            <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $hinweis }}</p>
        </div>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Bestehende Kennwörter bleiben gültig — die Regel greift, sobald jemand ein neues setzt. Eine Verschärfung sperrt also niemanden aus.') }}
        </p>
    </div>
</div>
