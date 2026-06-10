<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require 'db.php';

$prompt = $_POST['prompt'] ?? '';
$username = $_SESSION['username'] ?? 'unknown';

if (empty($prompt)) {
    echo json_encode(['error' => 'Un prompt est nécessaire pour générer une image.']);
    exit;
}

// Appel API vers Stable Diffusion WebUI (Automatic1111) par défaut
$sd_url = rtrim($settings['sd_url'] ?? 'http://127.0.0.1:7860', '/');
$payload = [
    "prompt" => $prompt,
    "steps" => 20,
    "width" => 512,
    "height" => 512
];

$ch = curl_init($sd_url . '/sdapi/v1/txt2img');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$media_type = 'image_gen';
$image_markdown = '';

if ($response && $http_code == 200) {
    $data = json_decode($response, true);
    if (isset($data['images']) && count($data['images']) > 0) {
        $base64 = $data['images'][0];
        $image_markdown = "![Généré par IA](data:image/png;base64," . $base64 . ")";
        
        $stmt = $pdo->prepare("INSERT INTO logs_ia (username, model, prompt, response, media_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, "Image Diffuser", $prompt, $image_markdown, $media_type]);
        
        echo json_encode(['response' => $image_markdown]);
    } else {
        echo json_encode(['error' => 'La réponse de l\'API d\'image est invalide.']);
    }
} else {
    // Simulate generation for demo purposes if SD isn't running
    if ($http_code == 0) {
        $msg = "Erreur: Le service de génération d'image (ex: Stable Diffusion) n'est pas joignable sur `$sd_url`.\nVérifiez qu'il est en cours d'exécution ou modifiez l'URL dans l'Administration.";
        echo json_encode(['error' => $msg]);
    } else {
        echo json_encode(['error' => "Impossible de contacter l'API (Code: $http_code)."]);
    }
}
?>
