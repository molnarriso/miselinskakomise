# CLAUDE.md — miselinskakomise.cz

Working instructions for Claude Code on this project.

## Access

```
SSH key:  C:\Development\SlurpJob\slurpjob.pem
EC2 host: ec2-user@3.127.242.167
WP user:  molnarriso@gmail.com
WP pass:  DAck3HuzTg7a)2p#3W
```

SSH shorthand used in all commands below:
```bash
ssh -i "C:/Development/SlurpJob/slurpjob.pem" ec2-user@3.127.242.167
```

WP-CLI shorthand (run any `wp` command inside the container):
```bash
ssh -i "C:/Development/SlurpJob/slurpjob.pem" ec2-user@3.127.242.167 "sudo docker exec miselinska_app wp <command> --allow-root"
```

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

Simple one-liners (SSH, WP-CLI):
```
powershell.exe -Command "(ssh -i 'C:\Development\SlurpJob\slurpjob.pem' ec2-user@3.127.242.167 'REMOTE CMD') | Write-Host"
```

Anything needing PowerShell variables or logic — write to `tmp/`, run, done:
```
# 1. Write script with the Write tool to C:\Development\miselinskakomise\tmp\task.ps1
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

`deploy.config.ps1` is gitignored — it holds server address and SSH key path.
On a new machine, create it manually using the values in the Access section above.

## Deployment

Deploy by running `deploy.ps1` — it SCPs each plugin file individually to the
server and activates the plugin. Always use this script; never use
`Compress-Archive` to package PHP files as it silently produces 0-byte files
for UTF-8 content.

```
powershell.exe -ExecutionPolicy Bypass -File C:\Development\miselinskakomise\deploy.ps1
```

The script prints each uploaded file, runs a PHP lint check, and confirms the
plugin is active. The activate call is idempotent — safe to run every time.

## Testing Approach

After every deploy I verify via SSH:

| Check | Command |
|-------|---------|
| PHP errors / fatal | `sudo docker logs miselinska_app --tail 50` |
| Plugin active | `wp plugin list` |
| CPT registered | `wp post-type list` |
| REST API returns reviews | `curl -s http://localhost:8080/wp-json/wp/v2/recenze` |
| Pages exist | `wp post list --post_type=page` |
| AJAX handler registered | `wp eval 'echo has_action("wp_ajax_mk_submit_review");'` |

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

```bash
# Activate / deactivate plugin
wp plugin activate miselinska-komise --allow-root
wp plugin deactivate miselinska-komise --allow-root

# Flush rewrite rules (always after CPT/taxonomy changes)
wp rewrite flush --allow-root

# Create a page
wp post create --post_type=page --post_title='Domů' --post_status=publish --allow-root

# Set static front page
wp option update show_on_front page --allow-root
wp option update page_on_front <ID> --allow-root

# Install and activate theme
wp theme install generatepress --activate --allow-root

# Set locale / timezone
wp option update WPLANG cs_CZ --allow-root
wp option update timezone_string Europe/Prague --allow-root

# Create nav menu
wp menu create "Hlavní menu" --allow-root
wp menu item add-post <menu-id> <post-id> --allow-root
wp menu location assign <menu-id> primary --allow-root

# Tail WordPress logs
sudo docker logs miselinska_app -f --tail 100
```

## Do Not Touch

- `/etc/cloudflared/config.yml` — shared tunnel config
- `slurpjob.service` — separate app on port 5000
- `db_data/` — never modify directly
