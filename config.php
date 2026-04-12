<?php

declare(strict_types=1);

const APP_NAME = 'MyBill Network';
const DB_PATH = __DIR__ . '/database.sqlite';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
