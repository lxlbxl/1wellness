<?php
require_once __DIR__ . '/../database/SqliteDB.php';

try {
    $db = new SqliteDB();
    $user = $db->getAdminByUsername('admin');
    var_dump($user);
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}