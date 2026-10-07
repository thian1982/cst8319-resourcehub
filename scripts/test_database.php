<?php

/**
 * ResourceHub Database Connection Test
 *
 * Run from the project root:
 *
 * php scripts/test_database.php
 */


try {

    $pdo = require __DIR__
        . '/../config/database.php';


    $databaseName = $pdo
        ->query('SELECT DATABASE()')
        ->fetchColumn();


    echo PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo "ResourceHub Database Test"
        . PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo PHP_EOL;


    echo "Database connection successful."
        . PHP_EOL;

    echo "Connected database: "
        . $databaseName
        . PHP_EOL;


    echo PHP_EOL;

    echo "Tables:"
        . PHP_EOL;


    $statement = $pdo->query(
        'SHOW TABLES'
    );


    $tables = $statement->fetchAll(
        PDO::FETCH_COLUMN
    );


    foreach ($tables as $table) {

        echo "- "
            . $table
            . PHP_EOL;
    }


    echo PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo "Test complete."
        . PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo PHP_EOL;


} catch (Throwable $e) {

    echo PHP_EOL;

    echo "Database test failed."
        . PHP_EOL;

    echo $e->getMessage()
        . PHP_EOL;

    echo PHP_EOL;
}
