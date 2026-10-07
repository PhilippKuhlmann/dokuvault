@use('App\Models\Setting')
<div class="p-3 sm:p-5 space-y-6">
    <div>
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Agenten') }}</div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Vorgaben für die Agenten aller Kunden. Einzelne Agenten stellst du beim Kunden unter „Agent“ ein.') }}
        </p>
    </div>

    <x-panel class="max-w-3xl">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Melden') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Wie oft ein Agent meldet, solange beim Kunden nichts anderes eingestellt ist, und ab wann er als still gilt.') }}
        </p>

        <div class="space-y-6">
            <div>
                <x-input.label for="intervall" :value="__('Standardintervall')" />
                <div class="mt-1 flex items-center gap-2">
                    <x-input.select id="intervall" name="intervall" wire:model.live="intervall" class="w-48">
                        @foreach ($intervalle as $minuten => $beschriftung)
                            <option value="{{ $minuten }}">{{ __($beschriftung) }}</option>
                        @endforeach
                    </x-input.select>
                    <span wire:loading wire:target="intervall" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Gilt für alle Agenten ohne eigenes Intervall – auch für die schon installierten, ab ihrer nächsten Anfrage.') }}</p>
                <x-input.fehler feld="intervall" />
            </div>

            <x-einstellung.zahl feld="stillStunden" :label="__('Meldet nicht ab')" :einheit="__('Stunden ohne Meldung')"
                :min="Setting::ZAHLEN[Setting::AGENT_STILL_STUNDEN][1]" :max="Setting::ZAHLEN[Setting::AGENT_STILL_STUNDEN][2]"
                :hinweis="__('Danach steht der Agent auf dem Dashboard, auf der Agent-Seite und unter Statistik → Agenten als „meldet nicht“. Größer als das längste Intervall wählen, sonst ist ein Agent zwischen zwei Meldungen schon still.')" />
        </div>
    </x-panel>

    <x-panel class="max-w-3xl">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Tokens') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Ein Agent-Token liegt auf jedem Rechner, auf dem der Agent läuft. Eine kürzere Laufzeit begrenzt, wie lange ein verlorener Token nützt.') }}
        </p>

        <div class="space-y-6">
            <x-einstellung.zahl feld="tokenTage" :label="__('Gültigkeit neuer Tokens')" :einheit="__('Tage')"
                :min="1" :max="$tokenMaxTage"
                :hinweis="__('Vorschlag beim Erzeugen und die Laufzeit beim Erneuern.')" />
            <x-einstellung.zahl feld="tokenMaxTage" :label="__('Höchstens')" :einheit="__('Tage')"
                :min="Setting::ZAHLEN[Setting::AGENT_TOKEN_MAX_TAGE][1]" :max="Setting::ZAHLEN[Setting::AGENT_TOKEN_MAX_TAGE][2]"
                :hinweis="__('Ein späteres Ablaufdatum lässt sich beim Erzeugen nicht wählen. Bestehende Tokens behalten ihr Datum.')" />
        </div>
    </x-panel>
</div>
