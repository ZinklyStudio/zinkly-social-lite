# Zinkly Social Lite - Changelog

## 1.0.0 - 2026-10-30

First release.

### Social
- Accounts with sign-up, log in, "keep me logged in" and password reset by email.
- Profiles with photo, cover, bio, location and website; change your photo and cover right on the page.
- Feed with text and photos, who can see each post, #hashtags, @mentions and links, likes, comments with replies, shares, saved posts, edit and delete.
- **Rich text posts**: select text for a small toolbar (bold, italic, underline, strikethrough, list, spoiler, link); start a line with `## `, `> `, `- ` or `1. ` for headings, quotes and lists.
- **Spoilers**: hidden text that readers tap to show.
- **Anonymous posts**: members post without their name; admins, moderators and group admins see the real author.
- Explore, trending hashtags and people to follow.
- Follows, private accounts and follow requests.
- **Stories** with a live preview editor: upload a photo, take a photo, record a video or pick a photo from your posts; text colour, 4 fonts, caption position and gradient backgrounds. Video stories (MP4, WebM, MOV) play for their full length.
- One-to-one messages with photos and "Seen"; a new chat start screen with people you can message.
- **Go Pro**: members buy Pro with Stripe, PayPal or a custom method and get a PRO badge and bigger uploads.
- **Groups**: public, private or hidden, group photo and cover, categories, rules, moderators, "only admins can post", post approval, anonymous posts, pinned post, Members / About / Review tabs.
- **Pages** for brands, artists, places and causes: follow, post as the page, several admins, verified badge.
- Notifications with a live badge, search, blocking and reports.
- Emoji picker, character counter and saved drafts in the post box.

### Admin
- **Members**: edit username, name, email and bio; set a new password or send a password reset email; sign-in history with IP address and device, failed attempts and other accounts on the same IP; **sign out on all devices**; see, edit, hide or delete a member's posts, and replace or remove their photos, videos and stories.
- **Post editor**: edit any post's text and visibility, replace, remove or add photos.
- **Pro & payments**: plans, Stripe Checkout, PayPal Checkout, a custom method you approve, payments list and Pro members.
- **Hashtags**: trending card on / off, how many, time window, minimum posts, featured hashtags with an end date, hidden hashtags.
- Dashboard with 14-day charts; members, roles and suspensions.
- **Posts**: every post with filters, bulk hide / show / delete, and the real author of anonymous posts.
- Reports, groups and pages.
- **Email**: send by SMTP (presets for Gmail, Outlook, Zoho, Brevo, Mailgun, SendGrid) or PHP mail(); **Test connection** checks the server, port, encryption, login and sender step by step and explains what to change; **Send test email**.
- **Site pages**: About, Contact, FAQ … written with the rich text editor, linked in the footer.
- **Site settings**: landing page mode (welcome page, log in, public feed), maintenance mode, accent colours, theme for visitors, animations, features on / off, upload limits, longest post.
- **Extensions**: upload `.zip` extensions with settings and license keys (ready for EchoAI). See `EXTENSIONS.md`.
- **Updates & backups**: install a new version from its `.zip` (with an automatic backup first), read the changelog, make, download and restore backups.

### Everything else
- Web installer with **Test connection**: checks the database (rights, emoji support, free table prefix) before installing, explains problems in plain words, and removes everything again if an install fails.
- MySQL / MariaDB or SQLite; runs on ordinary shared hosting.
- 8 languages, dark and light themes, full-width layout on every screen size.
- Animations: posts fade in, likes pop, smooth menus and dialogs, numbers count up, a loading bar between pages.
