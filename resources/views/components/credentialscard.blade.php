@props(['device'])

{{-- Verknüpfte Zugangsdaten direkt in der Geräteliste, damit man fürs Nachsehen
     nicht erst ins Bearbeiten-Formular muss. Nur Anzeige - verknüpft und gelöst
     wird weiterhin dort.

     Beschriftung ist die Notiz, falls eine gepflegt ist ("Serielle Konsole"),
     sonst der Name des Logins. Beides nebeneinander wäre auf 20 rem doppelt. --}}

@canany(['logingeneral_viewAny', 'sshkey_viewAny'])
    {{-- Kennwoerter und Schluessel liegen in derselben Tabelle, haben aber
         getrennte Rechte: Wer nur eines darf, sieht auch nur eines. --}}
    @php ($arten = collect([
        \App\Models\LoginGeneral::KIND => 'logingeneral_viewAny',
        \App\Models\SshKey::KIND => 'sshkey_viewAny',
    ])->filter(fn ($recht) => auth()->user()->can($recht))->keys()->all())

    @php ($eintraege = $device->zugangsdaten()->filter(fn ($e) => in_array($e->login->kind, $arten, true)))

    @if ($eintraege->isNotEmpty())
        <div class="w-full mb-5 break-inside-avoid">
            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('Zugangsdaten') }}
            </div>
            <div class="text-sm dark:text-gray-100">
                <table class="w-full">
                    @foreach ($eintraege as $eintrag)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            {{-- Kein whitespace-nowrap: Der Kartenkoerper laeuft in CSS-Spalten, und
                                 eine Tabelle schrumpft nicht unter ihre Mindestbreite - mit unbrechbarer
                                 Beschriftung lief sie in die Nachbarspalte und aus der Karte heraus
                                 ("10.10.30.7Hersteller"). Umgebrochen wird nur, wenn es sonst nicht passt. --}}
                            <td class="py-1 pr-6 align-top break-words text-gray-500 dark:text-gray-400">
                                {{ $eintrag->note ?: $eintrag->login->name }}
                                {{-- Ohne das Merkmal sieht man der Zeile nicht an,
                                     dass darunter eine Passphrase steht. --}}
                                @if ($eintrag->login->istSchluessel())
                                    <span class="ml-1 rounded bg-gray-100 px-1 py-0.5 align-middle text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __('SSH') }}</span>
                                @endif
                            </td>
                            <td class="py-1 w-full align-top text-gray-900 dark:text-gray-100">
                                @if ($eintrag->login->username)
                                    <div class="font-mono">{{ $eintrag->login->username }}</div>
                                @endif
                                @if ($eintrag->login->password)
                                    <x-password :value="$eintrag->login->password" width="w-24" />
                                @endif
                                {{-- Der Fingerprint ist das, was man auf dem Server
                                     vergleicht - ohne ihn steht hier nur, dass ein
                                     Schluessel gilt, nicht welcher. --}}
                                @if ($eintrag->login->istSchluessel())
                                    <x-fingerprint :value="$eintrag->login->fingerprint" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif
@endcanany
