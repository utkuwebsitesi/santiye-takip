param(
    [string]$OutputDirectory = "dist"
)

$ErrorActionPreference = "Stop"
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$outputRoot = Join-Path $projectRoot $OutputDirectory
$stagingRoot = Join-Path $outputRoot (".staging-" + [guid]::NewGuid().ToString("N"))
$packageRoot = Join-Path $stagingRoot "santiye-kasa"
$timestamp = Get-Date -Format "yyyyMMdd-HHmm"
$archivePath = Join-Path $outputRoot "santiye-kasa-production-$timestamp.zip"

New-Item -ItemType Directory -Force -Path $packageRoot | Out-Null

$directories = @("app", "bootstrap", "config", "database", "public", "resources", "routes", "storage")
$rootFiles = @(
    ".env.example", ".gitignore", "artisan", "CHANGELOG.md", "composer.json",
    "composer.lock", "CPANEL.md", "CWP.md", "DEPLOY_360_NATEX.md", "INSTALL.md",
    "LIVE_INSTALL_CHECKLIST.md", "README.md", "SECURITY.md", "server.php"
)

foreach ($directory in $directories) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $packageRoot -Recurse
}
foreach ($file in $rootFiles) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination $packageRoot
}

Get-ChildItem -LiteralPath (Join-Path $packageRoot "database") -Filter "*.sqlite*" -File |
    Remove-Item -Force

Push-Location $packageRoot
try {
    & composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) {
        throw "Composer production kurulumu başarısız oldu."
    }
} finally {
    Pop-Location
}

# Composer/Artisan çalışırken oluşabilecek yerel çalışma verilerini paketten temizle.
$runtimeDirectories = @(
    "storage/app/private/documents",
    "storage/framework/cache",
    "storage/framework/sessions",
    "storage/framework/testing",
    "storage/framework/views",
    "storage/logs"
)
foreach ($relativePath in $runtimeDirectories) {
    $runtimePath = Join-Path $packageRoot $relativePath
    if (Test-Path -LiteralPath $runtimePath) {
        Get-ChildItem -LiteralPath $runtimePath -Force |
            Where-Object { $_.Name -ne ".gitignore" } |
            Remove-Item -Recurse -Force
    }
}
$installedLock = Join-Path $packageRoot "storage/app/installed.lock"
if (Test-Path -LiteralPath $installedLock) {
    Remove-Item -LiteralPath $installedLock -Force
}
$installerKey = Join-Path $packageRoot "storage/app/.installer-key"
if (Test-Path -LiteralPath $installerKey) {
    Remove-Item -LiteralPath $installerKey -Force
}

New-Item -ItemType Directory -Force -Path $outputRoot | Out-Null
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [IO.Compression.ZipFile]::Open($archivePath, [IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -LiteralPath $packageRoot -Recurse -File -Force | ForEach-Object {
        $entryName = $_.FullName.Substring($stagingRoot.Length + 1).Replace("\", "/")
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $_.FullName,
            $entryName,
            [IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $archive.Dispose()
}

$resolvedOutput = (Resolve-Path $outputRoot).Path
$resolvedStaging = (Resolve-Path $stagingRoot).Path
if (-not $resolvedStaging.StartsWith($resolvedOutput + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Geçici paket dizini beklenen çıktı klasörünün dışında."
}
Remove-Item -LiteralPath $resolvedStaging -Recurse -Force

Write-Output $archivePath
