{{-- Key, and a tick for a moment after copying ($kopieren sets "copied"). --}}
<svg x-show="! copied" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="8" cy="15" r="4" />
    <path d="M10.8 12.2 20 3m-3 3 3 3m-6 0 2 2" />
</svg>
<svg x-show="copied" x-cloak class="h-5 w-5 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M4.5 12.75l6 6 9-13.5" />
</svg>
