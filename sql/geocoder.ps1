$csvPath = "C:\Temp\sites_a_geocoder.csv"
$sqlPath = "C:\Temp\update_coordonnees.sql"

if (-not (Test-Path "C:\Temp")) { New-Item -ItemType Directory -Path "C:\Temp" | Out-Null }

if (-not (Test-Path $csvPath)) {
    Write-Host "ERREUR : fichier $csvPath introuvable." -ForegroundColor Red
    exit
}

$sites = Import-Csv -Path $csvPath -Encoding UTF8
$total = $sites.Count
Write-Host "$total sites a geocoder..." -ForegroundColor Cyan

$sqlLines = @()
$sqlLines += "-- Coordonnees GPS generees le $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')"
$sqlLines += "-- Source : api-adresse.data.gouv.fr"
$sqlLines += "-- Importer dans phpMyAdmin sur le serveur"
$sqlLines += ""
$sqlLines += "USE ft_speedtest;"
$sqlLines += ""

$ok      = 0
$skipped = 0
$errors  = 0

for ($i = 0; $i -lt $sites.Count; $i++) {
    $site    = $sites[$i]
    $code    = $site.CODE_GX_SITE.Trim()
    $nom     = $site.NOM_SITE.Trim()
    $adresse = $site.ADRESSE.Trim()
    $cp      = $site.CODE_POSTAL.Trim().PadLeft(5, '0')
    $ville   = $site.VILLE.Trim()

    if (-not $code -or -not $adresse -or -not $ville) {
        $skipped++
        continue
    }

    try {
        $query    = [uri]::EscapeDataString("$adresse $ville")
        $url      = "https://api-adresse.data.gouv.fr/search/?q=$query&postcode=$cp&limit=1"
        $response = Invoke-RestMethod -Uri $url -TimeoutSec 5 -ErrorAction Stop
        $features = $response.features

        if (-not $features -or $features.Count -eq 0) {
            $query2   = [uri]::EscapeDataString($ville)
            $url2     = "https://api-adresse.data.gouv.fr/search/?q=$query2&postcode=$cp&limit=1"
            $response = Invoke-RestMethod -Uri $url2 -TimeoutSec 5 -ErrorAction Stop
            $features = $response.features
        }

        if (-not $features -or $features.Count -eq 0) {
            $skipped++
            $sqlLines += "-- AUCUN RESULTAT : $code ($nom)"
            Write-Host "  [$($i+1)/$total] $code - aucun resultat" -ForegroundColor Yellow
            continue
        }

        $feat     = $features[0]
        $lat      = $feat.geometry.coordinates[1]
        $lng      = $feat.geometry.coordinates[0]
        $score    = [math]::Round($feat.properties.score * 100)
        $label    = $feat.properties.label
        $codeEsc  = $code -replace "'", "''"

        $sqlLines += "UPDATE FT_SITE SET LATITUDE = $lat, LONGITUDE = $lng WHERE CODE_GX_SITE = '$codeEsc'; -- $score% $label"
        $ok++
        Write-Host "  [$($i+1)/$total] $code - OK ($score%) $label" -ForegroundColor Green

    } catch {
        $errors++
        $msg = $_.Exception.Message
        $sqlLines += "-- ERREUR : $code ($nom) - $msg"
        Write-Host "  [$($i+1)/$total] $code - ERREUR : $msg" -ForegroundColor Red
    }

    Start-Sleep -Milliseconds 50
}

$sqlLines | Out-File -FilePath $sqlPath -Encoding UTF8 -Force

Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "Termine !" -ForegroundColor Cyan
Write-Host "  OK            : $ok" -ForegroundColor Green
Write-Host "  Sans resultat : $skipped" -ForegroundColor Yellow
Write-Host "  Erreurs       : $errors" -ForegroundColor Red
Write-Host ""
Write-Host "Fichier SQL : $sqlPath" -ForegroundColor Cyan
