{{-- Das Favicon an einer Stelle statt in vier Layouts einzeln: Vorher stand
     dieselbe Zeile in app, admin/app, guest und empty - eine Aenderung haette
     man dreimal vergessen koennen.

     Ohne type-Angabe beim eigenen Logo: Welches Bildformat hochgeladen wurde,
     steht erst in der Datei. Der Browser erkennt es selbst, und eine falsche
     Angabe waere schlimmer als keine. --}}
@if (\App\Models\Setting::logoPfad('favicon'))
    <link rel="icon" href="{{ route('branding.logo', 'favicon') }}">
@else
    <link rel="icon" href="{{ asset('logo.svg') }}" type="image/svg+xml">
@endif
