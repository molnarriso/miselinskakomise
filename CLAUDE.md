# CLAUDE.md — miselinskakomise.cz

Working instructions for Claude Code on this project.

## Access

All credentials live in `deploy.config.ps1` (gitignored). Copy `deploy.config.ps1.example`
and fill in the real values. Variables provided:

- `$key` — path to SSH private key
- `$server` — `ec2-user@<ip>`
- `$src` — local plugin source path
- `$remote` — remote plugin path on server
- `$wp_user` — WP admin username
- `$wp_password` — WP admin password

To use them in any script: dot-source the config at the top (see Pattern B in Shell section).

## Infrastructure

- WordPress runs in Docker container `miselinska_app` on port 8080
- Plugin files live at: `/home/ec2-user/miselinskakomise/wp_data/wp-content/plugins/miselinska-komise/`
- Traffic: `miselinskakomise.cz → Cloudflare → cloudflared tunnel → localhost:8080`
- Do not touch: SlurpJob service (port 5000), cloudflared config

## Shell / Command Execution

The Bash tool runs inside Git Bash (MINGW64) on Windows. Simple Unix
commands (`echo`, `uname`, `whoami`) fail silently with exit code 1.
`$VAR` inside inline PowerShell strings also gets stripped by the Bash tool.
Do not debug either of these — work around them.

**Two patterns, nothing else:**

**Pattern A — local commands only (no SSH, no vars):**
```
powershell.exe -ExecutionPolicy Bypass -Command "Get-ChildItem ..."
```
Do NOT use Pattern A for SSH — `$key`/`$server` dollar signs get stripped by the Bash tool.

**Pattern B — everything that touches the server (always use this for SSH/WP-CLI):**
```
# 1. Write script with the Write tool to C:\Development\miselinskakomise\tmp\task.ps1
#    Start with: . "$PSScriptRoot\..\deploy.config.ps1"
#    Then use $key, $server, $src, $remote, $wp_user, $wp_password freely
# 2. powershell.exe -ExecutionPolicy Bypass -File C:\Development\miselinskakomise\tmp\task.ps1
# 3. The tmp/ folder is disposable — no cleanup needed
```

Never run multiple Bash tool calls in parallel — they often all fail together.
Never use `2>/dev/null` — always let errors surface.

## Git Workflow

Branch: `main`. Commit directly — no feature branches needed for small changes.

```
git add miselinska-komise/ deploy.ps1
git commit -m "short description"
git push
```

`deploy.config.ps1` is gitignored — it holds all credentials and paths.
On a new machine, copy `deploy.config.ps1.example` and fill in the real values.

## Deployment

Run from the Bash tool:

```
powershell.exe -ExecutionPolicy Bypass -File C:\Development\miselinskakomise\deploy.ps1
```

What the script does, in order:
1. Dot-sources `deploy.config.ps1` to load `$key`, `$server`, `$src`, `$remote`
2. Enumerates every file under `miselinska-komise/` recursively
3. For each file: creates the remote directory if needed, SCPs the file to `/tmp/_deploy_file`, then moves it into place with correct `www-data:www-data` ownership
4. PHP lint check on the main plugin file — output printed; a fatal here means broken deploy
5. `wp plugin activate miselinska-komise` — idempotent, safe every time
6. `wp plugin list` — printed so you can confirm the plugin shows `active`

**Never use `Compress-Archive` or zip** — it silently produces 0-byte files for UTF-8 PHP content. Always deploy with this script.

If any file shows `=> ` with no `OK`, the move failed — check permissions or disk space on the server.

## Testing Approach

After every deploy, verify via Pattern B script (dot-source config, use `$key`/`$server`):

| Check | Remote command (value of `"..."` in `ssh -i $key $server "..."`) |
|-------|---------|
| PHP errors / fatal | `sudo docker logs miselinska_app --tail 50` |
| Plugin active | `sudo docker exec miselinska_app wp plugin list --allow-root` |
| CPT registered | `sudo docker exec miselinska_app wp post-type list --allow-root` |
| REST API returns reviews | `curl -s http://localhost:8080/wp-json/wp/v2/recenze` |
| Pages exist | `sudo docker exec miselinska_app wp post list --post_type=page --allow-root` |
| AJAX handler registered | `sudo docker exec miselinska_app wp eval 'echo has_action("wp_ajax_mk_submit_review");' --allow-root` |

I cannot open a browser — I have no visual access to the site.

## Feature Implementation Workflow

For every feature / significant change:

1. Write code locally in `miselinska-komise/`
2. Run `deploy.ps1`
3. SSH-verify: check logs, WP-CLI, REST API as appropriate
4. **Ask the user to visually confirm in the browser** — always required
5. User reports what looks wrong → fix → repeat from step 2
6. Once confirmed working: `git add`, `git commit`, `git push`

Never consider a feature done without visual confirmation from the user.
Never commit until the feature is confirmed working.

## WP-CLI Reference

All WP-CLI commands run via Pattern B. Template:

```powershell
. "$PSScriptRoot\..\deploy.config.ps1"
ssh -i $key $server "sudo docker exec miselinska_app wp <COMMAND> --allow-root"
```

Common `<COMMAND>` values:

```powershell
# Activate / deactivate plugin
plugin activate miselinska-komise
plugin deactivate miselinska-komise

# Flush rewrite rules (always after CPT/taxonomy changes)
rewrite flush

# Create a page
post create --post_type=page --post_title='Domů' --post_status=publish --porcelain

# Set static front page
option update show_on_front page
option update page_on_front <ID>

# Install and activate theme
theme install generatepress --activate

# Set locale / timezone
option update WPLANG cs_CZ
option update timezone_string Europe/Prague

# Create nav menu
menu create "Hlavní menu" --porcelain
menu item add-post <menu-id> <post-id>
menu location assign <menu-id> primary

# Tail WordPress logs (this one runs on the host, not in the container)
# ssh -i $key $server "sudo docker logs miselinska_app --tail 100"
```

## Do Not Touch

- `/etc/cloudflared/config.yml` — shared tunnel config
- `slurpjob.service` — separate app on port 5000
- `db_data/` — never modify directly
