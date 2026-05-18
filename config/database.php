<?php
// Configuração do banco de dados SQLite

define('DB_PATH', __DIR__ . '/../data/kanban.db');

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Criar diretório de dados se não existir
            $dataDir = dirname(DB_PATH);
            if (!file_exists($dataDir)) {
                mkdir($dataDir, 0755, true);
            }

            // Conectar ao SQLite
            $this->connection = new PDO('sqlite:' . DB_PATH);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Inicializar tabelas se necessário
            $this->initializeTables();
        } catch (PDOException $e) {
            die("Erro na conexão com banco de dados: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function initializeTables() {
        $sql = "
            CREATE TABLE IF NOT EXISTS cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                priority TEXT DEFAULT 'medium',
                column_index INTEGER NOT NULL DEFAULT 0,
                position INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                card_id INTEGER NOT NULL,
                tag_name TEXT NOT NULL,
                FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS board_config (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                num_columns INTEGER NOT NULL DEFAULT 4,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_cards_column ON cards(column_index, position);
            CREATE INDEX IF NOT EXISTS idx_tags_card ON tags(card_id);
        ";

        $this->connection->exec($sql);

        // Inserir configuração padrão se não existir
        $stmt = $this->connection->query("SELECT COUNT(*) as count FROM board_config");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $this->connection->exec("INSERT INTO board_config (id, num_columns) VALUES (1, 4)");
            $this->seedInitialData();
        }
    }

    private function seedInitialData() {
        $sampleCards = [
            ['title' => 'Implementar autenticação JWT', 'priority' => 'high', 'tags' => ['Backend', 'Security']],
            ['title' => 'Refatorar código PHP legado', 'priority' => 'medium', 'tags' => ['Refactoring', 'PHP']],
            ['title' => 'Criar dashboard de analytics', 'priority' => 'low', 'tags' => ['Frontend', 'UI']],
            ['title' => 'Corrigir bug no checkout', 'priority' => 'high', 'tags' => ['Bug', 'Critical']],
            ['title' => 'Documentar API REST', 'priority' => 'medium', 'tags' => ['Docs', 'API']],
            ['title' => 'Otimizar queries do banco', 'priority' => 'high', 'tags' => ['Database', 'Performance']],
            ['title' => 'Implementar testes unitários', 'priority' => 'medium', 'tags' => ['Testing', 'QA']],
            ['title' => 'Design responsivo mobile', 'priority' => 'low', 'tags' => ['Frontend', 'Mobile']],
        ];

        foreach ($sampleCards as $index => $card) {
            $stmt = $this->connection->prepare(
                "INSERT INTO cards (title, priority, column_index, position) VALUES (?, ?, 0, ?)"
            );
            $stmt->execute([$card['title'], $card['priority'], $index]);
            
            $cardId = $this->connection->lastInsertId();
            
            foreach ($card['tags'] as $tag) {
                $tagStmt = $this->connection->prepare("INSERT INTO tags (card_id, tag_name) VALUES (?, ?)");
                $tagStmt->execute([$cardId, $tag]);
            }
        }
    }
}
