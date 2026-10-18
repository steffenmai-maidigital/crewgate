<?php

// Angaben zum Produkt für Footer, Hilfe-Seite und Fehlerseiten (Standard 03)
// Product details for footer, help page and error pages (Standard 03)
return [
    'name'        => 'CrewGate',
    // Symbol, solange kein eigenes Logo hochgeladen ist
    // Icon, as long as no custom logo has been uploaded
    'symbol'      => 'img/crewgate.svg',
    // Link zum Quellcode in der Fußzeile (AGPL: Nutzer müssen an den Quellcode
    // der laufenden Fassung kommen — bei eigenen Änderungen auf euer Repo zeigen)
    // Link to the source code in the footer (AGPL: users must be able to get the source
    // code of the running version — point to your own repo if you make changes)
    'source_url'  => env('SOURCE_URL', ''),
    'github'      => env('GITHUB_URL', 'https://github.com/steffenmai-maidigital/crewgate'),
    'project_url' => env('PROJECT_URL', 'https://www.mai-digital.de'),
];
