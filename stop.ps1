# Para tudo que o start.ps1 subiu: frontend, supervisor + servico de ingestao e backend (Docker).
$env:PATH = "C:\Program Files\Docker\Docker\resources\bin;$env:PATH"

# Supervisor primeiro (8101), senao ele religa o servico de ingestao (8100).
foreach ($port in 8101, 8100, 3000) {
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
}

Set-Location $PSScriptRoot
docker compose down
Write-Host 'Tudo parado.' -ForegroundColor Green
