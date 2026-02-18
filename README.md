# Mišelinská Komise — WordPress Plugin Spec

## Context

- **Site**: miselinskakomise.cz — friend group restaurant review blog (Czech language)
- **Infra**: WordPress on EC2, behind Cloudflare Tunnel, Nginx reverse proxy. WordPress is already installed (default/empty).
- **Access**: SSH to EC2
- **Language**: Czech (all UI, labels, admin)

## What to Build

A single WordPress plugin (`miselinska-komise`) that adds:

### 1. Custom Post Type: "Recenze" (Review)

- Slug: `/recenze/`
- Supports: title, editor (review body), author, thumbnail
- Show in REST API (ACF fields exposed via REST too)

### 2. Custom Meta Fields (no ACF — custom meta boxes, zero dependencies)

| Field | Meta Key | Type | Details |
|-------|----------|------|---------|
| Hodnocení (Rating) | `_mk_rating` | float | 0–10, step 0.1, required |
| Název restaurace | `_mk_restaurant_name` | string | Required |
| Google Maps odkaz | `_mk_google_maps_url` | url | Required. JS auto-extracts lat/lng from URL (regex, no API needed — parse `@lat,lng` patterns). Shortened goo.gl links → user fills GPS manually. |
| Zeměpisná šířka (Latitude) | `_mk_latitude` | float | Auto-filled from Maps URL or manual |
| Zeměpisná délka (Longitude) | `_mk_longitude` | float | Auto-filled from Maps URL or manual |
| Adresa | `_mk_address` | string | Optional |
| Galerie fotek | `_mk_gallery` | array | Attachment IDs, WP media uploader |

All fields registered with `register_post_meta()` + `show_in_rest` for REST API exposure. Author = WP post author. Review body = standard editor content.

### 3. Taxonomy: "Hashtag"

