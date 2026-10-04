{{-- Geräte im Plan: mit Sprung auf die Karte in ihrer Liste, wo der Nutzer
     die Liste sehen darf (siehe IpPlanController::geraetEintrag). Ohne Link
     bleibt es beim Namen. Alles auf einer Zeile, damit kein Leerraum vor dem
     Trenner landet. imPool: feste Adresse mitten im DHCP-Bereich - steht am
     Bereich, aber markiert, weil es ein Konflikt sein kann. --}}
@foreach ($geraete as $geraet)@if ($geraet['url'])<a href="{{ $geraet['url'] }}" class="underline decoration-gray-300 underline-offset-2 transition-colors hover:text-cerulean-600 hover:decoration-cerulean-400 dark:decoration-gray-600 dark:hover:text-cerulean-400">{{ $geraet['name'] }}</a>@else{{ $geraet['name'] }}@endif@if ($geraet['imPool'] ?? false)<span class="ml-1 rounded bg-amber-50 px-1 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" title="{{ __('Feste Adresse im DHCP-Bereich. Kam sie per DHCP, an der Adresse „per DHCP“ setzen; sonst droht ein Adresskonflikt.') }}">{{ __('fest im DHCP-Bereich') }}</span>@endif{{ $loop->last ? '' : $trenner }}@endforeach
