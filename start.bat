@echo off
REM Script para iniciar o Kanban Dashboard no Windows

echo.
echo 🥋 Iniciando Kanban Dashboard...
echo.
echo Servidor rodando em: http://localhost:8000
echo Pressione Ctrl+C para parar
echo.

REM Verificar se PHP está instalado
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo ❌ PHP não encontrado! Instale o PHP primeiro.
    echo Baixe em: https://windows.php.net/download/
    pause
    exit /b 1
)

REM Mostrar versão do PHP
php -v | findstr /C:"PHP"
echo.

REM Criar diretório data se não existir
if not exist "data" mkdir data

REM Iniciar servidor
php -S localhost:8000

pause
