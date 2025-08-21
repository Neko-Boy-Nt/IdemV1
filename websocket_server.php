<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
require 'vendor/autoload.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $users;
    protected $conversations;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        $this->conversations = [];
        echo "WebSocket server started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            if (!$data || !isset($data['type'])) {
                $from->send(json_encode(['type' => 'error', 'message' => 'Message invalide']));
                return;
            }

            switch ($data['type']) {
                case 'auth':
                    $userId = $data['user_id'] ?? null;
                    $sessionId = $data['session_id'] ?? null;
                    if ($this->verifySession($userId, $sessionId)) {
                        $this->users[$from->resourceId] = $userId;
                        $this->joinUserConversations($from, $userId);
                        $from->send(json_encode(['type' => 'auth_success', 'message' => 'Authenticated']));
                    } else {
                        $from->send(json_encode(['type' => 'error', 'message' => 'Session ID requis ou invalide']));
                        $from->close();
                    }
                    break;

                case 'message':
                    $message = $data['message'];
                    $this->broadcastToConversation($message['conversation_id'], [
                        'type' => 'message',
                        'message' => $message
                    ], $from);
                    break;

                case 'typing':
                    $this->broadcastToConversation($data['conversation_id'], [
                        'type' => 'typing',
                        'user_id' => $data['user_id']
                    ], $from);
                    break;

                case 'typing_stop':
                    $this->broadcastToConversation($data['conversation_id'], [
                        'type' => 'typing_stop',
                        'user_id' => $data['user_id']
                    ], $from);
                    break;

                case 'read':
                    $this->broadcastToConversation($data['conversation_id'], [
                        'type' => 'read',
                        'message_id' => $data['message_id']
                    ], $from);
                    break;

                case 'delete':
                    $this->broadcastToConversation($data['conversation_id'], [
                        'type' => 'delete',
                        'message_id' => $data['message_id']
                    ], $from);
                    break;

                default:
                    $from->send(json_encode(['type' => 'error', 'message' => 'Unknown message type']));
            }
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            $from->send(json_encode(['type' => 'error', 'message' => $e->getMessage()]));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if (isset($this->users[$conn->resourceId])) {
            $userId = $this->users[$conn->resourceId];
            unset($this->users[$conn->resourceId]);
            foreach ($this->conversations as $convId => &$clients) {
                $clients = array_filter($clients, fn($client) => $client !== $conn);
                if (empty($clients)) {
                    unset($this->conversations[$convId]);
                }
            }
        }
        $this->clients->detach($conn);
        echo "Connection closed ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function verifySession($userId, $sessionId) {
        if (empty($sessionId) || empty($userId)) return false;
        session_id($sessionId);
        session_start();
        $valid = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId;
        session_write_close();
        return $valid;
    }

    protected function joinUserConversations(ConnectionInterface $conn, $userId) {
        $db = initDatabase();
        $conversations = fetchAll(
            "SELECT conversation_id FROM conversation_participants WHERE user_id = :user_id AND is_deleted = 0",
            ['user_id' => $userId]
        );
        foreach ($conversations as $conv) {
            $convId = $conv['conversation_id'];
            if (!isset($this->conversations[$convId])) {
                $this->conversations[$convId] = [];
            }
            $this->conversations[$convId][] = $conn;
        }
    }

    protected function broadcastToConversation($conversationId, $message, ConnectionInterface $sender) {
        if (isset($this->conversations[$conversationId])) {
            foreach ($this->conversations[$conversationId] as $client) {
                if ($client !== $sender) {
                    $client->send(json_encode($message));
                }
            }
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);
$server->run();