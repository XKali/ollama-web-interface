<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Accès non autorisé.']);
    exit;
}

require 'db.php';

$prompt = $_POST['prompt'] ?? '';
$model = $_POST['model'] ?? 'mistral-nemo:latest';
$username = $_SESSION['username'] ?? 'unknown';

if (empty($prompt) && empty($_FILES['media'])) {
    echo json_encode(['error' => 'Veuillez saisir un prompt ou envoyer un fichier.']);
    exit;
}

if (!empty($_FILES['media']['name']) && $_FILES['media']['error'] != UPLOAD_ERR_OK) {
    if ($_FILES['media']['error'] == UPLOAD_ERR_INI_SIZE || $_FILES['media']['error'] == UPLOAD_ERR_FORM_SIZE) {
        echo json_encode(['error' => "Le fichier est trop volumineux pour la configuration PHP du serveur."]);
    } else {
        echo json_encode(['error' => "Erreur lors de l'envoi du fichier (Code: " . $_FILES['media']['error'] . ")."]);
    }
    exit;
}

$promptToSend = empty($prompt) ? "Décris cette image en détail." : $prompt;

$message = [
    'role' => 'user',
    'content' => $promptToSend
];

$media_type = 'text';

// Handle base64 extraction for capabilities like Image/Audio analysis (Llava etc.)
if (isset($_FILES['media']) && $_FILES['media']['error'] == UPLOAD_ERR_OK) {
    $mime = $_FILES['media']['type'];
    if (strpos($mime, 'image/') === 0) {
        $media_type = 'image';
        $base64 = base64_encode(file_get_contents($_FILES['media']['tmp_name']));
        $message['images'] = [$base64];
    }
    elseif (strpos($mime, 'audio/') === 0 || strpos($mime, 'video/') === 0) {
        $media_type = 'audio/video';
    }
}

$payload = [
    'model' => $model,
    'messages' => [$message],
    'stream' => false
];

$ch = curl_init($ollama_url . '/api/chat');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 mins in case of heavy model loading

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $http_code == 200) {
    $data = json_decode($response, true);
    $model_reply = $data['message']['content'] ?? 'Aucune réponse générée par le modèle.';
    
    // Log in Matrix
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_ia (username, model, prompt, response, media_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $model, $prompt, $model_reply, $media_type]);
    } catch(PDOException $e) {}

    echo json_encode(['response' => $model_reply]);
} else {
    echo json_encode(['error' => "Impossible de contacter l'API (Code HTTP: $http_code). Vérifiez que Ollama est actif sur $ollama_url"]);
}
?>
