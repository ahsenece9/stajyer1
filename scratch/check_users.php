<?php
require __DIR__ . '/../helpers.php';
$users = db()->query("SELECT id, username, full_name, role FROM users")->fetchAll();
echo json_encode($users, JSON_PRETTY_PRINT);
