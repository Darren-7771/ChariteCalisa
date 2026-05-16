<?php
require_once 'config.php';

$accounts = [
    ['baznas@example.com',            'password123', 'pengelola'],
    ['humaninitiative@example.com',   'password123', 'pengelola'],
    ['dmc@example.com',               'password123', 'pengelola'],
    ['nyalakanharapan@example.com',   'password123', 'pengelola'],
    ['budi@example.com',              'password123', 'donatur'],
    ['siti@example.com',              'password123', 'donatur'],
];

foreach ($accounts as [$email, $pass, $role]) {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $table = $role === 'pengelola' ? 'pengelola' : 'donatur';
    $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
    $stmt->bind_param('ss', $hash, $email);
    $stmt->execute();
    echo "Updated $role: $email\n";
}
echo "Selesai!\n";