<?php

declare(strict_types=1);

$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbCharset = getenv('DB_CHARSET');

return [
    'db' => [
        'host' => $dbHost !== false && $dbHost !== '' ? $dbHost : '127.0.0.1',
        'port' => $dbPort !== false && $dbPort !== '' ? (int) $dbPort : 3306,
        'name' => $dbName !== false && $dbName !== '' ? $dbName : 'whiteglove',
        'user' => $dbUser !== false && $dbUser !== '' ? $dbUser : 'root',
        'pass' => $dbPass !== false ? $dbPass : '',
        'charset' => $dbCharset !== false && $dbCharset !== '' ? $dbCharset : 'utf8mb4',
    ],
];
