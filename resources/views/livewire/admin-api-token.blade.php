@use('App\Support\Zeit')
<div class="p-3 sm:p-5 space-y-4">

    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('API-Token') }}</div>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ $tokens->count() }} {{ __('Token') }}
        </span>
    </div>

    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
        {{ __('Ein Token spricht mit deinen Rechten. Er ersetzt Benutzername und Kennwort für Skripte und den Agenten — gib ihn entsprechend sparsam heraus und widerrufe ihn, sobald er nicht mehr gebraucht wird.') }}
    </p>

    {{-- Der Klartext steht nur dieses eine Mal da. Gespeichert wird ein Hash;
         wer ihn jetzt nicht mitnimmt, legt einen neuen an. Deshalb gross auf
         der Seite und nicht in einer Meldung, die von selbst verschwindet. --}}
    @if ($frischerToken)
        <div class="rounded-xl border border-amber-300 bg-amber-50/60 p-4 dark:border-amber-800/70 dark:bg-amber-900/10">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-amber-900 dark:text-amber-300">
                        {{ __('Nur jetzt sichtbar') }}
                    </div>
                    <p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-400/80">
                        {{ __('Kopiere ihn jetzt. Danach lässt er sich nicht mehr anzeigen — nur ein neuer anlegen.') }}
                    </p>
                </div>
                <button type="button" wire:click="verbergen"
                    class="shrink-0 text-xs text-amber-800 hover:text-amber-900 dark:text-amber-400">{{ __('verbergen') }}</button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ kopiert: false }">
                <code x-ref="tok"
                    class="min-w-0 flex-1 break-all rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-gray-900 dark:border-amber-900/60 dark:bg-gray-900 dark:text-gray-100">{{ $frischerToken }}</code>
                <button type="button"
                    x-on:click="copyText($refs.tok.textContent); kopiert = true; setTimeout(() => kopiert = false, 1500)"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-cerulean-600 px-3 py-2 text-sm font-DINPro-bold text-white shadow-sm transition-colors hover:bg-cerulean-700">
                    <span x-show="! kopiert">{{ __('Kopieren') }}</span>
                    <span x-show="kopiert" x-cloak>{{ __('Kopiert') }}</span>
                </button>
            </div>
        </div>
    @endif

    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <x-input.label :value="__('Bezeichnung')" />
                {{-- Ein Name, der sagt, wofür er da ist: Beim Widerrufen ist er
                     das Einzige, woran sich ein Token erkennen lässt. --}}
                <x-input.field wire:model="name" class="mt-1 w-full"
                    placeholder="{{ __('z. B. Agent Standort Hamburg') }}" />
            </div>
            <x-input.button type="button" size="feld" wire:click="anlegen" :label="__('Token anlegen')" />
        </div>

        @error('name')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    @if ($tokens->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="text-gray-500 dark:text-gray-400">{{ __('Noch kein Token angelegt') }}</div>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full min-w-[32rem] text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Bezeichnung') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Angelegt') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Zuletzt benutzt') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $token)
                        <tr wire:key="token-{{ $token->id }}"
                            class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ $token->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5" title="{{ Zeit::anzeigen($token->created_at) }}">
                                {{-- JUST_NOW, sonst steht beim frisch angelegten
                                     Token "vor 0 Sekunden" da. --}}
                                {{ $token->created_at->diffForHumans(['options' => Carbon\Carbon::JUST_NOW]) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                @if ($token->last_used_at)
                                    <span title="{{ Zeit::anzeigen($token->last_used_at) }}">{{ $token->last_used_at->diffForHumans(['options' => Carbon\Carbon::JUST_NOW]) }}</span>
                                @else
                                    {{-- Ein Token, der nie benutzt wurde, ist ein
                                         Kandidat zum Widerrufen. --}}
                                    <span class="text-gray-400 dark:text-gray-500">{{ __('nie') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" wire:click="widerrufen({{ $token->id }})"
                                    wire:confirm="{{ __('Diesen Token widerrufen? Was ihn benutzt, kommt danach nicht mehr herein.') }}"
                                    title="{{ __('Widerrufen') }}"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-red-600 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                                    <x-svg.trash class="h-5 w-5" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
