{{--
    Der Fernwartungsknopf - eine Stelle statt vier.

    Vorher stand das RustDesk-Schema in vier Views fest im Markup. Wer
    TeamViewer benutzt, hätte jede einzelne ändern müssen. Jetzt kommt der Link
    aus der Einstellung; fehlen ID oder Muster, erscheint kein Knopf.

    Drei Varianten: "knopf" ist das Symbol in der Kopfzeile, "text" die breite
    Schaltfläche in der Computerliste, "label" der beschriftete Knopf in der
    Fernwartungs-Übersicht.

    The link goes to RemoteConnectController, which logs the connection and
    then redirects to the tool. The tool link itself carries the password
    (RustDesk: "?password=") and must not sit in the HTML.
--}}
@props([
    'device',
    'stil' => 'knopf',
])

@php
    // Only to know whether there is a button at all - not put into the page.
    $moeglich = \App\Models\Setting::fernwartungsLink($device->remoteID, $device->remotePassword);
    $typ = [\App\Models\Server::class => 'server', \App\Models\VM::class => 'vm', \App\Models\Computer::class => 'computer'][get_class($device)] ?? null;
    $link = $moeglich && $typ ? route('remote.connect', [$typ, $device->id]) : null;
    $werkzeug = \App\Models\Setting::fernwartung();
@endphp

@if ($link)
    @if ($stil === 'label')
        <x-input.linkbutton :label="__('Verbinden')" link="{{ $link }}" color="knopf" />
    @elseif ($stil === 'text')
        <a href="{{ $link }}"
            class="bg-cerulean-600 text-white rounded-lg px-4 py-2 text-sm mr-5 hover:bg-cerulean-700">{{ __('Verbinden') }}</a>
    @else
        <x-input.linkbutton link="{{ $link }}">
            <x-slot:label>
                <x-dynamic-component :component="$werkzeug['icon']"
                    class="h-6 w-6 fill-cerulean-500! hover:fill-cerulean-400! text-cerulean-500 hover:text-cerulean-400" />
            </x-slot:label>
        </x-input.linkbutton>
    @endif
@endif
