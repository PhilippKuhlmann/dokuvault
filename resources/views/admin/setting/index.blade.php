<x-admin-layout>
    <div class="p-3 sm:p-5 space-y-6">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Einstellungen') }}</div>

        <form method="POST" action="{{ route('admin.setting.update') }}"
            class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700"
            x-data="{ tool: '{{ old('remote_tool', $aktuell) }}' }">
            @csrf
            @method('PATCH')

            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Fernwartung') }}</div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Bestimmt, was der Verbinden-Knopf in den Geräteliste öffnet und wie die Felder am Gerät heißen.') }}
            </p>

            <div class="space-y-2">
                @foreach ($tools as $key => $tool)
                    <label class="flex items-start gap-3 p-3 rounded-lg border transition cursor-pointer
                        border-gray-200 hover:border-cerulean-300 dark:border-gray-700 dark:hover:border-cerulean-500"
                        :class="tool === '{{ $key }}' ? 'border-cerulean-500 bg-cerulean-50 dark:bg-cerulean-900/20' : ''">
                        <input type="radio" name="remote_tool" value="{{ $key }}" x-model="tool"
                            class="mt-1 text-cerulean-600 focus:ring-cerulean-500 dark:bg-gray-700 dark:border-gray-600" />
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 font-DINPro-bold text-gray-900 dark:text-gray-100">
                                <x-dynamic-component :component="$tool['icon']" class="h-5 w-5 !fill-cerulean-600 text-cerulean-600 dark:!fill-cerulean-400 dark:text-cerulean-400" />
                                {{ $tool['label'] }}
                            </span>

                            @if ($tool['url'])
                                <span class="block mt-1 font-mono text-xs text-gray-500 break-all dark:text-gray-400">{{ $tool['url'] }}</span>
                            @endif

                            {{-- Der wichtigste Hinweis auf dieser Seite: Nur RustDesk
                                 übergibt das Kennwort im Link. --}}
                            @if ($tool['url'] && ! str_contains($tool['url'], '{password}'))
                                <span class="block mt-1 text-xs text-amber-600 dark:text-amber-400">
                                    {{ __('Öffnet die Verbindung ohne Kennwort — es steht weiterhin zum Kopieren am Gerät.') }}
                                </span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>

            <div x-show="tool === 'custom'" x-cloak class="mt-4">
                <x-input.label for="remote_pattern" :value="__('URL-Muster')" />
                <x-input.field id="remote_pattern" name="remote_pattern" class="mt-1 w-full"
                    value="{{ old('remote_pattern', $muster) }}" placeholder="meintool://connect?id={id}" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('{id} wird durch die Kennung des Geräts ersetzt, {password} durch das Kennwort. Ein Muster ohne {password} ist der Normalfall.') }}
                </p>
                @error('remote_pattern')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5 flex items-center gap-3">
                <x-input.button type="submit" :label="__('Speichern')" />

                @if (session('success'))
                    <span class="text-sm text-green-600 dark:text-green-400">{{ session('success') }}</span>
                @endif
            </div>
        </form>
    </div>
</x-admin-layout>
