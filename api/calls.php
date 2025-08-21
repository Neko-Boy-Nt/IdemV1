<?php
// api/calls.php - Stub pour appels audio/vidéo (implémenter avec WebRTC plus tard)
require_once '../config/database.php';
require_once '../config/session.php';

SessionManager::requireLogin();

header('Content-Type: application/json');

echo json_encode(['success' => true, 'message' => 'Appels non implémentés encore - Utiliser WebRTC']);
?>