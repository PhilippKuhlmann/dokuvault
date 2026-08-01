{{--
    Gehäuse für beide Rack-Ansichten (Schema und Frontansicht).

    Bewusst eine einzige Stelle: Breite, Rahmen und Farbe sollen in beiden
    Ansichten identisch sein, damit sie nebeneinander wie derselbe Schrank
    wirken. Getrennt gepflegt sind sie schon einmal auseinandergelaufen.
--}}
<div {{ $attributes->merge([
    'class' => 'inline-block min-w-64 w-full max-w-md rounded-lg border-2 border-gray-400 bg-gray-200 p-2 dark:border-gray-600 dark:bg-gray-950',
]) }}>
    {{ $slot }}
</div>
