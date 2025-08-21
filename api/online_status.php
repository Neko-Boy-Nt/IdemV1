<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();
$userId = SessionManager::getUserId();

header('Content-Type: application/json');

if (isset($_GET['user_id'])) {
    $targetUserId = intval($_GET['user_id']);
    $status = fetchOne(
        "SELECT online_status FROM users WHERE id = :id",
        ['id' => $targetUserId]
    );
    echo json_encode(['success' => true, 'status' => $status['online_status'] ?? 'offline']);
} else {
    // Mise à jour statut current user
    update('users', ['last_activity' => date('Y-m-d H:i:s'), 'online_status' => 'online'], 'id = :id', ['id' => $userId]);
    echo json_encode(['success' => true]);
}