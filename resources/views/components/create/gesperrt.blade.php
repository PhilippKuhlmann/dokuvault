@props(['checked' => false, 'seit' => null])

{{-- Gesperrt statt geloescht: An einem Benutzer haengen Protokolleintraege,
     und ein geloeschter Benutzer macht aus "Rita hat den Serverschrank
     geaendert" ein "jemand". Wer das Haus verlaesst, wird gesperrt - was er
     getan hat, bleibt lesbar.

     Der Satz darunter sagt, wie weit die Sperre reicht: Sie endet nicht an der
     Anmeldemaske, sondern beendet auch die laufende Sitzung und die
     API-Token. Ohne diesen Hinweis muesste man es ausprobieren. --}}
<div class="mt-4">
    <label class="flex cursor-pointer select-none items-start gap-3">
        <input type="checkbox" name="deactivated" value="1"
            {{ old('deactivated', $checked) ? 'checked' : '' }}
            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700">
        <span class="text-sm">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Zugang sperren') }}</span>
            <span class="mt-0.5 block text-gray-600 dark:text-gray-400">
                {{ __('Keine Anmeldung mehr. Eine laufende Sitzung endet beim nächsten Aufruf, API-Token werden abgewiesen. Der Benutzer bleibt mit allem, was er dokumentiert hat, erhalten.') }}
                @if ($seit)
                    <span class="mt-1 block font-mono text-xs">{{ __('gesperrt seit') }} {{ \App\Support\Zeit::anzeigen($seit, 'd.m.Y H:i') }}</span>
                @endif
            </span>
        </span>
    </label>
</div>
