#!/bin/bash

# Script para iniciar o Kanban Dashboard com servidor built-in do PHP

echo "🥋 Iniciando Kanban Dashboard..."
echo ""
echo "Servidor rodando em: http://localhost:8000"
echo "Pressione Ctrl+C para parar"
echo ""

# Verificar se PHP está instalado
if ! command -v php &> /dev/null
then
    echo "❌ PHP não encontrado! Instale o PHP primeiro."
    exit 1
fi

# Verificar versão do PHP
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
echo "✅ PHP $PHP_VERSION detectado"

# Verificar se PDO SQLite está disponível
if ! php -m | grep -q "pdo_sqlite"
then
    echo "⚠️  AVISO: Extensão PDO SQLite pode não estar disponível"
    echo "   Instale com: sudo apt-get install php-sqlite3 (Ubuntu/Debian)"
    echo "   ou: brew install php (macOS com Homebrew)"
fi

echo ""

# Criar diretório data se não existir
mkdir -p data

# Iniciar servidor
php -S localhost:8000
