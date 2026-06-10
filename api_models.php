<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['error' => 'Non autorisé']); 
    exit; 
}

// Fetch available models from Ollama API
require 'db.php';
$ch = curl_init($ollama_url . '/api/tags');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    echo $response;
} else {
    // Fallback if unreachable
    echo json_encode(['models' => [['name' => 'mistral-nemo:latest']]]);
}
?>
