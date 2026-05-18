<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

class KanbanAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                case 'PUT':
                    $this->handlePut($action);
                    break;
                case 'DELETE':
                    $this->handleDelete($action);
                    break;
                default:
                    $this->sendResponse(['error' => 'Método não suportado'], 405);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'cards':
                $this->getAllCards();
                break;
            case 'config':
                $this->getConfig();
                break;
            default:
                $this->sendResponse(['error' => 'Ação não encontrada'], 404);
        }
    }

    private function handlePost($action) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'card':
                $this->createCard($data);
                break;
            case 'move':
                $this->moveCard($data);
                break;
            case 'config':
                $this->updateConfig($data);
                break;
            default:
                $this->sendResponse(['error' => 'Ação não encontrada'], 404);
        }
    }

    private function handlePut($action) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'card':
                $this->updateCard($data);
                break;
            default:
                $this->sendResponse(['error' => 'Ação não encontrada'], 404);
        }
    }

    private function handleDelete($action) {
        $id = $_GET['id'] ?? null;
        
        switch ($action) {
            case 'card':
                $this->deleteCard($id);
                break;
            default:
                $this->sendResponse(['error' => 'Ação não encontrada'], 404);
        }
    }

    private function getAllCards() {
        $stmt = $this->db->query("
            SELECT c.*, GROUP_CONCAT(t.tag_name) as tags
            FROM cards c
            LEFT JOIN tags t ON c.id = t.card_id
            GROUP BY c.id
            ORDER BY c.column_index, c.position
        ");
        
        $cards = $stmt->fetchAll();
        
        // Converter tags de string para array
        foreach ($cards as &$card) {
            $card['tags'] = $card['tags'] ? explode(',', $card['tags']) : [];
        }
        
        $this->sendResponse(['success' => true, 'cards' => $cards]);
    }

    private function getConfig() {
        $stmt = $this->db->query("SELECT num_columns FROM board_config WHERE id = 1");
        $config = $stmt->fetch();
        
        $this->sendResponse(['success' => true, 'config' => $config]);
    }

    private function createCard($data) {
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $priority = $data['priority'] ?? 'medium';
        $columnIndex = $data['column_index'] ?? 0;
        $tags = $data['tags'] ?? [];

        if (empty($title)) {
            $this->sendResponse(['error' => 'Título é obrigatório'], 400);
            return;
        }

        // Obter próxima posição na coluna
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as next_pos FROM cards WHERE column_index = ?");
        $stmt->execute([$columnIndex]);
        $position = $stmt->fetch()['next_pos'];

        // Inserir card
        $stmt = $this->db->prepare("
            INSERT INTO cards (title, description, priority, column_index, position) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $priority, $columnIndex, $position]);
        
        $cardId = $this->db->lastInsertId();

        // Inserir tags
        foreach ($tags as $tag) {
            $tagStmt = $this->db->prepare("INSERT INTO tags (card_id, tag_name) VALUES (?, ?)");
            $tagStmt->execute([$cardId, trim($tag)]);
        }

        $this->sendResponse(['success' => true, 'card_id' => $cardId]);
    }

    private function moveCard($data) {
        $cardId = $data['card_id'] ?? null;
        $newColumnIndex = $data['column_index'] ?? null;
        $newPosition = $data['position'] ?? null;

        if (!$cardId || $newColumnIndex === null || $newPosition === null) {
            $this->sendResponse(['error' => 'Dados incompletos'], 400);
            return;
        }

        $this->db->beginTransaction();

        try {
            // Atualizar posição do card
            $stmt = $this->db->prepare("
                UPDATE cards 
                SET column_index = ?, position = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$newColumnIndex, $newPosition, $cardId]);

            // Reordenar outros cards na mesma coluna
            $stmt = $this->db->prepare("
                UPDATE cards 
                SET position = position + 1 
                WHERE column_index = ? AND position >= ? AND id != ?
            ");
            $stmt->execute([$newColumnIndex, $newPosition, $cardId]);

            $this->db->commit();
            $this->sendResponse(['success' => true]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function updateCard($data) {
        $id = $data['id'] ?? null;
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $priority = $data['priority'] ?? null;
        $tags = $data['tags'] ?? null;

        if (!$id) {
            $this->sendResponse(['error' => 'ID é obrigatório'], 400);
            return;
        }

        $this->db->beginTransaction();

        try {
            // Atualizar card
            $updates = [];
            $params = [];
            
            if ($title !== null) {
                $updates[] = "title = ?";
                $params[] = $title;
            }
            if ($description !== null) {
                $updates[] = "description = ?";
                $params[] = $description;
            }
            if ($priority !== null) {
                $updates[] = "priority = ?";
                $params[] = $priority;
            }
            
            if (!empty($updates)) {
                $updates[] = "updated_at = CURRENT_TIMESTAMP";
                $params[] = $id;
                
                $sql = "UPDATE cards SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Atualizar tags se fornecidas
            if ($tags !== null) {
                $this->db->prepare("DELETE FROM tags WHERE card_id = ?")->execute([$id]);
                
                foreach ($tags as $tag) {
                    $tagStmt = $this->db->prepare("INSERT INTO tags (card_id, tag_name) VALUES (?, ?)");
                    $tagStmt->execute([$id, trim($tag)]);
                }
            }

            $this->db->commit();
            $this->sendResponse(['success' => true]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function deleteCard($id) {
        if (!$id) {
            $this->sendResponse(['error' => 'ID é obrigatório'], 400);
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM cards WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->sendResponse(['success' => true]);
    }

    private function updateConfig($data) {
        $numColumns = $data['num_columns'] ?? null;

        if (!$numColumns || $numColumns < 2 || $numColumns > 6) {
            $this->sendResponse(['error' => 'Número de colunas inválido (2-6)'], 400);
            return;
        }

        $stmt = $this->db->prepare("UPDATE board_config SET num_columns = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
        $stmt->execute([$numColumns]);
        
        $this->sendResponse(['success' => true]);
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// Executar API
$api = new KanbanAPI();
$api->handleRequest();
