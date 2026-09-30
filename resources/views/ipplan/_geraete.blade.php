{{-- Geräte im Plan: mit Sprung auf die Karte in ihrer Liste, wo der Nutzer
     die Liste sehen darf (siehe IpPlanController::geraetEintrag). Ohne Link
     bleibt es beim Namen. Alles auf einer Zeile, damit kein Leerraum vor dem
     Trenner landet. --}}
@foreach ($geraete as $geraet)@if ($geraet['url'])<a href="{{ $geraet['url'] }}" class="underline decoration-gray-300 underline-offset-2 transition-colors hover:text-cerulean-600 hover:decoration-cerulean-400 dark:decoration-gray-600 dark:hover:text-cerulean-400">{{ $geraet['name'] }}</a>@else{{ $geraet['name'] }}@endif{{ $loop->last ? '' : $trenner }}@endforeach
