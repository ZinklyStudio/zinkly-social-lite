# Zinkly Social Lite 1.0 - Installation

Zinkly Social Lite runs on ordinary shared hosting: **no terminal, no Composer, no cron job, no Node.js**.

## Requirements
- PHP **8.1 or newer** with the extensions **PDO**, **pdo_mysql** (or **pdo_sqlite**), **GD**, **mbstring** and **fileinfo**. Almost every host has these.
- Recommended: **dom** (rich text posts), **zip** (uploading extensions) and **curl** (Stripe and PayPal payments). Without them, posts stay plain text and extensions are copied into `extensions/` by FTP.
- A database:
  - **MySQL 5.7+ / MariaDB 10.3+** (recommended);
  - or **SQLite**, which needs no setup and suits small communities and testing.
- Apache with `.htaccess` (most shared hosts), or nginx (see below), or LiteSpeed.

The installer checks all of this for you and shows anything missing in red.

## 1. Install on shared hosting (cPanel, Plesk, DirectAdmin, Hostinger …)
1. **Upload the files.** Download the latest release from GitHub (Releases › Source code zip), unzip it and upload the contents of the unzipped folder:
   - to your site's root (`public_html/`) for `https://your-site.com`;
   - or to a subfolder, for example `public_html/social/` for `https://your-site.com/social`.
2. **Create a database** (skip this for SQLite). In your hosting panel, open **MySQL Databases**, create a database and a user, and give the user **all privileges** on the database. Write down the database name, user and password.
3. **Open your site** in a browser. You are taken to the installer (`/install/`).
4. **Fill in the four steps:**
   - the server check;
   - the database;
   - your site name and language;
   - your admin account.

   Then press **Install now**.
5. **Done.** You are logged in as admin. The installer locks itself (`data/installed.lock`).

