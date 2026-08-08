@props(['device'])

{{-- Verknüpfte Zugangsdaten direkt in der Geräteliste, damit man fürs Nachsehen
     nicht erst ins Bearbeiten-Formular muss. Nur Anzeige - verknüpft und gelöst
     wird weiterhin dort.

     Beschriftung ist die Notiz, falls eine gepflegt ist ("Serielle Konsole"),
     sonst der Name des Logins. Beides nebeneinander wäre auf 20 rem doppelt. --}}

@can('logingeneral_viewAny')
    @php ($eintraege = $device->zugangsdaten())

    @if ($eintraege->isNotEmpty())
        <div class="w-full sm:w-80">
            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('Zugangsdaten') }}
            </div>
            <div class="text-sm dark:text-gray-100">
                <table class="w-full">
                    @foreach ($eintraege as $eintrag)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">
                                {{ $eintrag->note ?: $eintrag->login->name }}
                            </td>
                            <td class="py-1 align-top text-gray-900 dark:text-gray-100">
                                @if ($eintrag->login->username)
                                    <div class="font-mono">{{ $eintrag->login->username }}</div>
                                @endif
                                @if ($eintrag->login->password)
                                    <x-password :value="$eintrag->login->password" width="w-24" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif
@endcan
