<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();
$userId = SessionManager::getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $conversationId = intval($_POST['conversation_id'] ?? 0);
        $type = $_POST['type'] ?? 'file';

        try {
            $filename = uploadImage($file, 'upload/' . $type . '/'); // Utilise ta fonction uploadImage, adapte pour non-images si besoin
            $messageId = insert('messages', [
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'message_type' => $type,
                'file_url' => $filename,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            echo json_encode(['success' => true, 'message_id' => $messageId, 'file_url' => $filename]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Fichier requis']);
    }
}