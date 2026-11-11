<?php

use Symfony\Component\Console\Input\ArgvInput;

// Punto di ingresso CLI per Plesk: selezionare la versione PHP del dominio.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/vendor/autoload.php';

exit((require __DIR__.'/bootstrap/app.php')->handleCommand(
    new ArgvInput(['artisan', 'schedule:run'])
));
