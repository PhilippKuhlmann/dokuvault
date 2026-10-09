<div class="p-3 sm:p-5 space-y-6">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Benachrichtigungen') }}</div>

    <x-panel class="max-w-3xl">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Ablaufende Einträge') }}</div>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Per Mail, täglich am Morgen.') }}
            {{ __('Gilt für alle Empfänger. Jeder Eintrag kommt einmal, wenn er in die Vorwarnzeit fällt, und noch einmal, wenn er abgelaufen ist.') }}
        </p>

        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ([
                'certificate' => __('Zertifikate'),
                'domain' => __('Domains'),
                'licensesoftware' => __('Software-Lizenzen'),
                'warranty' => __('Garantien der Geräte'),
            ] as $kind => $label)
                <label class="flex cursor-pointer select-none items-center gap-2" wire:key="kind-{{ $kind }}">
                    <input type="checkbox" value="{{ $kind }}" wire:model.live="kinds"
                        class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <x-input.fehler feld="kinds" />

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Die Vorwarnzeit steht unter') }}
            <a href="{{ route('admin.fristen.index') }}" class="text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Fristen') }}</a>,
            {{ __('der Mailserver unter') }}
            <a href="{{ route('admin.mail.index') }}" class="text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Mail') }}</a>.
        </p>

        <div class="mt-4 flex items-center gap-3">
            <x-input.button type="button" color="gray" :label="__('Vorschau an mich senden')"
                wire:click="sendPreview" wire:loading.attr="disabled" wire:target="sendPreview" />
            <span wire:loading wire:target="sendPreview" class="text-xs text-gray-400 dark:text-gray-500">{{ __('sendet …') }}</span>
        </div>
        <x-input.fehler feld="preview" />
    </x-panel>

    <x-panel polster="keins" class="max-w-3xl">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div>
                <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Empfänger') }}</div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ match ($recipients) { 0 => __('Noch niemand.'), 1 => __('1 Benutzer bekommt die Mail.'), default => __(':anzahl Benutzer bekommen die Mail.', ['anzahl' => $recipients]) } }}
                    {{ __('Benutzer mit nur einem Kunden sehen nur dessen Einträge.') }}
                </p>
            </div>
            <x-input.field type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Benutzer suchen')" class="w-56" />
        </div>

        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="border-y border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Benutzer') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Kunden') }}</th>
                    <th class="px-4 py-2.5 font-semibold text-right">{{ __('Per Mail') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                        <td class="px-4 py-2.5">
                            <div class="text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                            <div class="text-xs">{{ $user->email ?: __('keine Mailadresse') }}</div>
                        </td>
                        <td class="px-4 py-2.5">{{ $user->customer?->name ?? __('Alle Kunden') }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @if ($user->istDeaktiviert() || ! $user->email)
                                <span class="text-xs">{{ $user->istDeaktiviert() ? __('deaktiviert') : '—' }}</span>
                            @else
                                <input type="checkbox" @checked($user->expiry_mail) wire:click="toggle({{ $user->id }})"
                                    aria-label="{{ __('Mail zu ablaufenden Einträgen für :name', ['name' => $user->name]) }}"
                                    class="h-4 w-4 cursor-pointer rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($users->hasPages())
            <div class="px-4 py-3">{{ $users->links() }}</div>
        @endif
    </x-panel>
</div>
