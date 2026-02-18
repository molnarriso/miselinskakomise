. "$PSScriptRoot\deploy.config.ps1"

# SCP each file individually — never use Compress-Archive, it silently
# produces 0-byte files for UTF-8 PHP content.
$files = Get-ChildItem -Path $src -Recurse -File
Write-Host "Uploading $($files.Count) files..."

foreach ($file in $files) {
    $rel       = $file.FullName.Substring($src.Length + 1).Replace('\', '/')
    $remoteDir = $remote + '/' + ($rel -replace '/[^/]+$', '')

    ssh -i $key $server "sudo mkdir -p $remoteDir" 2>&1 | Out-Null
    scp -i $key $file.FullName "${server}:/tmp/_deploy_file" 2>&1 | Out-Null
    $mv = ssh -i $key $server "sudo mv /tmp/_deploy_file $remote/$rel && sudo chown www-data:www-data $remote/$rel && echo OK" 2>&1
    Write-Host "  $rel => $mv"
}

# ── PHP syntax check ──────────────────────────────────────────────────
Write-Host ""
$lint = ssh -i $key $server "sudo docker exec miselinska_app php -l /var/www/html/wp-content/plugins/miselinska-komise/miselinska-komise.php 2>&1" 2>&1
Write-Host "PHP lint: $lint"

# ── Activate + verify ─────────────────────────────────────────────────
Write-Host ""
$act = ssh -i $key $server "sudo docker exec miselinska_app wp plugin activate miselinska-komise --allow-root 2>&1" 2>&1
Write-Host "Activate: $act"

$pl = ssh -i $key $server "sudo docker exec miselinska_app wp plugin list --allow-root 2>&1" 2>&1
Write-Host "Plugins:`n$pl"
