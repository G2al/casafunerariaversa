<?php

// Usato dall'installatore CLI. Non carica Laravel, non accede al DB e non mostra segreti.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$project = dirname(__DIR__);
if (PHP_OS_FAMILY !== 'Linux' || realpath($project) !== '/var/www/vhosts/casafuneraria.deviommi.it/httpdocs') {
    fwrite(STDERR, "Questo configuratore è destinato al server Plesk del gestionale.\n");
    exit(1);
}
require $project.'/vendor/autoload.php';
$path = $project.'/.env';
$text = file_get_contents($path);
$parsed = Dotenv\Dotenv::parse($text);
$values = [
    'OCCUPANCY_REALTIME_ENABLED' => 'true', 'BROADCAST_CONNECTION' => 'reverb',
    'REVERB_SERVER_HOST' => '127.0.0.1', 'REVERB_SERVER_PORT' => '8085', 'REVERB_SERVER_PATH' => '',
    'REVERB_HOST' => '127.0.0.1', 'REVERB_PORT' => '8085', 'REVERB_SCHEME' => 'http',
    'REVERB_PUBLIC_HOST' => 'casafuneraria.deviommi.it', 'REVERB_PUBLIC_PORT' => '443', 'REVERB_PUBLIC_SCHEME' => 'https',
    'REVERB_ALLOWED_ORIGINS' => 'casafuneraria.deviommi.it', 'REVERB_SCALING_ENABLED' => 'false',
];
foreach (['REVERB_APP_ID' => 'casafuneraria-occupancy', 'REVERB_APP_KEY' => bin2hex(random_bytes(16)), 'REVERB_APP_SECRET' => bin2hex(random_bytes(32))] as $key => $fallback) {
    if (trim($parsed[$key] ?? '') === '') {
        $values[$key] = $fallback;
    }
}
foreach ($values as $key => $value) {
    $line = $key.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    $pattern = '/^(?:export[\t ]+)?'.preg_quote($key, '/').'[\t ]*=[^\r\n]*$/m';
    if (preg_match($pattern, $text)) {
        $text = preg_replace_callback($pattern, fn () => $line, $text);
    } else {
        $text = rtrim($text)."\n".$line."\n";
    }
}
$temp = $path.'.realtime-tmp-'.bin2hex(random_bytes(6));
if (file_put_contents($temp, $text) === false || ! chmod($temp, 0600) || ! rename($temp, $path)) {
    if (is_file($temp)) {
        unlink($temp);
    }throw new RuntimeException('Aggiornamento .env non riuscito.');
}
fwrite(STDOUT,"Configurazione realtime aggiornata; credenziali generate o conservate senza stamparle.\n");
