<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
logout();
flash('success', 'Anda sudah logout.');
header('Location: index.php');
exit;
