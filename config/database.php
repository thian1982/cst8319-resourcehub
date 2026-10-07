<?php

/**
 * ResourceHub PDO Database Connection
 *
 * Actual credentials are stored in database.local.php,
 * which must NOT be committed.
 */

$configFile = __DIR__ . '/database.local.php';


if (!file_exists($configFile)) {

    throw new RuntimeException(
        'Database configuration file not found. ' .
        'Copy config/database.example.php to ' .
        'config/database.local.php and add your credentials.'
    );
}


$config = require $configFile;


$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['database'],
    $config['charset']
);


$options = [

    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,

    PDO::ATTR_EMULATE_PREPARES =>
        false
];


try {

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $options
    );

    return $pdo;

} catch (PDOException $e) {

    throw new RuntimeException(
        'Unable to connect to the ResourceHub database.',
        0,
        $e
    );
}
