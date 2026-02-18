# Mišelinská Komise

Restaurant review site for a friend group. Czech language. Live at **miselinskakomise.cz**.

---

## What This Repo Contains

```
miselinskakomise/
├── miselinska-komise/        # WordPress plugin (the entire site logic)
│   ├── miselinska-komise.php # Main plugin file — CPT, hooks, filters
│   ├── includes/             # CPT, taxonomy, meta fields, AJAX, templates
│   ├── shortcodes/           # [miselinska_mapa], [miselinska_feed], [miselinska_formular]
│   ├── templates/            # single-recenze.php, archive-recenze.php
│   └── assets/               # CSS + JS (Leaflet map, form AJAX, feed)
├── deploy.ps1                # Deploys plugin to EC2 (file-by-file SCP)
├── deploy.config.ps1         # GITIGNORED — server credentials, paths
├── wp-setup.ps1              # One-time WP setup (pages, menu, theme, locale)
├── CLAUDE.md                 # Instructions for AI agents working on this project
└── README.md                 # This file
```

`deploy.config.ps1` is gitignored. Recreate it by copying `deploy.config.ps1.example` and filling in real values.

---

## Infrastructure

WordPress runs in Docker on an EC2 instance (eu-central-1, Amazon Linux 2023):

```
miselinskakomise.cz
  → Cloudflare CDN
    → cloudflared tunnel (systemd service on EC2)
      → localhost:8080
        → Docker container: miselinska_app (WordPress/Apache)
```

On the server:
```
/home/ec2-user/miselinskakomise/
├── docker-compose.yml
├── wp_data/                  # WordPress files (volume → /var/www/html)
│   └── wp-content/plugins/miselinska-komise/   ← deployed here
└── db_data/                  # MariaDB data (never touch directly)
```

**Do not touch:** `cloudflared` config, `slurpjob.service` (port 5000, unrelated app on same tunnel).

---

## Plugin Overview

Everything is in a single plugin — no ACF, no page builders, no other dependencies.

| Feature | Implementation |
|---------|----------------|
| Custom Post Type | `recenze` — title, editor, author, thumbnail, REST API exposed |
| Meta fields | Rating (0–10), restaurant name, Google Maps URL, lat/lng, address, gallery |
| Taxonomy | `hashtag` — non-hierarchical, user-defined |
| Homepage | `[miselinska_mapa height="400"]` + `[miselinska_feed]` |
| Map page | `[miselinska_mapa]` — Leaflet + OpenStreetMap, color-coded pins, MarkerCluster |
| Review feed | `[miselinska_feed]` — card grid, newest first |
| Submit form | `[miselinska_formular]` — login-required, AJAX, photo upload |
| Theme | GeneratePress (lightweight, no sidebar, responsive) |

---

## Deployment

Edit plugin files locally in `miselinska-komise/`, then deploy:

```powershell
powershell.exe -ExecutionPolicy Bypass -File deploy.ps1
```

The script SCPs each file individually to the server and activates the plugin. **Never use `Compress-Archive` / zip** — it silently produces 0-byte PHP files.

After deploy, verify on the server (Pattern B — dot-source config for `$key`/`$server`):
```powershell
. "$PSScriptRoot\deploy.config.ps1"
ssh -i $key $server "sudo docker logs miselinska_app --tail 20"
```

See CLAUDE.md for the full verification checklist.

---

## Executing Commands from Windows (Agent Workflow)

The Bash tool in Claude Code on this machine runs Git Bash (MINGW64). **Simple Unix commands (`echo`, `uname`) fail silently.** Two reliable patterns only:

**Pattern A — local PowerShell only (no SSH, no credentials):**
```bash
powershell.exe -ExecutionPolicy Bypass -Command "Get-ChildItem ..."
```

**Pattern B — anything touching the server (always use for SSH/WP-CLI):**
```bash
# Step 1: Write script to tmp/ with the Write tool. Start with:
#   . "$PSScriptRoot\..\deploy.config.ps1"
#   Then use $key, $server, $wp_user, $wp_password freely.
# Step 2:
powershell.exe -ExecutionPolicy Bypass -File tmp/myscript.ps1
```

Rules:
- SSH always needs Pattern B — `$key`/`$server` dollar signs get stripped in inline `-Command "..."`
- Never run parallel Bash tool calls — they all fail together
- Never use `2>/dev/null` — you want to see errors
- `tmp/` is gitignored — safe for throwaway scripts

---

## Git Workflow

Branch: `main`. Commit directly, no PRs needed.

```bash
git add miselinska-komise/ deploy.ps1 wp-setup.ps1 CLAUDE.md README.md
git commit -m "brief description"
git push
```

`deploy.config.ps1` is gitignored — never commit it.

Commit only after the user visually confirms the change works in the browser.

---

## WP Admin Access

- URL: https://miselinskakomise.cz/wp-admin/
- Credentials: see `deploy.config.ps1` (gitignored) — copy from `deploy.config.ps1.example`

---

## Future Ideas

- Rating aggregation (multiple friends rate same restaurant → average)
- Filter/sort by cuisine, area, rating, price
- "Want to visit" draft list
- Comments on reviews for group discussion
- Blog posts cross-linking to restaurant cards
