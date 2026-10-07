<?php
// includes/rarlib/autoload.php - Autoload PSR-4 de selective/rar (fork local)
//
// Lector puro PHP de indices RAR (RAR3/4 y RAR5) usado por el explorador de
// carpetas. Solo lee metadatos (nombre, tamanos, CRC, fecha, cifrado); la
// extraccion real la hace UnRAR.exe (ver extraer_rar.php).
//
// Fork de https://github.com/selective-php/rar (MIT, ver LICENSE) con arreglos
// para PHP 8.1 de 32 bits: unpack de 64 bits, fileTime sin inicializar y
// deteccion de cifrado/directorios.
spl_autoload_register(static function ($clase) {
    $prefijo = 'Selective\\Rar\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});
