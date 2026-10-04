<?php
/**
 * Carga variables desde un archivo .env hacia getenv()/$_ENV.
 * Siempre usa el valor del .env como fuente de verdad (sobre-escribe
 * cualquier valor que haya quedado pegado de una petición anterior,
 * algo que puede pasar en Windows porque Apache reutiliza el mismo
 * proceso entre peticiones).
 */
function cargarEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lineas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        [$clave, $valor] = array_pad(explode('=', $linea, 2), 2, '');
        $clave = trim($clave);
        $valor = trim($valor, " \t\n\r\0\x0B\"'");

        if ($clave !== '') {
            putenv("$clave=$valor");
            $_ENV[$clave] = $valor;
        }
    }
}

cargarEnv(__DIR__ . '/../.env');