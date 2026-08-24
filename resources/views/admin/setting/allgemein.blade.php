<x-admin-layout>
    <div class="p-3 sm:p-5 space-y-6">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Allgemein') }}</div>

        {{-- enctype: Ohne das kommt die Datei nicht an, das Formular schickt
             dann nur den Dateinamen als Text. --}}
        <form method="POST" action="{{ route('admin.allgemein.update') }}" enctype="multipart/form-data"
            class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            @csrf

            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Name und Logo') }}</div>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Beides steht in der Kopfzeile, auf der Anmeldeseite und im PDF-Export.') }}
            </p>

            <div>
                <x-input.label for="app_name" :value="__('Name')" />
                <x-input.field id="app_name" name="app_name" class="mt-1 w-full"
                    value="{{ old('app_name', $name) }}" placeholder="{{ $standardName }}" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Leer lassen für den Namen aus der Konfiguration:') }} <span class="font-mono">{{ $standardName }}</span>
                </p>
                @error('app_name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Drei Felder statt eines: Das Logo auf der Anmeldeseite darf
                 gross und breit sein, das in der Kopfzeile muss neben den
                 Namen passen, ein Favicon ist quadratisch. Wer nur eine Datei
                 hat, laedt sie dreimal hoch. --}}
            @foreach ($stellen as $s)
                <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-700">
                    <x-input.label :for="'logo_'.$s['stelle']" :value="__('Logo').' — '.__($s['label'])" />
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __($s['hinweis']) }}</p>

                    @if ($s['vorhanden'])
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            {{-- Auf kariertem Grund: Ein Logo mit transparentem
                                 Rand sieht auf Weiss aus, als haette es keinen. --}}
                            <span class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700"
                                style="background-image: linear-gradient(45deg, #eee 25%, transparent 25%), linear-gradient(-45deg, #eee 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #eee 75%), linear-gradient(-45deg, transparent 75%, #eee 75%); background-size: 12px 12px; background-position: 0 0, 0 6px, 6px -6px, -6px 0;">
                                <img src="{{ route('branding.logo', $s['stelle']) }}?v={{ now()->timestamp }}"
                                    alt="{{ __($s['label']) }}" class="h-10 w-auto max-w-[12rem] object-contain" />
                            </span>

                            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <input type="checkbox" name="entfernen_{{ $s['stelle'] }}" value="1"
                                    class="rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:bg-gray-700 dark:border-gray-600" />
                                {{ __('Entfernen') }}
                            </label>
                        </div>
                    @endif

                    <input type="file" id="logo_{{ $s['stelle'] }}" name="logo_{{ $s['stelle'] }}"
                        accept="image/png,image/jpeg,image/webp"
                        class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-cerulean-600 file:px-4 file:py-2 file:text-sm file:font-DINPro-bold file:text-white hover:file:bg-cerulean-700 dark:text-gray-300" />

                    @error('logo_'.$s['stelle'])
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Erlaubt sind :formate, höchstens 512 KB. PNG mit transparentem Hintergrund passt in hellem und dunklem Erscheinungsbild.', ['formate' => strtoupper(implode(', ', $formate))]) }}
            </p>
            {{-- Kein SVG: Eine SVG-Datei darf Skript enthalten, und von
                 derselben Herkunft ausgeliefert waere das ausfuehrbarer Code
                 auf jeder Seite. --}}

            <div class="mt-6 flex items-center gap-3">
                <x-input.button type="submit" :label="__('Speichern')" />

                @if (session('success'))
                    <span class="text-sm text-green-600 dark:text-green-400">{{ session('success') }}</span>
                @endif
            </div>
        </form>
    </div>
</x-admin-layout>
