# Sobe tudo: Docker (Postgres + API + tempo real), supervisor de ingestao (oculto) e frontend.
# Na primeira execucao tambem instala as dependencias.
$ErrorActionPreference = 'Stop'

# Roda um comando externo e para o script se ele falhar.
function Run([string]$command, [string[]]$arguments) {
    $ErrorActionPreference = 'Continue'
    & $command @arguments
    if ($LASTEXITCODE -ne 0) { throw "Falhou: $command $arguments" }
}
$root = $PSScriptRoot
$env:PATH = "C:\Program Files\Docker\Docker\resources\bin;C:\Program Files\nodejs;$env:PATH"

function Wait-Until([scriptblock]$check, [string]$what, [int]$seconds = 180) {
    $deadline = (Get-Date).AddSeconds($seconds)
    while (-not (& $check)) {
        if ((Get-Date) -gt $deadline) { throw "Tempo esgotado esperando $what." }
        Start-Sleep 2
    }
}

function Test-Url([string]$url) {
    try { Invoke-WebRequest $url -UseBasicParsing -TimeoutSec 2 | Out-Null; $true }
    catch { [bool]$_.Exception.Response }
}

function Test-Docker {
    cmd /c 'docker info >nul 2>&1'
    $LASTEXITCODE -eq 0
}

function Start-Window([string]$title, [string]$dir, [string]$command) {
    Start-Process powershell -WorkingDirectory $dir -ArgumentList '-NoExit', '-Command',
        "`$Host.UI.RawUI.WindowTitle = '$title'; $command"
}

# Garante uma chave aleatoria em um arquivo .env (sem sobrescrever a que ja existe).
function Set-SecretIfMissing([string]$file, [string]$name) {
    if (-not (Test-Path $file)) { New-Item -ItemType File $file | Out-Null }
    if (-not (Select-String -Path $file -Pattern "^$name=." -Quiet)) {
        $bytes = New-Object byte[] 24
        [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
        Add-Content -Path $file -Value "$name=$(-join ($bytes | ForEach-Object { $_.ToString('x2') }))" -Encoding ascii
    }
}

Write-Host '> Docker'
if (-not (Test-Docker)) {
    Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
    Wait-Until { Test-Docker } 'o Docker Desktop'
}

Write-Host '> Dependencias e segredos locais'
Set-Location $root
foreach ($name in 'JWT_SECRET', 'MESSAGE_INGESTION_API_KEY', 'REALTIME_API_KEY') {
    Set-SecretIfMissing "$root\apps\backend\.env.local" $name
}
if (-not (Test-Path 'node_modules')) { Run npm ci }
if (-not (Test-Path 'apps\backend\vendor')) {
    foreach ($dir in 'packages/shared', 'modules/auth', 'modules/message-classification', 'apps/backend') {
        Run docker compose, run, --rm, --no-deps, php, composer, install, --no-interaction, "--working-dir=$dir"
    }
}
if (-not (Test-Path 'apps\ingestion\.venv')) { Push-Location apps\ingestion; Run uv sync; Pop-Location }
if (-not (Test-Path 'apps\ingestion\.env')) {
    $apiKey = (Select-String -Path 'apps\backend\.env.local' -Pattern '^MESSAGE_INGESTION_API_KEY=(.+)$').Matches[0].Groups[1].Value
    (Get-Content 'apps\ingestion\.env.example') -replace '^BACKEND_INGESTION_API_KEY=.*$', "BACKEND_INGESTION_API_KEY=$apiKey" |
        Set-Content 'apps\ingestion\.env' -Encoding utf8
}

Write-Host '> Backend (Postgres + API + tempo real)'
Run docker compose, up, -d

# Modelo de texto que traduz as mensagens para o ingles antes do Laya (TEXT_MODEL no .env da ingestao).
# Sem ele o Laya classifica o texto original, com menos acerto.
Write-Host '> Ollama (traducao para o Laya)'
$ollama = "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe"
$textModel = (Select-String -Path 'apps\ingestion\.env' -Pattern '^TEXT_MODEL=(.+)$' | Select-Object -First 1).Matches.Groups[1].Value
if (-not $textModel) { $textModel = 'llama3.2:3b' }
if (Test-Path $ollama) {
    if (-not (Test-Url 'http://localhost:11434')) {
        Start-Process $ollama -ArgumentList 'serve' -WindowStyle Hidden
        Wait-Until { Test-Url 'http://localhost:11434' } 'o Ollama'
    }
    if (-not (& $ollama list | Select-String -Quiet -SimpleMatch $textModel)) { Run $ollama pull, $textModel }
} else {
    Write-Host '  Ollama nao encontrado: as mensagens serao classificadas sem traducao.' -ForegroundColor Yellow
}

Write-Host '> Supervisor de ingestao (oculto) e frontend'
# O supervisor liga o servico de ingestao (Laya + leitura das lives) quando um monitoramento e
# iniciado no app e desliga quando nenhum esta rodando. Sem janela: logs em apps\ingestion\logs.
if (-not (Test-Url 'http://127.0.0.1:8101/health')) {
    Start-Process uv -ArgumentList 'run', '--env-file', '.env', 'python', 'ingestion_supervisor.py' `
        -WorkingDirectory "$root\apps\ingestion" -WindowStyle Hidden
}
if (-not (Test-Url 'http://localhost:3000')) {
    Start-Window 'Frontend' "$root\apps\frontend" 'npm run dev'
}

Wait-Until { Test-Url 'http://localhost:8000' } 'a API'
Wait-Until { Test-Url 'http://localhost:8200/health' } 'o servidor de tempo real'
Wait-Until { Test-Url 'http://127.0.0.1:8101/health' } 'o supervisor de ingestao'
Wait-Until { Test-Url 'http://localhost:3000' } 'o frontend'

Write-Host ''
Write-Host 'Pronto:' -ForegroundColor Green
Write-Host '  Frontend    http://localhost:3000  (login em /join, monitoramentos em /monitors)'
Write-Host '  API         http://localhost:8000'
Write-Host '  Tempo real  ws://localhost:8200/ws'
Write-Host '  Ingestao    liga/desliga sozinha ao iniciar/pausar monitoramentos (logs em apps\ingestion\logs)'
Write-Host 'No Chrome: chrome://inspect/#remote-debugging -> "Allow remote debugging for this browser instance".' -ForegroundColor Yellow
