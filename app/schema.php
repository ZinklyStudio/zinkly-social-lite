<?php
/**
 * Zinkly Social Lite - database schema. One definition for MySQL / MariaDB and SQLite.
 * Placeholders: {pk} primary key, {str} short text (191), {txt} long text, {int} integer, {big} big integer.
 */
return [
    'tables' => [
        'settings' => '`skey` VARCHAR(64) NOT NULL PRIMARY KEY, `svalue` {txt}',

        'users' => '{pk},
            username VARCHAR(32) NOT NULL, email {str} NOT NULL, password {str} NOT NULL,
            name VARCHAR(80) NOT NULL DEFAULT \'\', bio VARCHAR(300) NOT NULL DEFAULT \'\', location VARCHAR(80) NOT NULL DEFAULT \'\',
            website {str} NOT NULL DEFAULT \'\', avatar {str} NULL, cover {str} NULL,
            role VARCHAR(16) NOT NULL DEFAULT \'user\', verified {int} NOT NULL DEFAULT 0, banned {int} NOT NULL DEFAULT 0,
            private_account {int} NOT NULL DEFAULT 0, who_can_message VARCHAR(16) NOT NULL DEFAULT \'everyone\',
            lang VARCHAR(8) NOT NULL DEFAULT \'\', theme VARCHAR(8) NOT NULL DEFAULT \'system\',
            remember_token {str} NULL, reset_token {str} NULL, reset_expires {big} NULL,
            followers_count {int} NOT NULL DEFAULT 0, following_count {int} NOT NULL DEFAULT 0, posts_count {int} NOT NULL DEFAULT 0,
            last_seen {big} NOT NULL DEFAULT 0, created_at {big} NOT NULL',

        'follows' => '{pk}, follower_id {big} NOT NULL, followee_id {big} NOT NULL, status VARCHAR(10) NOT NULL DEFAULT \'accepted\', created_at {big} NOT NULL',
        'blocks' => '{pk}, blocker_id {big} NOT NULL, blocked_id {big} NOT NULL, created_at {big} NOT NULL',

        'posts' => '{pk}, user_id {big} NOT NULL, group_id {big} NULL, body {txt} NULL, privacy VARCHAR(12) NOT NULL DEFAULT \'public\',
            shared_post_id {big} NULL, feeling VARCHAR(32) NULL, images_count {int} NOT NULL DEFAULT 0,
            reactions_count {int} NOT NULL DEFAULT 0, comments_count {int} NOT NULL DEFAULT 0, shares_count {int} NOT NULL DEFAULT 0,
            edited_at {big} NULL, created_at {big} NOT NULL',
        'post_images' => '{pk}, post_id {big} NOT NULL, path {str} NOT NULL, thumb {str} NOT NULL, width {int} NOT NULL DEFAULT 0, height {int} NOT NULL DEFAULT 0, position {int} NOT NULL DEFAULT 0',
        'reactions' => '{pk}, post_id {big} NOT NULL, user_id {big} NOT NULL, type VARCHAR(10) NOT NULL DEFAULT \'like\', created_at {big} NOT NULL',
        'comments' => '{pk}, post_id {big} NOT NULL, user_id {big} NOT NULL, parent_id {big} NULL, body {txt} NOT NULL, likes_count {int} NOT NULL DEFAULT 0, created_at {big} NOT NULL',
        'comment_likes' => '{pk}, comment_id {big} NOT NULL, user_id {big} NOT NULL, created_at {big} NOT NULL',
        'saved' => '{pk}, user_id {big} NOT NULL, post_id {big} NOT NULL, created_at {big} NOT NULL',
        'hashtags' => '{pk}, tag VARCHAR(64) NOT NULL, posts_count {int} NOT NULL DEFAULT 0, last_used {big} NOT NULL DEFAULT 0',
        'post_hashtags' => '{pk}, post_id {big} NOT NULL, hashtag_id {big} NOT NULL, created_at {big} NOT NULL',

        'notifications' => '{pk}, user_id {big} NOT NULL, actor_id {big} NULL, type VARCHAR(24) NOT NULL, post_id {big} NULL, comment_id {big} NULL,
            group_id {big} NULL, read_at {big} NULL, created_at {big} NOT NULL',

        'conversations' => '{pk}, user_a {big} NOT NULL, user_b {big} NOT NULL, last_message_at {big} NOT NULL DEFAULT 0, created_at {big} NOT NULL',
        'messages' => '{pk}, conversation_id {big} NOT NULL, user_id {big} NOT NULL, body {txt} NULL, image {str} NULL, read_at {big} NULL, created_at {big} NOT NULL',

        'stories' => '{pk}, user_id {big} NOT NULL, type VARCHAR(8) NOT NULL DEFAULT \'image\', path {str} NULL, text_body VARCHAR(300) NULL,
            bg VARCHAR(16) NULL, views_count {int} NOT NULL DEFAULT 0, expires_at {big} NOT NULL, created_at {big} NOT NULL',
        'story_views' => '{pk}, story_id {big} NOT NULL, user_id {big} NOT NULL, created_at {big} NOT NULL',

        'communities' => '{pk}, owner_id {big} NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(90) NOT NULL, description VARCHAR(500) NOT NULL DEFAULT \'\',
            privacy VARCHAR(10) NOT NULL DEFAULT \'public\', cover {str} NULL, members_count {int} NOT NULL DEFAULT 0, created_at {big} NOT NULL',
        'community_members' => '{pk}, group_id {big} NOT NULL, user_id {big} NOT NULL, role VARCHAR(10) NOT NULL DEFAULT \'member\', status VARCHAR(10) NOT NULL DEFAULT \'active\', created_at {big} NOT NULL',

        'reports' => '{pk}, reporter_id {big} NOT NULL, target_type VARCHAR(10) NOT NULL, target_id {big} NOT NULL, reason VARCHAR(32) NOT NULL,
            details VARCHAR(500) NOT NULL DEFAULT \'\', status VARCHAR(10) NOT NULL DEFAULT \'open\', created_at {big} NOT NULL',

        'rate_limits' => '`rkey` VARCHAR(120) NOT NULL PRIMARY KEY, hits {int} NOT NULL DEFAULT 0, reset_at {big} NOT NULL',

        // Pages (like Facebook Pages), site pages (About, Contact ...), extensions
        'pages' => '{pk}, owner_id {big} NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(90) NOT NULL, category VARCHAR(40) NOT NULL DEFAULT \'\',
            description VARCHAR(1000) NOT NULL DEFAULT \'\', website {str} NOT NULL DEFAULT \'\', avatar {str} NULL, cover {str} NULL,
            verified {int} NOT NULL DEFAULT 0, followers_count {int} NOT NULL DEFAULT 0, posts_count {int} NOT NULL DEFAULT 0, created_at {big} NOT NULL',
        'page_follows' => '{pk}, page_id {big} NOT NULL, user_id {big} NOT NULL, created_at {big} NOT NULL',
        'page_admins' => '{pk}, page_id {big} NOT NULL, user_id {big} NOT NULL, role VARCHAR(10) NOT NULL DEFAULT \'admin\', created_at {big} NOT NULL',
        'site_pages' => '{pk}, slug VARCHAR(90) NOT NULL, title VARCHAR(120) NOT NULL, body {txt} NULL, in_footer {int} NOT NULL DEFAULT 1,
            published {int} NOT NULL DEFAULT 1, position {int} NOT NULL DEFAULT 0, updated_at {big} NOT NULL, created_at {big} NOT NULL',
        'extensions' => '{pk}, ext_id VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, version VARCHAR(20) NOT NULL DEFAULT \'\', enabled {int} NOT NULL DEFAULT 0,
            license_key {str} NULL, settings {txt} NULL, installed_at {big} NOT NULL',

        // Pro membership: plans and payments (Stripe, PayPal or a custom method the admin approves)
        // sign-in history: IP address and device of each sign-in (and failed attempts), for security
        'login_log' => '{pk}, user_id {big} NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT \'\', agent VARCHAR(255) NOT NULL DEFAULT \'\', device VARCHAR(80) NOT NULL DEFAULT \'\',
            method VARCHAR(10) NOT NULL DEFAULT \'password\', ok {int} NOT NULL DEFAULT 1, created_at {big} NOT NULL',
        'pro_plans' => '{pk}, name VARCHAR(80) NOT NULL, price_cents {int} NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            period_days {int} NOT NULL DEFAULT 30, perks {txt} NULL, active {int} NOT NULL DEFAULT 1, position {int} NOT NULL DEFAULT 0, created_at {big} NOT NULL',
        'pro_payments' => '{pk}, user_id {big} NOT NULL, plan_id {big} NOT NULL, gateway VARCHAR(10) NOT NULL, ref {str} NULL,
            amount_cents {int} NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT \'EUR\', days {int} NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT \'pending\', note VARCHAR(500) NOT NULL DEFAULT \'\', paid_at {big} NULL, created_at {big} NOT NULL',
    ],

    // extra columns (added by the installer, and by the automatic upgrade on sites made before they existed)
    'columns' => [
        'posts' => [
            'status' => 'VARCHAR(12) NOT NULL DEFAULT \'published\'', // published, pending (waits for a group admin), hidden (by a moderator)
            'anonymous' => '{int} NOT NULL DEFAULT 0',
            'format' => 'VARCHAR(8) NOT NULL DEFAULT \'text\'', // text or html (rich text)
            'page_id' => '{big} NULL',
            'hidden_by' => '{big} NULL',
            'hidden_at' => '{big} NULL',
        ],
        'notifications' => [
            'page_id' => '{big} NULL',
        ],
        'users' => [
            'pro_until' => '{big} NULL',
            'pro_plan_id' => '{big} NULL',
            'session_version' => '{int} NOT NULL DEFAULT 0', // raised by "sign out everywhere"; older sessions then end
        ],
        'stories' => [
            'text_color' => 'VARCHAR(16) NULL', // white, black, yellow, ...
            'font' => 'VARCHAR(10) NULL', // modern, serif, mono, hand
            'text_pos' => 'VARCHAR(8) NULL', // top, center, bottom (caption on a photo or video)
        ],
        'communities' => [
            'avatar' => '{str} NULL',
            'category' => 'VARCHAR(40) NOT NULL DEFAULT \'\'',
            'rules' => '{txt} NULL',
            'visibility' => 'VARCHAR(10) NOT NULL DEFAULT \'listed\'', // listed or hidden (not in lists and search)
            'post_permission' => 'VARCHAR(10) NOT NULL DEFAULT \'members\'', // members or admins
            'post_approval' => '{int} NOT NULL DEFAULT 0',
            'allow_anonymous' => '{int} NOT NULL DEFAULT 0',
            'pinned_post_id' => '{big} NULL',
        ],
    ],

    'indexes' => [
        'users' => ['UNIQUE:username', 'UNIQUE:email', 'created_at'],
        'follows' => ['UNIQUE:follower_id,followee_id', 'followee_id,status'],
        'blocks' => ['UNIQUE:blocker_id,blocked_id', 'blocked_id'],
        'posts' => ['user_id,created_at', 'group_id,created_at', 'created_at', 'shared_post_id', 'status,id', 'page_id,id'],
        'post_images' => ['post_id,position'],
        'reactions' => ['UNIQUE:post_id,user_id', 'user_id'],
        'comments' => ['post_id,created_at', 'parent_id'],
        'comment_likes' => ['UNIQUE:comment_id,user_id'],
        'saved' => ['UNIQUE:user_id,post_id'],
        'hashtags' => ['UNIQUE:tag', 'last_used'],
        'post_hashtags' => ['UNIQUE:post_id,hashtag_id', 'hashtag_id,created_at'],
        'notifications' => ['user_id,created_at', 'user_id,read_at'],
        'conversations' => ['UNIQUE:user_a,user_b', 'user_a,last_message_at', 'user_b,last_message_at'],
        'messages' => ['conversation_id,id'],
        'stories' => ['user_id,expires_at', 'expires_at'],
        'story_views' => ['UNIQUE:story_id,user_id'],
        'communities' => ['UNIQUE:slug', 'created_at'],
        'community_members' => ['UNIQUE:group_id,user_id', 'user_id'],
        'reports' => ['status,created_at'],
        'pages' => ['UNIQUE:slug', 'owner_id', 'followers_count'],
        'page_follows' => ['UNIQUE:page_id,user_id', 'user_id'],
        'page_admins' => ['UNIQUE:page_id,user_id', 'user_id'],
        'site_pages' => ['UNIQUE:slug'],
        'extensions' => ['UNIQUE:ext_id'],
        'pro_plans' => ['active,position'],
        'login_log' => ['user_id,id', 'ip', 'created_at'],
        'pro_payments' => ['user_id,id', 'status,id', 'gateway,ref'],
    ],
];
