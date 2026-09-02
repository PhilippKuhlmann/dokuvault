<?php

return [
    'required' => 'Das Feld :attribute ist erforderlich.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse enthalten.',
    'ipv4' => 'Das  Feld :attribute muss eine gültige IPv4-Adresse sein.',
    'url' => 'Das Feld URL muss eine gültige URL sein.',
    'integer' => 'Das Feld :attribute muss eine Zahl sein',

    // Die Meldungen der Kennwortregel. Ohne sie erfaehrt ein Benutzer auf
    // Englisch, woran es lag - eine Regel, die man im Adminbereich einstellt,
    // soll auch deutsch erklaeren, warum sie zugeschlagen hat.
    'min' => [
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'confirmed' => 'Die Wiederholung von :attribute stimmt nicht überein.',
    'password' => [
        'letters' => 'Das Feld :attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => 'Das Feld :attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => 'Das Feld :attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => 'Das Feld :attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Dieses :attribute steht in einer bekannten Liste geleakter Kennwörter. Bitte ein anderes wählen.',
    ],

    /*
     * Wie die Felder in Meldungen heissen. Ohne das steht dort "Das Feld
     * password" - der Spaltenname statt des Worts, das der Benutzer auf dem
     * Bildschirm sieht.
     */
    'attributes' => [
        'password' => 'Kennwort',
        'password_confirmation' => 'Wiederholung des Kennworts',
        'current_password' => 'aktuelles Kennwort',
        'username' => 'Benutzername',
        'email' => 'E-Mail-Adresse',
        'name' => 'Name',
    ],
];
