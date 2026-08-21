<x-admin-layout>

    @php
        $eventLabels = [
            'created' => ['Erstellt', 'text-green-700 bg-green-50 dark:text-green-400 dark:bg-green-900/30'],
            'updated' => ['Geändert', 'text-cerulean-700 bg-cerulean-50 dark:text-cerulean-400 dark:bg-gray-700'],
            'deleted' => ['Gelöscht', 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-900/30'],
            'restored' => ['Wiederhergestellt', 'text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/30'],
            'password_changed' => ['Kennwort geändert', 'text-purple-700 bg-purple-50 dark:text-purple-300 dark:bg-purple-900/30'],
        ];

        // Welches Kennwort - ein Gerät kann mehrere haben. Der Wert steht nie
        // dabei, nur das Feld.
        $feldNamen = config('custom.secret_field_labels');
    @endphp

    <div class="m-3">
        <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">{{ __('Aktivitäten') }}</div>

        {{-- Scrollen statt abschneiden: Mit overflow-hidden lagen bei 839 Pixeln
             25 Pixel der Details-Spalte ausserhalb des Rahmens, und die Seite
             selbst lief seitlich ueber. Seit dort mehr steht als "anzeigen"
             faellt das auf. --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full min-w-[38rem] text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs uppercase tracking-wide text-gray-500 bg-gray-50 border-b border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                    <tr>
                        <th class="py-2.5 px-4 font-semibold">{{ __('Zeitpunkt') }}</th>
                        <th class="py-2.5 px-4 font-semibold">{{ __('Benutzer') }}</th>
                        <th class="py-2.5 px-4 font-semibold">{{ __('Ereignis') }}</th>
                        <th class="py-2.5 px-4 font-semibold">{{ __('Objekt') }}</th>
                        <th class="py-2.5 px-4 font-semibold">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        @php
                            [$label, $badge] = $eventLabels[$activity->event] ?? [ucfirst($activity->event ?? '—'), 'text-gray-600 bg-gray-100 dark:text-gray-300 dark:bg-gray-700'];
                            $attrs = $activity->properties['attributes'] ?? [];
                            $old = $activity->properties['old'] ?? [];
                            $felder = $activity->properties['felder'] ?? [];
                            $verlaufIds = $activity->properties['verlauf_ids'] ?? [];
                            $objectName = $attrs['name'] ?? $old['name'] ?? $activity->properties['objekt'] ?? ('#' . $activity->subject_id);
                        @endphp
                        <tr x-data="{ open: false }" class="bg-white border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700/50">
                            <td class="py-2.5 px-4 whitespace-nowrap">{{ $activity->created_at->format('d.m.Y H:i') }}</td>
                            <td class="py-2.5 px-4 text-gray-900 dark:text-gray-100">{{ $activity->causer?->name ?? 'System' }}</td>
                            <td class="py-2.5 px-4">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $badge }}">{{ $label }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-gray-900 dark:text-gray-100">
                                {{ class_basename($activity->subject_type) }} <span class="text-gray-400">{{ is_scalar($objectName) ? $objectName : '' }}</span>
                            </td>
                            <td class="py-2.5 px-4">
                                @if (count($verlaufIds))
                                    {{-- Das bisherige Kennwort steht nicht im
                                         Protokolleintrag, sondern kommt auf Klick aus
                                         der Historie - und laeuft mit deren Frist ab. --}}
                                    <livewire:protokoll-kennwort :ids="$verlaufIds" :felder="$felder"
                                        :key="'pw-'.$activity->id" />
                                @elseif (count($felder))
                                    {{-- Kein alter Wert vorhanden - dann wenigstens
                                         sagen, welches Kennwort gemeint war. --}}
                                    <span class="text-xs text-gray-500">
                                        {{ collect($felder)->map(fn ($f) => __($feldNamen[$f] ?? $f))->join(', ') }}
                                    </span>
                                @elseif (count($attrs) || count($old))
                                    <button type="button" @click="open = !open" class="text-cerulean-600 hover:text-cerulean-700 text-sm">
                                        <span x-show="!open">anzeigen</span>
                                        <span x-show="open" x-cloak>verbergen</span>
                                    </button>
                                    <div x-show="open" x-cloak class="mt-2 space-y-1">
                                        @foreach ($attrs as $field => $new)
                                            @continue($field === 'name' && $activity->event === 'updated' && ! array_key_exists('name', $old))
                                            <div class="text-xs">
                                                <span class="text-gray-500">{{ $field }}:</span>
                                                @if ($activity->event === 'updated' && array_key_exists($field, $old))
                                                    <span class="text-red-600 dark:text-red-400 line-through">{{ is_scalar($old[$field]) ? $old[$field] : json_encode($old[$field]) }}</span>
                                                    →
                                                @endif
                                                <span class="text-gray-900 dark:text-gray-100">{{ is_scalar($new) ? $new : json_encode($new) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 px-4 text-center text-gray-400">{{ __('Noch keine Aktivitäten') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Die Seitenzahlen stehen in einer Zeile nebeneinander: Bei 856
             Einträgen sind das 18 Knöpfe und 633 Pixel, mehr als das Fenster
             breit ist - ohne eigenen Scrollbereich lief die ganze Seite über. --}}
        <div class="mt-4 mb-10 overflow-x-auto">
            {{ $activities->links() }}
        </div>
    </div>

</x-admin-layout>
