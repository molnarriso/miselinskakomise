. "$PSScriptRoot\deploy.config.ps1"

function WP { param([string]$cmd)
    $r = & ssh -i $key $server "sudo docker exec miselinska_app wp $cmd --allow-root 2>&1"
    Write-Host "wp $cmd => $r"
    return $r
}

# ── Locale & timezone ───────────────────────────────────────────────
WP "option update WPLANG cs_CZ"
WP "option update timezone_string Europe/Prague"
WP "option update date_format 'j. n. Y'"
WP "option update blogname 'Mišelinská Komise'"
WP "option update blogdescription 'Restaurační recenze přátel'"

# ── Pretty permalinks ────────────────────────────────────────────────
WP "option update permalink_structure '/%postname%/'"
WP "rewrite flush"

# ── Install & activate GeneratePress ────────────────────────────────
WP "theme install generatepress --activate"

# ── Flush rewrites after CPT registration ───────────────────────────
WP "rewrite flush"

# ── Create pages ────────────────────────────────────────────────────
$home_id = & ssh -i $key $server "sudo docker exec miselinska_app wp post create --post_type=page --post_title='Domů' --post_content='[miselinska_mapa height=""400""]
[miselinska_feed]' --post_status=publish --porcelain --allow-root 2>&1"
Write-Host "Home page ID: $home_id"

$map_id = & ssh -i $key $server "sudo docker exec miselinska_app wp post create --post_type=page --post_title='Mapa' --post_content='[miselinska_mapa]' --post_status=publish --porcelain --allow-root 2>&1"
Write-Host "Map page ID: $map_id"

$form_id = & ssh -i $key $server "sudo docker exec miselinska_app wp post create --post_type=page --post_title='Nová recenze' --post_content='[miselinska_formular]' --post_status=publish --porcelain --allow-root 2>&1"
Write-Host "Form page ID: $form_id"

$reviews_id = & ssh -i $key $server "sudo docker exec miselinska_app wp post create --post_type=page --post_title='Recenze' --post_content='' --post_status=publish --porcelain --allow-root 2>&1"
Write-Host "Reviews page ID: $reviews_id"

# ── Set static front page ────────────────────────────────────────────
WP "option update show_on_front page"
WP "option update page_on_front $home_id"

# ── Create nav menu ──────────────────────────────────────────────────
$menu_id = & ssh -i $key $server "sudo docker exec miselinska_app wp menu create 'Hlavní menu' --porcelain --allow-root 2>&1"
Write-Host "Menu ID: $menu_id"

& ssh -i $key $server "sudo docker exec miselinska_app wp menu item add-post $menu_id $home_id --title='Domů' --allow-root 2>&1"
& ssh -i $key $server "sudo docker exec miselinska_app wp menu item add-custom $menu_id 'Recenze' '/recenze/' --allow-root 2>&1"
& ssh -i $key $server "sudo docker exec miselinska_app wp menu item add-post $menu_id $map_id --allow-root 2>&1"
& ssh -i $key $server "sudo docker exec miselinska_app wp menu item add-post $menu_id $form_id --allow-root 2>&1"
& ssh -i $key $server "sudo docker exec miselinska_app wp menu location assign $menu_id primary --allow-root 2>&1"

# ── Final flush ──────────────────────────────────────────────────────
WP "rewrite flush"

# ── Check logs for errors ────────────────────────────────────────────
Write-Host ""
Write-Host "=== PHP error check ==="
$logs = & ssh -i $key $server "sudo docker logs miselinska_app --tail 10 2>&1"
Write-Host $logs

Write-Host ""
Write-Host "=== Final plugin list ==="
WP "plugin list"

Write-Host ""
Write-Host "=== Post types ==="
WP "post-type list"

Write-Host ""
Write-Host "=== REST API check ==="
$rest = & ssh -i $key $server "curl -s http://localhost:8080/wp-json/wp/v2/recenze 2>&1"
Write-Host "REST recenze: $rest"
