<?php
/**
 * Jednotkové testy logiky nepokryté výuky.
 *
 * Testují čisté rozhodovací funkce bez připojení k databázi:
 *
 *   php tests/coverage-functions-test.php
 */

require_once __DIR__ . '/../includes/coverage-functions.php';

$expectBool = static function (bool $expected, bool $actual, string $message): void {
    if ($expected !== $actual) {
        $e = $expected ? 'true' : 'false';
        $a = $actual ? 'true' : 'false';
        throw new RuntimeException($message . ": expected $e, got $a");
    }
};

$expectFloat = static function (float $expected, float $actual, string $message): void {
    if (abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ": expected $expected, got $actual");
    }
};

// --- Nepokrytá část výuky ---
$expectBool(true, jeCastNepokryta(0.0), 'Část bez vyučujícího je nepokrytá');
$expectBool(true, jeCastNepokryta(50.0), 'Poloviční podíl je nepokrytý');
$expectBool(true, jeCastNepokryta(99.0), 'Podíl 99 % je stále nepokrytý');
$expectBool(false, jeCastNepokryta(100.0), 'Plný podíl je pokrytý');
$expectBool(false, jeCastNepokryta(150.0), 'Přepokrytí se nepovažuje za nepokryté');

// Tolerance kvůli desetinným podílům (např. 3 × 33,33 %)
$expectBool(false, jeCastNepokryta(99.99), 'Zaokrouhlovací tolerance – 99,99 % se bere jako pokryté');
$expectBool(true, jeCastNepokryta(99.5), 'Půl procenta pod hranicí už je nepokryté');

// --- Chybějící podíl ---
$expectFloat(100.0, chybejiciPodil(0.0), 'Bez vyučujícího chybí 100 %');
$expectFloat(50.0, chybejiciPodil(50.0), 'Při 50 % chybí 50 %');
$expectFloat(0.0, chybejiciPodil(100.0), 'Při plném pokrytí nechybí nic');
$expectFloat(0.0, chybejiciPodil(120.0), 'Přepokrytí nesmí dát zápornou hodnotu');

// --- Pokrytí předmětu jako celku (0–100 %) ---
// REGRESE: dříve se procenta částí sčítala, takže předmět se 4 nepokrytými
// částmi hlásil „chybí 400 %“. Pokrytí předmětu musí vždy zůstat v rozsahu 0–100.
$expectFloat(0.0, pokrytiPredmetu([0.0, 0.0, 0.0, 0.0]), 'Čtyři části bez vyučujícího = 0 % pokrytí');
$expectFloat(100.0, chybejiciPokrytiPredmetu([0.0, 0.0, 0.0, 0.0]), 'Chybí 100 %, nikoliv 400 %');

$expectFloat(100.0, pokrytiPredmetu([100.0, 100.0]), 'Vše pokryto = 100 %');
$expectFloat(0.0, chybejiciPokrytiPredmetu([100.0, 100.0]), 'Nechybí nic');

$expectFloat(50.0, pokrytiPredmetu([100.0, 0.0]), 'Jedna ze dvou částí pokryta = 50 %');
$expectFloat(90.0, pokrytiPredmetu([100.0, 100.0, 100.0, 60.0]), 'Tři plné a jedna na 60 % = 90 %');
$expectFloat(10.0, chybejiciPokrytiPredmetu([100.0, 100.0, 100.0, 60.0]), 'Chybí 10 %');

// Přepokrytí jedné části nesmí kompenzovat nepokrytí jiné
$expectFloat(50.0, pokrytiPredmetu([150.0, 0.0]), 'Přepokrytí se nezapočítává jako bonus');
$expectFloat(100.0, pokrytiPredmetu([]), 'Předmět bez částí je považován za pokrytý');

// Výsledek musí vždy zůstat v rozsahu 0–100
foreach ([[0.0], [0.0, 0.0, 0.0, 0.0, 0.0], [200.0, 200.0], [33.3, 33.3, 33.3]] as $vzorek) {
    $hodnota = pokrytiPredmetu($vzorek);
    if ($hodnota < 0.0 || $hodnota > 100.0) {
        throw new RuntimeException('Pokrytí předmětu musí být v rozsahu 0–100, získáno: ' . $hodnota);
    }
    $chybi = chybejiciPokrytiPredmetu($vzorek);
    if ($chybi < 0.0 || $chybi > 100.0) {
        throw new RuntimeException('Chybějící pokrytí musí být v rozsahu 0–100, získáno: ' . $chybi);
    }
}

// --- Scénář: předmět spadne do přehledu, pokud JAKÁKOLIV část není pokrytá ---
$castiPredmetu = [
    ['typ' => 'P', 'soucet' => 100.0],  // pokryto
    ['typ' => 'C', 'soucet' => 60.0],   // nepokryto
    ['typ' => 'S', 'soucet' => 100.0],  // pokryto
];
$nepokryteCasti = array_values(array_filter(
    $castiPredmetu,
    static fn(array $cast): bool => jeCastNepokryta((float)$cast['soucet'])
));

if (count($nepokryteCasti) !== 1 || $nepokryteCasti[0]['typ'] !== 'C') {
    throw new RuntimeException('Předmět musí být nepokrytý kvůli cvičení (60 %).');
}
$expectFloat(40.0, chybejiciPodil(60.0), 'U cvičení chybí 40 %');

// Předmět plně pokrytý ve všech částech se do přehledu nedostane
$plnePokryty = [100.0, 100.0, 100.0];
$nepokryte = array_filter($plnePokryty, static fn(float $s): bool => jeCastNepokryta($s));
if ($nepokryte !== []) {
    throw new RuntimeException('Plně pokrytý předmět nesmí spadnout do přehledu.');
}

echo "coverage-functions: OK\n";
