<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require 'db.php';

$log_id = $_GET['id'] ?? 0;
$username = $_SESSION['username'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM logs_ia WHERE id = ? AND username = ?");
$stmt->execute([$log_id, $username]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if ($log) {
    echo json_encode(['status' => 'success', 'data' => $log]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Archive introuvable.']);
}
?>
