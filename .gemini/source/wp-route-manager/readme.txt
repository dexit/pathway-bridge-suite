=== WP Route Manager ===
Contributors: pathwaygroup
Tags: rest api, webhook, endpoint, php snippets, automation
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom REST API endpoint manager, webhook ingestion, PHP snippet runner and action dispatcher for WordPress.

== Description ==

**WP Route Manager** lets you create and manage custom WordPress REST API endpoints from the admin — without writing boilerplate. Incoming HTTP requests can trigger WordPress hooks, run PHP code snippets, or be forwarded to external URLs.

= Core Features =

* **Custom Endpoints** — create REST routes for any HTTP method with a few clicks
* **4 Action Types** — fire a WP action hook, apply a WP filter (and return the result), forward to an external URL, or run a PHP snippet
* **PHP Snippets** — write, version (via WP revisions), enable/disable, and debug PHP code from the admin. Full output capture: `echo`, `error_log`, `print_r` and PHP errors all appear in the log.
* **Body Parsing** — auto-detect (Content-Type sniff), force JSON/form/raw, or use a custom PHP snippet to parse exotic formats (XML, CSV, custom protocols)
* **API Key Authentication** — hashed key storage (`wrmk_xxxx` prefix), per-key endpoint restrictions, last-used tracking
* **Request/Response Logs** — every request logged: headers, params, raw body, parsed body, response, duration, snippet output and PHP errors. Configurable retention + CRON purge.
* **Secure Custom Fields** — all admin forms powered by SCF/ACF field groups registered in PHP — full validation and conditional logic built-in
* **WP Abilities API** — exposes plugin state and feature flags via `wp-abilities/v1` REST endpoint (WP 6.9+)

= WP-CLI Commands =

All management available from the terminal:

`wp wprm endpoint list|create|get|toggle|delete`
`wp wprm key list|create|revoke|toggle`
`wp wprm log list|get|tail|clear`
`wp wprm test <slug> --method=POST --body='...' --key=...`
`wp wprm scaffold handler <hook> [--type=filter]`
`wp wprm scaffold snippet "My Snippet" --type=filter_handler`
`wp wprm scaffold extension my-plugin`

= Extension Hooks =

WP Route Manager is designed to be extended:

`do_action( 'wprm_loaded', $plugin )`
`add_filter( 'wprm_parsed_data', $data, $request, $endpoint_id )`
`add_filter( 'wprm_action_handler', $handler, $type, $endpoint_id )`
`add_action( 'wprm_before_request', $request, $endpoint_id )`
`add_action( 'wprm_after_request', $response, $request, $endpoint_id )`
`add_action( 'wprm_snippet_executed', $snippet_id, $endpoint_id, $result, $output )`
`add_filter( 'wprm_forward_data', $data, $endpoint_id, $request )`
`add_action( 'wprm_register_abilities' )` — register your own Abilities API entries

Generate a fully-annotated extension plugin stub: `wp wprm scaffold extension my-extension`

= PHP Snippets =

PHP snippet execution is disabled by default. To enable, add to `wp-config.php`:

`define( 'WPRM_ALLOW_PHP_SNIPPETS', true );`

This is a deliberate security measure — PHP execution requires explicit opt-in plus `manage_options` capability.

Available variables inside snippets: `$data`, `$request`, `$endpoint`, `$wpdb`, `$wp_query`

= Demo Content =

On first activation, 5 demo endpoints, 4 snippets and a demo API key are created so you can test everything immediately:

* `GET /wp-json/wprm/v1/demo/ping` — public endpoint, no key needed
* `POST /wp-json/wprm/v1/demo/action` — fires a WP action hook
* `POST /wp-json/wprm/v1/demo/filter` — apply_filters → returns JSON
* `POST /wp-json/wprm/v1/demo/snippet` — runs PHP snippet (requires opt-in)
* `POST /wp-json/wprm/v1/demo/forward` — forwards to httpbin.org/post

== Requirements ==

* WordPress 6.4+
* PHP 8.1+
* [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) (free) or ACF (for admin forms)

== Installation ==

1. Upload the `wp-route-manager` folder to `/wp-content/plugins/`
2. Install and activate **Secure Custom Fields** (free, from wordpress.org)
3. Activate **WP Route Manager**
4. Visit **Route Manager** in the WordPress admin sidebar
5. Copy the demo API key shown on the dashboard (it appears once)

== Frequently Asked Questions ==

= Why does it require Secure Custom Fields? =

SCF handles all admin form rendering, validation, conditional logic, and sanitization for endpoint and snippet configuration. This avoids hundreds of lines of hand-written form HTML and gives you a consistent, professional editing experience.

= How are API keys stored? =

Keys are stored as `wp_hash()` of the plain key — the same approach WordPress uses for passwords. The plain key is shown exactly once on creation. If you lose it, create a new one.

= Is PHP snippet execution safe? =

PHP execution is disabled by default and requires both `define('WPRM_ALLOW_PHP_SNIPPETS', true)` in `wp-config.php` AND `manage_options` capability to create/edit snippets. Only give trusted administrators access to snippet editing.

= Can I forward webhooks to multiple external URLs? =

Each endpoint forwards to one URL. For fan-out, use a `wp_action` endpoint and attach multiple `add_action()` handlers that each make their own outbound requests.

= How do I extend WP Route Manager from another plugin? =

Run `wp wprm scaffold extension my-plugin` to generate a fully-annotated extension plugin stub demonstrating all available hooks and filters.

== Changelog ==

= 1.0.0 =
* Initial release
* Custom REST endpoint CPT with 4 action handler types
* PHP Snippet CPT with CodeMirror editor, revisions, on/off toggle
* Full request/response logging with detail modal
* API key management with hashed storage
* WP-CLI: endpoint, key, log, test, scaffold commands
* WP Abilities API integration
* Demo content loaded on first activation
* SCF/ACF field groups registered in PHP