- Non-hierarchical (tag-like), slug `/hashtag/`
- Users add freely when creating reviews (#pizza, #vegan, #brno, etc.)

### 4. Map Page — `[miselinska_mapa]` shortcode

- **Leaflet.js + OpenStreetMap** (free, no API key)
- Fetch all reviews via WP REST API, plot as markers
- Marker popup: restaurant name, rating, thumbnail, hashtags, link to review
- Color-coded pins by rating (green 8+, yellow 5–8, red <5)
- MarkerCluster plugin for dense areas
- Default view: centered on Czech Republic, auto-fit to markers

### 5. Review Feed — `[miselinska_feed]` shortcode

- Card grid of latest reviews (thumbnail, name, rating badge, author, date, hashtags, excerpt)
- Chronological order (newest first)
- Params: `count`, `hashtag`, `orderby` (date/rating)

### Homepage Layout

Top-to-bottom:
1. **Header/nav** — site name, menu (Recenze, Mapa, Nová recenze), login link / logged-in user name
2. **Compact map** — `[miselinska_mapa height="400"]` — all pins, same functionality as full map page but shorter
3. **Chronological review feed** — `[miselinska_feed]` — latest reviews as cards, scrolling down

### 6. Frontend Submission Form — `[miselinska_formular]` shortcode

- Login-required (show message + login link if not logged in)
- Fields: restaurant name, rating (0–10), Google Maps link (auto-extract GPS), address, hashtags (autocomplete from existing), photo upload (multiple), review text
- Creates a published `review` post on submit
- First uploaded photo = featured image, rest → gallery
- AJAX submission, nonce-secured

### 7. Templates

- `single-review.php` — full review page (restaurant name, prominent rating, author, date, hashtags, photos, body text, small Leaflet map, Google Maps link)
- `archive-review.php` — paginated review listing

## Two Content Types

1. **Reviews (Recenze)** — structured cards with ratings, location, map integration
2. **Blog Posts** — standard WP posts for longer content ("Top 5 pizza in Ostrava", trip reports). Can reference reviews.

## WordPress Setup

- No external plugin dependencies — everything is in the custom plugin
- Czech locale (`cs_CZ`), timezone `Europe/Prague`, date format `j. n. Y`
- Pretty permalinks (`/%postname%/`)
- Create pages: Domů (homepage with `[miselinska_mapa height="400"]` + `[miselinska_feed]`), Mapa (`[miselinska_mapa]` full page), Nová recenze (`[miselinska_formular]`)
- Set static front page to Domů
- Create nav menu: Domů, Recenze (/recenze/), Mapa, Nová recenze
- User accounts: me = Administrator, friends = Author role
- Install a clean lightweight responsive theme (GeneratePress or similar) — rely on the theme for mobile responsiveness, no custom responsive CSS needed
- Site must be mobile-friendly — friends will mostly browse/submit on phones

## Server Access & Infrastructure

### SSH Connection

```powershell
ssh -i "C:\Development\SlurpJob\slurpjob.pem" ec2-user@3.127.242.167
```

- **Host**: `3.127.242.167` (AWS EC2, eu-central-1)
- **User**: `ec2-user`
- **Key**: `C:\Development\SlurpJob\slurpjob.pem` (shared with the SlurpJob project)
- **OS**: Amazon Linux 2023, ARM64 (aarch64)

### How WordPress Runs

WordPress runs in **Docker** via docker-compose, managed from:

```
/home/ec2-user/miselinskakomise/
├── docker-compose.yml      # Service definitions
├── .env                    # DB credentials (not in git)
├── wp_data/                # WordPress files volume → /var/www/html in container
└── db_data/                # MariaDB data volume
```

**Containers:**

| Container | Image | Host Port | Role |
|-----------|-------|-----------|------|
| `miselinska_app` | `wordpress:latest` | `8080:80` | WordPress PHP/Apache |
| `miselinska_db` | `mariadb:10.11` | internal only | Database |
| `wud` | `getwud/wud` | — | Auto-updates containers on new image releases |

### Traffic Flow

```
miselinskakomise.cz
  → Cloudflare (CDN/proxy)
    → cloudflared tunnel (systemd service on EC2)
      → localhost:8080
        → miselinska_app Docker container
          → WordPress
```

The Cloudflare tunnel config lives at `/etc/cloudflared/config.yml`. The same tunnel also routes `slurpjob.com → localhost:5000` — that is a separate unrelated service, do not touch it.

### Useful Commands on the Server

```bash
# Check container status
sudo docker ps

# View WordPress logs
sudo docker logs miselinska_app

# Restart WordPress container
sudo docker compose -f /home/ec2-user/miselinskakomise/docker-compose.yml restart wordpress

# Shell inside WordPress container
sudo docker exec -it miselinska_app bash

# WP-CLI inside container
sudo docker exec -it miselinska_app wp --info --allow-root
```

### Co-located Services (do not disturb)

- **SlurpJob** (`slurpjob.service`) — separate app on port 5000, routed via the same Cloudflare tunnel to `slurpjob.com`
- **cloudflared** — single tunnel serving both `miselinskakomise.cz` and `slurpjob.com`; config at `/etc/cloudflared/config.yml`

## Nice-to-Have (Future)

- Rating aggregation (multiple friends rate same restaurant → average)
- "Want to visit" draft list
- Filter/sort page (by cuisine, area, rating, price)
- Comments on reviews for group discussion
- RSS feed / email digest of new reviews
- Blog posts cross-linking to restaurant cards

## Technical Notes

- All meta fields registered with `register_post_meta()` + `show_in_rest: true` — automatically available in REST API, no extra plugins needed
- Leaflet/OSM CDN — enqueue only on pages using map shortcode
- Frontend form: `wp_ajax` handlers, `media_handle_upload()` for photos, proper nonce + capability checks
- Admin meta boxes: custom PHP with `add_meta_box()`, proper nonce verification, `sanitize_text_field()` / `floatval()` / `esc_url()` / `wp_kses_post()`
- Google Maps URL parsing: regex for `@lat,lng`, `?q=lat,lng` patterns — pure JS, no server-side API calls. Frontend validates the pasted URL is a Google Maps link, attempts coordinate extraction, and shows an error if it fails. Shortened URLs (`goo.gl`, `maps.app.goo.gl`) don't contain coordinates — instruct the user to open the link in a browser first and copy the expanded URL from the address bar (e.g. `.../@50.08,14.42,...`).
- Match file ownership/permissions to existing WP installation

---

## Activity Log

### 2026-02-18 — Initial implementation

**Built:** Full `miselinska-komise` WordPress plugin from scratch.

**Plugin structure:**
```
miselinska-komise/
├── miselinska-komise.php        # Main plugin, enqueues styles
├── includes/
│   ├── cpt.php                  # CPT: recenze (slug /recenze/)
│   ├── taxonomy.php             # Taxonomy: hashtag (non-hierarchical)
│   ├── meta-registration.php   # register_post_meta() for all 7 fields
│   ├── meta-boxes.php          # Admin meta boxes + JS GPS extraction
│   ├── ajax.php                # wp_ajax_mk_submit_review + mk_get_hashtags
│   └── templates.php           # Template loader (plugin fallback)
├── shortcodes/
│   ├── map.php                 # [miselinska_mapa height="N"]
│   ├── feed.php                # [miselinska_feed count orderby hashtag]
│   └── form.php                # [miselinska_formular]
├── templates/
│   ├── single-recenze.php      # Single review with Leaflet mini-map
│   └── archive-recenze.php     # Paginated archive + hashtag archive
└── assets/
    ├── css/miselinska.css      # Card grid, rating badges, form styles
    ├── js/map-shortcode.js     # Leaflet + MarkerCluster, color-coded pins
    └── js/form.js              # AJAX submit, GPS extraction, hashtag autocomplete
```

**Server setup:**
- Installed WP-CLI inside `miselinska_app` container
- Deployed plugin via zip+scp (Windows tar path issues workaround)
- Fixed file permissions (`www-data:www-data`, 755 dirs / 644 files)
- Installed GeneratePress theme
- Created pages: Domů (ID 7, front page), Mapa (ID 8), Nová recenze (ID 9)
- Set permalink structure `/%postname%/`, timezone `Europe/Prague`
- Created nav menu (Hlavní menu) with 4 items, assigned to `primary`

**Verified:**
- Plugin active ✓, CPT `recenze` registered ✓, taxonomy `hashtag` ✓
- REST API `/wp-json/wp/v2/recenze` returns `[]` (no posts yet) ✓
- Homepage renders `[miselinska_mapa]` + `[miselinska_feed]` shortcodes ✓
- Leaflet CSS/JS enqueued on map pages ✓, plugin CSS loads (200) ✓
- No PHP fatal errors in Docker logs ✓

**Known issues / next:**
- Czech diacritics in site name mangled through PowerShell SSH → fix in WP admin
- Need user to visually confirm layout and report any issues
