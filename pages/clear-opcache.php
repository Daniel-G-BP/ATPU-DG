<?php
// Temporary file – delete after use!
$files = [
    '/var/www/html/pages/debug-a2.php',
    '/var/www/html/pages/zkouseni.php',
    '/var/www/html/includes/functions.php',
];

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p style='color:green;font-size:18px'>✅ OPcache reset (všechny soubory vyčištěny)</p>";
    foreach ($files as $f) {
        $ok = opcache_invalidate($f, true);
        echo "<p>Invalidate <code>$f</code>: " . ($ok ? '✅' : '⚠ soubor není v cache') . "</p>";
    }
} else {
    echo "<p style='color:orange'>OPcache není aktivní – není co čistit.</p>";
}

echo "<p><a href='debug-a2.php'>→ Otevřít debug-a2.php</a></p>";
echo "<p><small>Smaž tento soubor po použití.</small></p>";
