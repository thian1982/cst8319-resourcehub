<?php
declare(strict_types=1);

// Loads ResourceHub classes from src/Models, src/Repositories and src/Services.
// Class names match file names, e.g. ReservationService -> src/Services/ReservationService.php
spl_autoload_register(function (string $class): void {
    foreach (['Models', 'Repositories', 'Services'] as $folder) {
        $file = __DIR__ . '/' . $folder . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
