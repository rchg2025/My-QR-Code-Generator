# Copilot Instructions for `my-qr-generator`

Purpose: Help AI coding agents work productively in this WordPress plugin. Keep advice specific to this repo. Prefer minimal changes, align with existing patterns.

## Big Picture
- Plugin: QR code + shortlink generator for arbitrary URLs with dynamic scheduling, statistics, and server-side PNG generation.
- Entry: `my-qr-generator.php` defines class `My_QR_Generator` and hooks.
- Dependencies: Requires `phpqrcode/phpqrcode` via Composer for server-side QR PNG generation (see `composer.json`).
- Output surfaces:
  - Admin pages: QR Code Generator (main), QR Links (overview), Manage Dynamic (schedule editor), Stats (charts), Settings (logo upload).
  - Shortcodes: `[my_qr_generator]` (static generator), `[my_dynamic_qr id="POST_ID" mode="short|effective"]` (dynamic QR with countdown).
- Data model: Custom Post Type `shortlink` storing `_original_url`, `_mqrg_dynamic_rules`, `_mqrg_scan_count`, `_mqrg_last_scan`, `_mqrg_daily_counts` meta. Post slug is the short code. Pretty URLs live under `/url/{slug}`.
- Redirects: `template_redirect` resolves the `url` query var or singular `shortlink`, applies dynamic rules via `get_effective_destination()`, increments scan count, and 301s to target URL.
- Server-side PNG: Endpoint `/?mqrg_qr_png=POST_ID&color=HEX` generates QR PNG with optional color and logo watermark, cached via transients (1 hour TTL).

## Assets & Integration Points
- Frontend JS/CSS: `assets/script.js` (static generator), `assets/dynamic.js` (dynamic QR with countdown), `assets/style.css`.
- External libs: QRCode.js v1.0.0 (CDN) for client-side QR, Chart.js v4.4.0 (CDN) for statistics charts.
- Server-side QR: phpqrcode library (via Composer) generates PNG output at `/?mqrg_qr_png=POST_ID&color=HEX`.
- Script localization: `mqrg_data` provides `ajax_url` and `nonce` to JS.
- Enqueue logic: `enqueue_generator_assets()` runs for the admin page and only on frontend posts containing the shortcode. `enqueue_dynamic_assets()` handles dynamic QR shortcode.

## Composer Dependency Management
- File: `composer.json` at plugin root declares `phpqrcode/phpqrcode: ^1.1` dependency.
- Installation: Run `composer install` in plugin directory to install phpqrcode to `vendor/` folder.
- Autoload: `handle_qr_png_endpoint()` checks for `QRcode` class and attempts to load from `vendor/phpqrcode/qrlib.php`.
- Fallback: If library not found, endpoint returns HTTP 500 with message "QR library not available. Run: composer install".
- Note: Plugin will function without Composer (client-side QR still works), but PNG endpoint requires phpqrcode for server-side generation.

## Watermark/Logo Feature
- Settings page: Admin → QR Code Generator → Settings (`mqrg-settings`) provides WordPress media library integration for logo upload.
- Option: `mqrg_logo_attachment_id` stores attachment ID of logo image (PNG/JPEG/GIF/WebP supported).
- Overlay logic: `overlay_logo_on_qr($qr_png_data, $logo_attachment_id)` centers logo on QR code:
  - Logo resized to 20% of QR width (maintains aspect ratio).
  - White background square (10% larger than logo) drawn beneath logo for contrast.
  - Uses GD image functions (`imagecopyresampled`, `imagefilledrectangle`).
- Cache invalidation: Changing logo triggers transient cache purge (all `_transient_mqrg_qr_*` options deleted).
- UI: JavaScript uses `wp.media()` frame for logo selection, preview thumbnail displayed on settings page.

## PNG Endpoint & Caching
- URL pattern: `/?mqrg_qr_png=POST_ID&color=HEX` (color optional, default `000000`).
- Cache strategy: WordPress transients with key `mqrg_qr_{post_id}_{color_hex}_{logo_hash}`, TTL 1 hour (`HOUR_IN_SECONDS`).
- Cache headers: `X-Cache: HIT` or `X-Cache: MISS` indicates cache status, `Cache-Control: public, max-age=3600` for browser caching.
- Logo hash: Computed as `md5($logo_id . get_post_modified_time('U', true, $logo_id))` to bust cache when logo changes.
- Color recoloring: `recolor_qr_png($png_data, $hex)` iterates pixels with GD, replaces black pixels (RGB < 50) with target color.
- Performance: Transient cache eliminates redundant QR generation for repeat requests, logo overlay only computed on cache miss.

