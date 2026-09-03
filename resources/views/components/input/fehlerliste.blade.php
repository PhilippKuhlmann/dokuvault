{{--
    Der Satz über dem Formular, wenn etwas fehlt.

    Er zählt nicht auf, was fehlt - das steht an den Feldern selbst. Er sagt
    nur, dass das Absenden nicht durchging und wo man hinsehen muss. Ohne ihn
    wirkt ein Formular, das sich nicht schließt, wie ein hängender Knopf.
--}}
@if ($errors->any())
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
        role="alert">
        <svg class="mt-0.5 h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 012 0v5a1 1 0 01-2 0V6zm1 9a1.25 1.25 0 110-2.5 1.25 1.25 0 010 2.5z" clip-rule="evenodd" />
        </svg>
        <span>{{ __('Bitte die rot markierten Felder prüfen.') }}</span>
    </div>
@endif
