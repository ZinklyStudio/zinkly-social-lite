# Zinkly Social Lite - Extensions

Extensions add features without changing Zinkly Social Lite's own files, so they survive updates.
Admins install them in **Admin > Extensions**: upload the `.zip`, switch it on, fill in its settings.

A complete example is in `extensions-sample/hello-banner/` (and `hello-banner.zip`, ready to upload).

## What is in an extension

```
my-extension/
  extension.json   the manifest (required)
  extension.php    the code that registers hooks (required, name set by "main")
  assets/          css, js, images, fonts (optional; served to browsers)
```

The `.zip` may contain these files at the top or inside one folder.
Files starting with a dot (`.htaccess`, `.git`) are never installed.
PHP, JSON and other server files in `extensions/` are blocked from the web; only css, js, images, fonts and media are served.

## extension.json

```json
{
    "id": "my-extension",
    "name": "My Extension",
    "version": "1.0.0",
    "description": "One sentence about what it does.",
    "author": "You",
    "url": "https://example.com",
    "main": "extension.php",
    "requires": "1.0.0",
    "license": { "required": true, "label": "Purchase code", "url": "https://example.com/buy" },
    "settings": [
        { "key": "api_key", "label": "API key", "type": "password", "hint": "From your account page" },
        { "key": "mode", "label": "Mode", "type": "select", "options": { "fast": "Fast", "best": "Best quality" }, "default": "fast" },
        { "key": "enabled_for_guests", "label": "Visitors too", "type": "toggle", "default": "0" }
    ]
}
```

- `id`: 2-40 characters, lowercase letters, numbers, `-` and `_`. Uploading a zip with the same id updates the extension and keeps its settings.
- `license`: `true`, or an object as above. A licensed extension cannot be switched on until the admin enters a key. Zinkly Social Lite only stores the key; your extension checks it with your server (`Ext::license('my-extension')`).
- `settings` types: `text`, `password` (never shown again after saving), `textarea`, `toggle`, `select`, `number`, `url`, `email`. Zinkly Social Lite draws the form for you.

## Hooks

Register hooks in your main file with `Ext::on($hook, $function, $priority = 10)`.
A hook that returns HTML must escape everything with `e()`.

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `head` | HTML | | `<link>` / `<script src>` tags in every page head |
| `body_end` | HTML | `$user` or null | Dialogs, script tags before `</body>` |
| `aside` | HTML | `$user` or null | Cards in the right column |
| `landing` | HTML | | Sections at the end of the welcome page |
| `composer_tools` | HTML | `$groupId`, `$pageId` | Buttons in the post box |
| `post_after_body` | HTML | `$post` | Under the text and photos of each post |
| `post_menu` | HTML | `$post` | Items in the post "..." menu |
| `nav_links` | filter | `$links`, `$user` | Add `[key, path, icon, label, count]` to the main menu |
| `admin_nav` | filter | `$items` | Add `'key' => [path, icon, label]` to the admin menu |
| `csp` | filter | `$csp` | Allow an outside service, e.g. `$csp['connect-src'][] = 'https://api.example.com'` |
| `post_created` | action | `$postId`, `$userId` | |
| `post_deleted` | action | `$postId`, `$post` | |
| `post_status_changed` | action | `$postId`, `$status`, `$byUserId` | hidden / published |
| `comment_created` | action | `$commentId`, `$postId`, `$userId` | |
| `extension_enabled` / `extension_disabled` | action | `$id` | |
| `extension_settings_saved` | action | `$id`, `$settings` | |

Pages of your own: call `Router::get()` / `Router::post()` in your main file (POST routes get the CSRF check automatically).
Options: `['auth' => true]` for members, `['auth' => true, 'admin' => true]` for admins.

## Helpers

```php
Ext::setting('my-extension', 'api_key');          // a saved setting
Ext::settings('my-extension');                    // all of them
Ext::license('my-extension');                     // the key the admin entered, or null
Ext::asset('my-extension', 'assets/app.js');      // URL of a file in your folder
Ext::active('echoai');                            // is another extension switched on?
Auth::user(); Auth::isAdmin();                    // who is looking
DB::all('SELECT * FROM {posts} WHERE user_id = ?', [$id]);   // database ({table} adds the prefix)
setting('site_name'); t('nav.home'); e($text); url('path'); View::render(...)
```

Browser scripts must be files (`Ext::asset`), not inline `<script>`: the site uses a strict Content-Security-Policy.

## Safety

- If your main file throws an error while loading, the extension is switched off automatically and the error is written to `data/error.log`; the site keeps working.
- Extensions run with the same rights as Zinkly Social Lite. Admins should only install extensions they trust.

## EchoAI and other paid extensions

Admin > Extensions shows **EchoAI** as a featured slot. After buying it, the admin uploads the `echoai.zip` they received, enters the license key in its settings and switches it on. EchoAI must be packaged with an `extension.json` as described here; a package built for the full (Laravel) Zinkly will not run in Lite.
