<div align="center">

<img src="assets/img/icon.svg" width="72" alt="Zinkly Social Lite">

# Zinkly Social Lite

**A fast, modern social network you can host yourself, on ordinary shared hosting.**<br>
Plain PHP · No framework · No Composer · No terminal · Free and open source (MIT)

[**Live demo**](https://lite.zinkly.org/) · [Install](#-install-in-5-minutes) · [Features](#-features) · [Updates](#-updates) · [Zinkly Full](#-want-more-get-zinkly-full)

![Version](https://img.shields.io/badge/version-1.0.0-7c5cff) ![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4) ![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB%20%2F%20SQLite-supported-0891b2) ![License](https://img.shields.io/badge/license-MIT-22c55e)

<img src="docs/screenshots/home-dark.png" alt="Zinkly Social Lite home feed" width="100%">

</div>

---

## ✨ Why Zinkly Social Lite?

- **Runs anywhere.** Upload the files, open your domain, follow the 4-step web installer. Works on cPanel, Plesk, DirectAdmin, Hostinger, HestiaCP, any VPS and even `php -S` on your laptop.
- **Light and fast.** About 90 small PHP files, **one** stylesheet and **one** JavaScript file, no libraries, no build step. Pages are rendered on the server, photos are resized on upload and feeds load a page at a time.
- **Looks great.** The same polished look as the full Zinkly: dark, light or system theme, accent colours, smooth animations, works on phones, tablets and desktop.
- **Yours to change.** MIT licensed. Change the code, the design, the texts, add features, brand it as your own.
- **Free updates.** New versions are published here. Install them in one click from **Admin › Updates**, with an automatic backup first.

## 🎬 Live demo

**👉 [lite.zinkly.org](https://lite.zinkly.org/)** - create an account and try everything: posts, stories, chat, groups, pages and Pro.

## 📸 Screenshots

| Home feed (dark) | Home feed (light) |
|---|---|
| ![Home dark](docs/screenshots/home-dark.png) | ![Home light](docs/screenshots/home-light.png) |

| Story editor with live preview | Story viewer |
|---|---|
| ![Story editor](docs/screenshots/story-editor.png) | ![Story viewer](docs/screenshots/story-viewer.png) |

| Text stories: colours, fonts, backgrounds | Profile with PRO badge |
|---|---|
| ![Text story](docs/screenshots/story-editor-text.png) | ![Profile](docs/screenshots/profile.png) |

| Messages | Go Pro (Stripe, PayPal, bank transfer) |
|---|---|
| ![Messages](docs/screenshots/messages.png) | ![Go Pro](docs/screenshots/go-pro.png) |

| Groups | Landing page |
|---|---|
| ![Group](docs/screenshots/group.png) | ![Landing](docs/screenshots/landing.png) |

<details>
<summary><b>Admin panel screenshots</b></summary>

| Dashboard | Pro & payments |
|---|---|
| ![Admin dashboard](docs/screenshots/admin-dashboard.png) | ![Admin Pro](docs/screenshots/admin-pro.png) |

| Member security: IP addresses and devices | Member account and password |
|---|---|
| ![Member security](docs/screenshots/admin-member-security.png) | ![Member account](docs/screenshots/admin-member-account.png) |

| Trending hashtags | Site settings |
|---|---|
| ![Hashtags](docs/screenshots/admin-hashtags.png) | ![Settings](docs/screenshots/admin-settings.png) |

</details>

<details>
<summary><b>Mobile and installer screenshots</b></summary>

| Mobile home | Mobile story editor | Mobile Go Pro |
|---|---|---|
| ![Mobile home](docs/screenshots/mobile-home.png) | ![Mobile story](docs/screenshots/mobile-story.png) | ![Mobile Pro](docs/screenshots/mobile-pro.png) |

| Web installer | Installed |
|---|---|
| ![Installer](docs/screenshots/installer.png) | ![Installed](docs/screenshots/installer-done.png) |

</details>

<sub>All names, people and photos in the screenshots are demo content.</sub>

## 🚀 Features

### For your members
- **Accounts** - sign up, log in, "keep me logged in", password reset by email, verified badges
- **Profiles** - photo, cover, bio, location and website; change photo and cover right on the page
- **Feed** - text and up to 10 photos, rich text (bold, italic, lists, quotes, headings, spoilers, links), #hashtags, @mentions, likes, comments with replies, shares, saved posts, edit and delete
- **Privacy** - Public, Followers or Only me for each post; private accounts with follow requests; block and report; anonymous posts
- **Stories** - photo, video or text stories that disappear after 24 hours
  - **Upload, take a photo, record a video** or **pick a photo from your posts**
  - **Live preview** while you edit: text colour, 4 fonts, caption position, gradient backgrounds
  - Full-screen viewer with view counts
- **Messages** - one-to-one chats with photos, unread counts, "Seen", new messages arrive by themselves
- **Groups** - public, private or hidden; rules, moderators, post approval, pinned post, anonymous posts
- **Pages** - for brands, artists, places and causes, with several admins and a verified badge
- **Explore & search** - trending hashtags, popular posts, people to follow, live search
- **Notifications** - follows, likes, comments, replies, mentions, shares, group activity, with a live badge
- **Go Pro** - members can buy Pro and get a **PRO badge** and bigger uploads
- **8 languages** - English, Ελληνικά, Deutsch, Français, Português (Brasil), Español (México), Русский, Italiano

### For you (Admin panel)
- **Dashboard** with 14-day charts
- **Members**
  - edit username, name, email and bio; give the verified badge; roles (member / moderator / admin); suspend or delete
  - **set a new password** or **send a password reset email**
  - **security**: sign-in history with **IP address and device**, failed attempts, other accounts on the same IP, **sign out on all devices**
  - **content**: see every post, photo, video and story of a member; **edit, hide, delete**, **replace or remove photos and videos**
- **Posts** - every post with filters and bulk hide / show / delete, plus a full post editor
- **Pro & payments** - turn Pro on or off, create plans (price, currency, length, perks), accept **Stripe**, **PayPal** and your **own method** (bank transfer, cash …) that you approve; payments list and Pro members
- **Trending hashtags** - on / off, how many to show, time window (hours / days / weeks), minimum posts, **featured hashtags** that disappear by themselves, hidden hashtags
- **Reports, Groups, Pages, Site pages** (About, Contact, FAQ …)
- **Site settings** - name, logo, landing page mode, maintenance mode, accent colour, animations, feature switches, upload limits, video stories, email (SMTP with a step-by-step connection test), Terms and Privacy
- **Extensions** - upload `.zip` extensions with hooks, settings and licence keys (see [EXTENSIONS.md](EXTENSIONS.md))
- **Updates & backups** - one-click updates from a `.zip` with automatic backup and undo; make, download and restore backups

### Security built in
Prepared SQL statements everywhere · every output escaped · CSRF token on every form · `password_hash` · secure, httpOnly, SameSite cookies · strict Content-Security-Policy (no inline scripts) · rate limits on sign-in, sign-up, posting, messages and more · uploaded images are checked and re-drawn · uploads can never run as code · `app/` and `data/` blocked from the web · the installer locks itself · sign-in history and "sign out everywhere" · payments are only marked paid after the server checks them with Stripe or PayPal.

## 🧰 Requirements

| | Minimum |
|---|---|
| **PHP** | 8.1 or newer with **PDO**, **pdo_mysql** (or **pdo_sqlite**), **GD**, **mbstring**, **fileinfo** |
| **Recommended PHP** | **dom** (rich text), **zip** (extensions), **curl** (Stripe and PayPal) |
| **Database** | **MySQL 5.7+** / **MariaDB 10.3+** (recommended) or **SQLite** (no setup) |
| **Web server** | Apache or LiteSpeed with `.htaccess`, or nginx (rules in [INSTALL.md](INSTALL.md)) |
| **Hosting** | Any shared hosting or VPS. No SSH, cron, Composer or Node.js needed |

The installer checks everything for you and shows anything missing in red.

## 📦 Install in 5 minutes

1. **Download** the latest release: [**Releases**](../../releases) › `Source code (zip)`, or use **Code › Download ZIP**.
2. **Upload** the files to your site's root (`public_html/`) or to a subfolder such as `public_html/social/`.
3. **Create a database** in your hosting panel (MySQL Databases) and give the user all privileges. Skip this for SQLite.
4. **Open your site** in a browser. You are taken to the installer (`/install/`).
5. **Fill in the 4 steps** - server check, database (with a **Test connection** button), site name and language, your admin account - and press **Install now**.

Done. You are logged in as admin and the installer locks itself.

> If the server check says **data/** or **uploads/** is not writable, set those folders to **755** (or **775**) in your file manager. Do the same for **extensions/** to upload extensions from the admin panel.

**On your computer:** run `php -S localhost:8080 router.php` in the folder, open `http://localhost:8080` and choose **SQLite**. XAMPP, MAMP and Laragon work too.

**With git:**
```bash
git clone https://github.com/ZinklyStudio/zinkly-social-lite.git
```

Full guide (shared hosting, localhost, nginx, email, backups): **[INSTALL.md](INSTALL.md)**

## 💳 Set up Pro and payments

1. **Admin › Pro & payments** › turn on **Pro**.
2. Add one or more **plans** (for example *Pro Monthly €4.99 / month* and *Pro Yearly €39.99 / year*).
3. Turn on the payment methods you want:
   - **Stripe** - paste your secret key (dashboard.stripe.com › Developers › API keys). Members pay on Stripe's secure checkout page.
   - **PayPal** - paste your client ID and secret (developer.paypal.com › Apps & Credentials). Use **Sandbox** to test, then **Live**.
   - **Your own method** - for example *Bank transfer*, with your instructions. Members send a request and you **Approve** it.
4. The **Go Pro** card appears in the right column. Pro is paid once per period, and buying again adds time.

## 🔄 Updates

Zinkly Social Lite **receives free updates** here on GitHub. ⭐ **Star** and 👁 **Watch › Custom › Releases** to hear about new versions.

**One-click update (recommended):**
1. Download the new version's `.zip` from [Releases](../../releases).
2. Open **Admin › Updates**, upload the `.zip` and check the preview of what changes.
3. Type your password and click **Update now**. Your database and files are backed up first, and everything is put back automatically if anything fails.

**By FTP:** make a backup, upload the new files over the old ones, but **do not overwrite `data/`, `uploads/` or `extensions/`**. The first page view upgrades the database by itself.

Your `data/`, `uploads/` and `extensions/` folders are never touched by an update.

## 🎨 Make it yours

Everything is plain PHP, HTML, CSS and JavaScript, so you can change anything:

| What | Where |
|---|---|
| Page templates | `app/views/` |
| Look and colours | `assets/css/zl.css` (CSS variables at the top) |
| Behaviour | `assets/js/zl.js` |
| Texts and languages | `app/lang/<code>.php` (add a language by copying `en.php`) |
| Pages and actions | `app/routes.php`, `app/routes-admin.php` |
| Features | `app/modules/` (Users, Posts, Stories, Groups, Pages, Pro, Security …) |
| Add-ons without touching the core | `extensions/` - see [EXTENSIONS.md](EXTENSIONS.md) and `extensions-sample/` |

Tip: keep your own changes in an **extension** where you can, so updates never overwrite them.

## 📁 Files

```
index.php            every page goes through here
install/             web installer (locks itself after install)
app/core/            database, router, sign-in, uploads, translations, mail, updates, backups
app/modules/         users, posts, stories, groups, pages, messages, notifications, Pro, security
app/views/           page templates (plain PHP)
app/lang/            8 languages
assets/              one stylesheet, one script, icon
uploads/             photos and videos (scripts blocked)
extensions/          installed extensions
extensions-sample/   an example extension to learn from
data/                config, SQLite file, backups (all blocked from the web)
```

## 💎 Want more? Get Zinkly Full

Zinkly Social Lite is the free, light edition. When your community grows, upgrade to **[Zinkly - Laravel Social Network with Reels, Live, Marketplace & Communities](https://shop.zinkly.org/item/zinkly-laravel-social-network-with-reels-live-marketplace-communities)**:

| | **Lite** (free) | **Zinkly Full** |
|---|:---:|:---:|
| Feed, photos, comments, shares, hashtags | ✅ | ✅ |
| Stories | ✅ photo, video, text | ✅ Story Studio with stickers and music |
| Messages | ✅ one-to-one | ✅ real-time, group chats, voice notes |
| Groups and Pages | ✅ | ✅ Communities with files and events |
| Pro membership (Stripe, PayPal, bank transfer) | ✅ | ✅ VIP, creator memberships, payouts |
| **Reels** and long videos with playlists | - | ✅ |
| **Live streaming** with chat and donations | - | ✅ |
| **Voice and video calls**, screen sharing | - | ✅ |
| **Marketplace**, services, jobs, courses | - | ✅ |
| **Wallet**, events with tickets, boosted posts | - | ✅ |
| Google / Facebook / Discord sign-in, 2FA | - | ✅ |
| REST API + Android and iOS apps (sold separately) | - | ✅ |
| Built with | Plain PHP | Laravel, real-time with Reverb |

**One-time payment · 6 months of support · free updates forever.**

### 👉 [Get Zinkly Full on Zinkly Shop](https://shop.zinkly.org/item/zinkly-laravel-social-network-with-reels-live-marketplace-communities)

## 🤝 Support and contributing

- Found a bug or have an idea? Open an [issue](../../issues).
- Pull requests are welcome: keep it light (no frameworks or build steps) and escape every output.
- Need help, custom work or the full edition? Visit **[Zinkly Shop](https://shop.zinkly.org/)**.

## 📄 License

Zinkly Social Lite is free and open source under the **[MIT License](LICENSE)**. Use it for personal or commercial sites, change it as much as you like.

<div align="center">
<sub>Made with ❤️ by <a href="https://shop.zinkly.org/">Zinkly Studio</a></sub>
</div>
