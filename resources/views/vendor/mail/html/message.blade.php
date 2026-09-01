<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ App\Models\Setting::appName() }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{-- Kein "All rights reserved": Diese Mails gehen an Kollegen und Kunden,
     nicht an Abonnenten. Der Hinweis, woher sie kommt, ist nuetzlicher. --}}
© {{ date('Y') }} {{ App\Models\Setting::appName() }} — {{ __('Diese Nachricht wurde automatisch verschickt.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
