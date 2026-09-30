{{-- Ein Scan-Ziel in der Liste: wohin, womit man sich anmeldet und auf
     welchen Scannern es eingerichtet ist. --}}
    <x-card>
        <x-slot:head>
            <x-show.header can="scantarget_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'scantarget', id: {{ $eintrag->id }} })">
                {{ $eintrag->name ?: $eintrag->target }}

                    <x-slot:kernwerte>
                        <x-kernwert :label="__('Art')">{{ $eintrag->artName() }}</x-kernwert>
                    </x-slot>
                </x-show.header>
        </x-slot>

        <x-slot:body>

            <div class="w-full mb-5 break-inside-avoid">
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Ziel') }}</div>
                <div class="font-mono text-sm break-all text-gray-900 dark:text-gray-100">{{ $eintrag->target }}</div>
                @if ($eintrag->description)
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $eintrag->description }}</div>
                @endif
            </div>

            {{-- Die Anmeldung am Ziel steht bei den Zugangsdaten; hier nur der
                 Verweis. Das Kennwort kommt erst auf Klick vom Server, mit
                 demselben Recht, Protokoll und derselben Bremse wie dort. --}}
            @if ($eintrag->login)
                @can('logingeneral_viewAny')
                    <div class="w-full mb-5 break-inside-avoid">
                        <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Zugangsdaten') }}</div>
                        <table class="w-full text-sm">
                            <tr>
                                <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ $eintrag->login->name }}</td>
                                <td class="py-1 w-full align-top text-gray-900 dark:text-gray-100">
                                    @if ($eintrag->login->username)
                                        <div class="font-mono">{{ $eintrag->login->username }}</div>
                                    @endif
                                    @if ($eintrag->login->password)
                                        <livewire:geheim-feld :modell="\App\Models\LoginGeneral::class" :id="$eintrag->login->id" feld="password"
                                            width="w-24" :key="'scanziel-kw-'.$eintrag->id" />
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                @endcan
            @endif

            <div class="w-full mb-5 break-inside-avoid">
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Eingerichtet auf') }}</div>
                @forelse ($eintrag->scanners as $scanner)
                    <div class="text-sm">
                        @can('scanner_viewAny')
                            <a href="{{ route('scanner.index', [$customer, 'highlight' => $scanner->id]) }}"
                                class="underline decoration-gray-300 underline-offset-2 hover:text-cerulean-600 hover:decoration-cerulean-400 dark:decoration-gray-600 dark:hover:text-cerulean-400">{{ $scanner->name }}</a>
                        @else
                            {{ $scanner->name }}
                        @endcan
                    </div>
                @empty
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Noch auf keinem Scanner.') }}</div>
                @endforelse
            </div>

        </x-slot>
    </x-card>
