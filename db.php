<?php
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: setup.php');
    exit;
}
require __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    if ($stmt) {
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    $ollama_url = $settings['ollama_url'] ?? 'http://127.0.0.1:11434';
    $ai_name = $settings['ai_name'] ?? 'Your company';
    $ai_logo = $settings['ai_logo'] ?? '';
    
} catch(PDOException $e) {
    die("Erreur de connexion MariaDB : " . $e->getMessage());
}
?>
