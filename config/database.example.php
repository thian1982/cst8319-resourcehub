<?php

/**
 * ResourceHub Database Configuration Template
 *
 * COPY this file:
 * database.example.php
 *
 * TO:
 * database.local.php
 *
 * Then place the real database password in the local copy.
 *
 * Never commit database.local.php.
 */

return [

    /*
     * Local development through the SSH tunnel.
     *
     * SSH tunnel  ;
     *
     * localhost:3307  ;
     *
     * DreamHost SSH  ;
     *
     * mysql.zanniat.info:3306
     */

    'host' => '127.0.0.1',

    'port' => 3307,

    'database' => 'cst8319',

    'username' => 'dh_cst8319',

    'password' => 'YOUR_DATABASE_PASSWORD',

    'charset' => 'utf8mb4'
];
