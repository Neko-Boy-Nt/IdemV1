<?php
require_once '../config/database.php';
require_once '../config/session.php';

SessionManager::requireLogin();
$userId = SessionManager::getUserId();

// Mise à jour last_activity
update('users', ['last_activity' => date('Y-m-d H:i:s'), 'online_status' => 'online'], 'id = :id', ['id' => $userId]);

echo json_encode(['success' => true]);
?>