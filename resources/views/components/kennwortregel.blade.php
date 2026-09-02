{{-- Was fuer ein Kennwort verlangt wird, in einem Satz.

     Steht unter jedem Feld, in das jemand ein neues Kennwort eintraegt. Wer ein
     Sonderzeichen verlangt, ohne es hinzuschreiben, laesst raten - und der
     Benutzer erfaehrt die Regel erst, wenn er gegen sie verstossen hat.

     Der Satz kommt aus den Einstellungen (Adminbereich > Sicherheit) und
     aendert sich mit ihnen. --}}
<p {{ $attributes->merge(['class' => 'mt-1 text-xs text-gray-500 dark:text-gray-400']) }}>
    {{ \App\Models\Setting::kennwortHinweis() }}
</p>
