<?php

// Testsystem-Kennzeichnung: bei TEST_MODE=true erscheint "TESTSYSTEM"
// im Header, auf der Support-Seite und auf allen Exporten (CSV/PDF).
// Test system marking: with TEST_MODE=true, "TESTSYSTEM" appears
// in the header, on the support page and on all exports (CSV/PDF).
return [
    'enabled' => (bool) env('TEST_MODE', false),
];
