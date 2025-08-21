<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();
$userId = SessionManager::getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['audio'])) {
        $file = $_FILES['audio'];
        $conversationId = intval($_POST['conversation_id'] ?? 0);

        try {
            $filename = uniqid('audio_') . '.ogg'; // Exemple pour audio ogg
            $uploadDir = 'upload/audio/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $messageId = insert('messages', [
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'message_type' => 'audio',
                    'file_url' => $filename,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                echo json_encode(['success' => true, 'message_id' => $messageId, 'file_url' => $filename]);
            } else {
                throw new Exception('Échec de l\'upload');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Fichier audio requis']);
    }
}
?>