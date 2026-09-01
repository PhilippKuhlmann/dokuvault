@props(['checked' => false])

{{-- Der Haken verlangt die zweite Stufe, richtet sie aber nicht ein: Ein
     Geheimnis kann nur der Benutzer selbst mit seiner App verbinden. Bis er
     das getan hat, kommt er nur bis zu seinem Profil. --}}
<div class="mt-4">
    <label class="flex cursor-pointer select-none items-start gap-3">
        <input type="checkbox" name="two_factor_required" value="1"
            {{ old('two_factor_required', $checked) ? 'checked' : '' }}
            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
        <span class="text-sm">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Zweite Stufe der Anmeldung verlangen') }}</span>
            <span class="mt-0.5 block text-gray-600 dark:text-gray-400">
                {{ __('Der Benutzer kommt nach der Anmeldung nur bis zu seinem Profil, bis er eine Authentifizierungs-App verbunden hat.') }}
            </span>
        </span>
    </label>
</div>
