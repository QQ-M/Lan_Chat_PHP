<?php
// db.php
$host = '127.0.0.1';
$db   = 'lan_chat';
$user = 'Lan_Chat_PHP';      // XAMPP 默认是 root
$pass = 'mqq4188';          // XAMPP 默认密码为空，MAMP 可能是 root
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

session_start();
?>