## AJAX Contract (Shortlink Creation)
- Action: `mqrg_generate_link` (both auth and nopriv handlers).
- Endpoint: `POST` to `mqrg_data.ajax_url` with fields: `action=mqrg_generate_link`, `nonce=mqrg_data.nonce`, `long_url`.
- Validation: PHP uses `check_ajax_referer('mqrg_nonce','nonce')` and `FILTER_VALIDATE_URL`.
- Behavior: Reuses existing `shortlink` if `_original_url` already exists; else creates a new post with 6-char slug via `wp_generate_password(6, false)`.
- Response: `{ success: true, data: { short_url } }` or `{ success: false, data: { message } }`.

## URL Routing Notes
- CPT is registered with `query_var: 'url'` and `rewrite: { slug: 'url', with_front: false }`.
- `shortlink_redirect()` supports both:
  - Non-pretty: `?url={slug}`
  - Pretty permalinks: `/url/{slug}` or the singular `shortlink` permalink.
- Rewrite rules are flushed once on first successful generation (option `mqrg_flushed`). If redirects fail, manually flush permalinks or delete that option.

## UI/UX Patterns (script.js)
- Component scope: `initializeQrGenerator()` binds events per `.mqrg-wrap` instance—safe for multiple embeds.
- Generate flow: Reads URL, calls AJAX, sets `currentQrText` to returned `short_url`, renders QR via QRCode.js.
- Color palette: Updates `currentQrColor` and re-renders existing QR on selection.
- Copy/download: Copies short URL via `document.execCommand('copy')`; downloads PNG from canvas, adding a white border.

## Styling Conventions (style.css)
- Uses CSS variables under `:root` for colors and shadows.
- Card-like layout with `.mqrg-container`; form left, result right, responsive via flex-wrap.
- Keep additions consistent with variables: `--mqrg-*` and button styles.

## Extensibility Guidelines
- Keep all WP hooks and rendering within `My_QR_Generator` methods. Prefer adding new actions/shortcodes as new methods and hook them in `__construct()`.
- Enqueue new assets through `enqueue_generator_assets()` or mirror its pattern to ensure nonce and dependencies are present.
- Preserve the AJAX contract and nonce naming (`mqrg_nonce`). If creating new endpoints, mirror `handle_ajax_generate_link()` for validation and JSON responses.
- For new UI controls, wire them per-instance inside `initializeQrGenerator()` to avoid cross-instance leakage.

## Developer Workflow
- No build step: assets are plain JS/CSS. Work directly in `assets/` and `my-qr-generator.php`.
- Test locally by enabling the plugin in WP Admin, visit the admin page or place `[my_qr_generator]` in a post.
- If shortlinks do not redirect, visit Settings → Permalinks (save) to flush, or delete `mqrg_flushed` option.

## File Map (Key References)
- `my-qr-generator.php`: Hooks, CPT, AJAX, queueing, HTML renderer, PNG endpoint, statistics tracking, admin pages (Generator, Links, Manage Dynamic, Stats, Settings).
- `assets/script.js`: Event handlers for static QR generator, AJAX fetch, QR render, copy/download.
- `assets/dynamic.js`: Dynamic QR rendering with countdown timer and auto-refresh.
- `assets/style.css`: Visual styles and CSS variables.
- `composer.json`: Composer manifest declaring phpqrcode dependency for server-side PNG generation.
- `.github/copilot-instructions.md`: This file—AI agent guidance for plugin architecture and conventions.

## Examples
- Shortcode usage: `[my_qr_generator]`
- Expected short URL: `/url/ABC123` (pretty) or `?url=ABC123` (fallback)
- JS fetch shape:
  ```js
  const form = new FormData();
  form.append('action','mqrg_generate_link');
  form.append('nonce', mqrg_data.nonce);
  form.append('long_url', 'https://example.com');
  fetch(mqrg_data.ajax_url, { method:'POST', body: form });
  ```
