@props(['url'])
{{-- Kopfzeile in den Farben der Anwendung.

     Ein eigenes Logo, wenn eines hinterlegt ist - die Route /logo/{stelle}
     liegt bewusst ohne Anmeldung, sie steht auch auf der Anmeldeseite. Sonst
     der Name in Versalien: Das eingebaute Zeichen ist ein SVG, und SVG wird
     in den meisten Mailprogrammen gar nicht erst angezeigt. Ein Name, der
     immer ankommt, ist besser als ein Bild, das es meistens nicht tut. --}}
@php
    $eigenesLogo = App\Models\Setting::logoPfad('login') ? route('branding.logo', 'login') : null;
    $name = App\Models\Setting::appName();
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($eigenesLogo)
<img src="{{ $eigenesLogo }}" class="logo" alt="{{ $name }}">
@else
{{ $name }}
@endif
</a>
</td>
</tr>
