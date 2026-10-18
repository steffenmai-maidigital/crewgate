<?php

// Deutsche Validierungsmeldungen (Auszug der verwendeten Regeln)
return [
    'required' => ':attribute ist ein Pflichtfeld.',
    'accepted' => ':attribute muss bestätigt werden.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'url' => ':attribute muss eine gültige URL sein.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'array' => ':attribute muss eine Auswahl sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'image' => ':attribute muss ein Bild sein.',
    'mimes' => ':attribute muss eine Datei des Typs :values sein.',
    'string' => ':attribute muss eine Zeichenkette sein.',
    'min' => [
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
        'array' => 'Bitte mindestens :min Eintrag/Einträge für :attribute auswählen.',
    ],
    'max' => [
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
        'file' => ':attribute darf höchstens :max KB groß sein.',
    ],
    'between' => [
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
    ],
    'required_if' => ':attribute ist ein Pflichtfeld.',
];