If the server check shows **data/ is not writable** or **uploads/ is not writable**, set those two folders to permission **755** (or **775**) in your file manager. Do the same for **extensions/** if you want to upload extensions from the admin panel.

## 2. Install on your computer (localhost)
Use any local PHP server:
- [XAMPP](https://www.apachefriends.org) on Windows, macOS or Linux;
- [MAMP](https://www.mamp.info) on macOS;
- [Laragon](https://laragon.org) on Windows.

**XAMPP example:**
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the `zinkly-lite` folder to `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (macOS).
3. **MySQL:** open `http://localhost/phpmyadmin`, click **New** and create a database called `zinkly_lite`. XAMPP's user is `root` with an empty password.
   - **SQLite:** skip this step.
4. Open **`http://localhost/zinkly-lite/`** and follow the installer.
   - Database host: `localhost`, port `3306`, user `root`, password empty.

**Without XAMPP**, if you only have PHP installed, run this from inside the `zinkly-lite` folder:
```
php -S localhost:8080 router.php
```
Then open `http://localhost:8080` and choose **SQLite** in the installer. `router.php` is only for this built-in server; Apache and nginx do not use it.

## 3. nginx
Use this in your `server { }` block (adjust the path and the PHP socket):
```nginx
root /var/www/zinkly-lite;
index index.php;
client_max_body_size 16m;

location ~ ^/(app|data)/ { deny all; }
location ~ /\. { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
location ~ ^/extensions/.*\.(php|phtml|phar|json|md|txt|ini|lock|log|sql|zip|sh|env)$ { deny all; }

location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
location ~* \.(css|js|svg|png|jpe?g|gif|webp)$ { expires 1y; add_header Cache-Control "public, immutable"; }
```

## After installing
- **Admin panel:** the **Admin** item in the menu:
  - **Members**: verify, change roles, suspend, delete.
  - **Posts**: every post with filters, bulk hide / show / delete, and the real author of anonymous posts.
  - **Reports**, **Groups**, **Pages**.
  - **Site pages**: About, Contact, FAQ and similar pages, written with the rich text editor.
  - **Extensions**: upload, switch on, settings and license keys. See `EXTENSIONS.md`.
  - **Site settings**: name, logo, landing page (welcome page / straight to log in / public feed), features on or off (sign-ups, stories, groups, pages, messages, comments, extensions, maintenance mode), rich text, anonymous posts, accent colour, animations, limits, email, Terms / Privacy.
- **Email:** password-reset emails use PHP `mail()`, which works on almost every shared host. If emails do not arrive, set **Send emails from** in Admin › Site settings to an address on your own domain.
- **Larger photos:** the upload limit is the smaller of Admin › Site settings and your host's PHP `upload_max_filesize`. On most hosts you can raise the PHP limit with **MultiPHP INI Editor** (cPanel).
- **HTTPS:** turn on your host's free SSL certificate (Let's Encrypt). Zinkly Social Lite then uses secure cookies automatically.

## Pro and payments
In **Admin › Pro & payments**:
1. Turn on **Pro**, and choose whether to show the **Go Pro** card and the **PRO** badge.
2. Add **plans**: name, price, currency (EUR, USD, GBP …), Pro time (a week, a month, 3 months, a year) and what is included.
3. Turn on one or more payment methods:
   - **Stripe**: your secret key from dashboard.stripe.com › Developers › API keys (`sk_test_…` to test, `sk_live_…` for real payments).
   - **PayPal**: client ID and secret from developer.paypal.com › Apps & Credentials, with **Sandbox** for tests and **Live** for real payments.
   - **Your own method** (bank transfer, cash …): write the instructions. Members send a request with a reference, and you click **Approve**.
4. Members pay on Stripe's or PayPal's own secure page; card details never reach your server. A payment is only marked paid after your server checks it with Stripe or PayPal. If a member closed the page early, use **Check again** in the payments list.

## Members and security
In **Admin › Members**, click a member to:
- change their username, name, email and bio, or give the verified badge;
- set a new password (and sign them out everywhere), or send a password reset email;
- see the **sign-in history** with IP address and device, failed attempts and other accounts on the same IP, and **sign them out on all devices**;
- see, edit, hide or delete their posts, and replace or remove their photos, videos and stories.

## Email (SMTP)
Zinkly sends password reset emails. Set it up in **Admin › Site settings › Email**:
- **SMTP** (recommended): emails go through a real mailbox or a mail service, so they arrive instead of landing in spam. Fill in the server, port, encryption, username and password from your host's or mail service's email settings. The buttons *Gmail, Outlook, Zoho, Brevo, Mailgun, SendGrid* fill in the server for you.
- **PHP mail()**: the server sends the email itself. It needs no setup, but emails often land in spam, and some hosts switch it off.
- **Off**: no emails (password reset will not work).

Click **Test connection** to check everything step by step without sending anything: the server name, the connection, the encryption, the login and the sender address. If a step fails, it tells you why and what to change. Then click **Send test email** to send a real email to yourself.

Common fixes:
- **Port 587 → STARTTLS, port 465 → SSL/TLS.** The test tells you when they are mixed up.
- **Gmail, Outlook, Yahoo, iCloud, Zoho** need an *app password* (made in your account's security settings, with 2-step verification on), not your normal password.
- **"Send emails from"** must be the mailbox you log in with, or an address your mail service has verified.
- **No answer from the server**: many shared hosts block outgoing mail ports. Use your host's own mail server (often `mail.your-site.com`), or ask your host to open the port.

The SMTP password is kept in your database and is never shown on the page again. Leave the field empty to keep it. When an email fails, the reason is shown in the Email settings and written to `data/error.log`.

## Backups
Everything Zinkly Social Lite stores is in your database (or the `data/zinkly-….sqlite` file), the `uploads/` folder and `data/config.php`.

The easy way: **Admin › Updates › Backups**. Choose what to include (database, program files, uploads), click **Make a backup now**, and download the `.zip` to keep it somewhere safe. Backups are kept in `data/backups/`, which cannot be opened from the web.

**Restore** puts the database and files back as they were in that backup. A fresh backup of the current state is made first, so a restore can always be undone. Your password is asked for both restores and updates. Your `data/config.php` (database password) is never overwritten by a restore.

A MySQL backup can only be restored on MySQL / MariaDB, and an SQLite backup only on SQLite.

## Updating
**From Admin (recommended):**
1. Download the new Zinkly Social Lite `.zip`.
2. Open **Admin › Updates** and upload it (drag it on the box or click *Choose file*).
3. Zinkly checks the package first: that it is really Zinkly Social Lite, that it is newer than yours, and which files it adds or changes. Nothing is changed yet. You also see what is new.
4. Type your password and click **Update now**. Zinkly backs up your database and files, copies the new files and upgrades the database. Visitors see a short maintenance page in the meantime; admins can keep browsing.
5. If anything goes wrong, the old files are put back automatically. You can also roll back later from the *Before an update* backup in the Backups tab.

Your `data/`, `uploads/` and `extensions/` folders are never touched by an update. Older versions are refused, so you cannot downgrade by mistake (use a backup for that).

If your host limits upload sizes, raise `upload_max_filesize` and `post_max_size` (the page shows the current limit), or update by hand.

**By hand (FTP):**
1. Make a backup first (see above).
2. Upload the new files over the old ones. **Do not** overwrite `data/` or `uploads/`.
3. Open your site. The first page view adds the new tables and columns by itself (no terminal, nothing to click). **Admin › Dashboard** then shows the new version.

## Reinstalling
Delete `data/config.php` and `data/installed.lock`, then empty the database (or delete the `.sqlite` file). The installer starts again.

## Security notes
- `data/` (settings and the SQLite file) and `app/` are blocked by `.htaccess`. On nginx, use the rules above.
- In `extensions/`, only css, js, images and fonts are served; PHP and settings files are blocked. Extensions run code on your server: only install extensions you trust.
- Rich text posts are cleaned on the server: only a short list of formatting tags is kept, and scripts, event handlers and unsafe links are removed.
- Uploaded files can never run as code: images are checked, re-drawn and saved as JPEG / PNG / WebP, and `uploads/.htaccess` blocks scripts.
- Passwords are hashed with PHP's `password_hash`.
- Every form has a security token.
- Sign-in, sign-up, password reset, posting, messaging and searching are rate-limited.
