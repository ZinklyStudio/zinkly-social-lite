<?php
/** Zinkly Social Lite - web installer (no terminal needed). Locked after a successful install (data/installed.lock). */
require dirname(__DIR__) . '/app/bootstrap.php';

zl_start(false);

if (isset($_GET['lang']) && isset(Lang::LANGS[$_GET['lang']])) {
    $_SESSION['install_lang'] = $_GET['lang'];
}
Lang::set($_SESSION['install_lang'] ?? Lang::detect(null));

$done = zl_installed();
if ($done) {
    http_response_code(403);
}

/* "Test connection" button: checks the database without installing anything */
if (!$done && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'test-db') {
    csrf_check();
    // no database yet, so the limit is kept in the session: 30 tests per 10 minutes
    $hits = array_filter((array) ($_SESSION['install_tests'] ?? []), fn ($ts) => $ts > time() - 600);
    if (count($hits) >= 30) {
        json_out(['ok' => false, 'message' => t('error.429'), 'checks' => []], 429);
    }
    $hits[] = time();
    $_SESSION['install_tests'] = array_values($hits);
    json_out(Installer::checkDatabase(Installer::dbFromInput($_POST)));
}

$errors = [];
$fieldErrors = [];
$dbCheck = null;
$step = 'form';
$reqs = Installer::requirements();
$reqOk = !in_array(false, array_map(fn ($r) => $r[1] || !$r[3], $reqs), true);

if (!$done && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (!$reqOk) {
        $errors[] = t('install.fix_requirements');
    }
    $site = Text::clean((string) ($_POST['site_name'] ?? ''), 80) ?: 'Zinkly Social Lite';
    $lang = isset(Lang::LANGS[$_POST['default_lang'] ?? '']) ? $_POST['default_lang'] : Lang::locale();
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $fieldErrors['username'] = t('auth.username_rules');
    } elseif (in_array(strtolower($username), array_diff(Users::RESERVED, ['admin']), true)) {
        $fieldErrors['username'] = t('auth.username_taken');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = t('auth.email_invalid');
    }
    if ($e = Auth::passwordError($password)) {
        $fieldErrors['password'] = $e;
    } elseif (!hash_equals($password, (string) ($_POST['password2'] ?? ''))) {
        $fieldErrors['password2'] = t('install.password_mismatch');
    }
    $db = Installer::dbFromInput($_POST);

    // the database is checked again here (the browser check can be skipped), before anything is created
    if (!$errors && !$fieldErrors) {
        $dbCheck = Installer::checkDatabase($db);
        if (!$dbCheck['ok']) {
            $errors[] = $dbCheck['message'];
        }
    }
    if (!$errors && !$fieldErrors) {
        $conn = $db;
        if ($db['driver'] === 'sqlite') {
            // a random file name, so the database cannot be guessed even on a server that ignores .htaccess
            $db['path'] = 'data/zinkly-' . bin2hex(random_bytes(8)) . '.sqlite';
            $conn['path'] = ZL_ROOT . '/' . $db['path'];
        }
        $created = false;
        try {
            DB::connect($conn);
            $created = true;
            // (no transaction: MySQL ends one at every CREATE TABLE; a failure removes the tables below instead)
            Installer::createSchema();
            Installer::defaults($site, $lang);
            $uid = Users::create($username, $email, $password, $username, 'admin');
            DB::update('users', ['verified' => 1, 'lang' => $lang], 'id = ?', [$uid]);
            // final check: the tables answer and the admin can be read back
            $admin = Users::byUsername($username);
            setting('__flush'); // settings were cached (empty) before the tables existed
            if (!$admin || $admin['role'] !== 'admin' || DB::value('SELECT svalue FROM {settings} WHERE skey = ?', ['version']) !== Installer::VERSION) {
                throw new RuntimeException(t('install.verify_failed'));
            }
            Installer::writeConfig(['db' => $db, 'debug' => false, 'installed_at' => date('c'), 'version' => Installer::VERSION]);
            if (file_put_contents(ZL_ROOT . '/data/installed.lock', date('c')) === false) {
                throw new RuntimeException(t('install.data_not_writable'));
            }
            Auth::login($admin);
            $step = 'done';
            $summary = ['db' => $db['driver'] === 'sqlite' ? 'SQLite' : ($db['name'] . ' @ ' . $db['host']), 'checks' => $dbCheck['checks'] ?? [],
                'admin' => $username, 'tables' => count((require ZL_ROOT . '/app/schema.php')['tables'])];
        } catch (Throwable $e) {
            $detail = trim(preg_replace('/SQLSTATE\[\w+\]:?\s*(\[\d+\])?\s*/', '', $e->getMessage()) ?? $e->getMessage());
            $msg = $e instanceof PDOException ? t($created ? 'install.create_failed' : 'install.db_failed') . ' ' . $detail : $e->getMessage();
            $errors[] = $msg;
            // leave nothing half-installed: no config, no lock, no tables, no SQLite file
            @unlink(ZL_ROOT . '/data/config.php');
            @unlink(ZL_ROOT . '/data/installed.lock');
            if ($created && $db['driver'] === 'mysql') {
                Installer::dropSchema();
            }
            if ($db['driver'] === 'sqlite' && isset($conn['path'])) {
                foreach (['', '-wal', '-shm'] as $sfx) {
                    @unlink($conn['path'] . $sfx);
                }
            }
        }
    }
}
$v = fn ($k, $d = '') => e((string) ($_POST[$k] ?? $d));
$fe = fn ($k) => isset($fieldErrors[$k]) ? '<small class="zl-field-error" role="alert">' . e($fieldErrors[$k]) . '</small>' : '';
$stateIcon = ['ok' => 'check', 'warn' => 'clock', 'fail' => 'x'];
?><!doctype html>
<html lang="<?= e(Lang::htmlLang()) ?>" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(t('install.title')) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/zl.css')) ?>">
</head>
<body class="zl-guest" data-texts="<?= e(json_encode(Lang::jsTexts(), JSON_UNESCAPED_UNICODE)) ?>">
<main class="zl-install">
    <div class="zl-between">
        <div class="zl-brand"><span class="zl-logo">Z</span><b>Zinkly Social Lite</b><span class="zl-badge">v<?= e(Installer::VERSION) ?></span></div>
        <form method="get"><label class="sr-only" for="ilang"><?= e(t('settings.language')) ?></label>
            <select id="ilang" name="lang" class="zl-input zl-input-sm" data-autosubmit><?php foreach (Lang::LANGS as $code => $name): ?><option value="<?= e($code) ?>" <?= $code === Lang::locale() ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select>
            <noscript><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('common.go')) ?></button></noscript>
        </form>
    </div>

<?php if ($done): ?>
    <section class="zl-card zl-pad">
        <h1 class="zl-h1"><?= e(t('install.already')) ?></h1>
        <p class="zl-muted"><?= e(t('install.already_hint')) ?></p>
        <a class="zl-btn zl-btn-main" href="<?= e(url('')) ?>"><?= e(t('install.open_site')) ?></a>
    </section>
<?php elseif ($step === 'done'): ?>
    <section class="zl-card zl-pad zl-center">
        <div class="zl-done-ico"><?= icon('check', 'zl-i zl-i-lg') ?></div>
        <h1 class="zl-h1"><?= e(t('install.done_title')) ?></h1>
        <p class="zl-muted"><?= e(t('install.done_text')) ?></p>
        <ul class="zl-reqs zl-done-list">
            <li class="is-ok"><?= icon('check') ?><span><?= e(t('install.sum_db')) ?></span><small><?= e($summary['db']) ?></small></li>
            <?php foreach ($summary['checks'] as [$label, $state, $detail]): ?><li class="is-<?= $state === 'fail' ? 'bad' : ($state === 'warn' ? 'warn' : 'ok') ?>"><?= icon($stateIcon[$state]) ?><span><?= e($label) ?></span><small><?= e($detail) ?></small></li><?php endforeach ?>
            <li class="is-ok"><?= icon('check') ?><span><?= e(t('install.sum_tables')) ?></span><small><?= (int) $summary['tables'] ?></small></li>
            <li class="is-ok"><?= icon('check') ?><span><?= e(t('install.sum_admin')) ?></span><small>@<?= e($summary['admin']) ?></small></li>
            <li class="is-ok"><?= icon('check') ?><span><?= e(t('install.sum_locked')) ?></span><small>data/installed.lock</small></li>
        </ul>
        <p class="zl-note"><?= e(t('install.done_security')) ?></p>
        <div class="zl-row" style="justify-content:center;flex-wrap:wrap">
            <a class="zl-btn zl-btn-main" href="<?= e(url('')) ?>"><?= e(t('install.open_site')) ?></a>
            <a class="zl-btn zl-btn-soft" href="<?= e(url('admin/settings')) ?>"><?= icon('settings', 'zl-i-sm') ?><?= e(t('install.open_settings')) ?></a>
        </div>
    </section>
<?php else: ?>
    <div>
        <h1 class="zl-h1"><?= e(t('install.title')) ?></h1>
        <p class="zl-muted"><?= e(t('install.intro')) ?></p>
    </div>

    <?php foreach ($errors as $err): ?><div class="zl-alert zl-alert-error" role="alert"><?= e($err) ?></div><?php endforeach ?>
    <?php if ($fieldErrors): ?><div class="zl-alert zl-alert-error" role="alert"><?= e(t('install.fix_fields')) ?></div><?php endif ?>

    <section class="zl-card zl-pad">
        <h2 class="zl-h2 zl-step"><span>1</span><?= e(t('install.requirements')) ?></h2>
        <ul class="zl-reqs">
        <?php foreach ($reqs as [$label, $ok, $info, $required]): ?>
            <li class="<?= $ok ? 'is-ok' : ($required ? 'is-bad' : 'is-warn') ?>"><?= icon($ok ? 'check' : ($required ? 'x' : 'clock')) ?><span><?= e($label) ?><?php if (!$required): ?> <em class="zl-optional"><?= e(t('install.optional')) ?></em><?php endif ?></span><small><?= e($ok && !$required ? '' : $info) ?></small></li>
        <?php endforeach ?>
        </ul>
        <?php if (!$reqOk): ?><p class="zl-alert zl-alert-error" style="margin-top:12px"><?= e(t('install.fix_requirements')) ?></p><?php endif ?>
    </section>

    <form method="post" class="zl-card zl-pad zl-stack" autocomplete="off" data-install-form data-test-url="<?= e(url('install/') . '?action=test-db') ?>" novalidate>
        <?= csrf_field() ?>
        <h2 class="zl-h2 zl-step"><span>2</span><?= e(t('install.database')) ?></h2>
        <div class="zl-seg" role="radiogroup" aria-label="<?= e(t('install.database')) ?>">
            <input type="radio" name="driver" value="mysql" id="d-mysql" class="sr-only" <?= ($_POST['driver'] ?? (extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite')) !== 'sqlite' ? 'checked' : '' ?> <?= extension_loaded('pdo_mysql') ? '' : 'disabled' ?>>
            <label for="d-mysql">MySQL / MariaDB <small><?= e(t('install.recommended')) ?></small></label>
            <input type="radio" name="driver" value="sqlite" id="d-sqlite" class="sr-only" <?= ($_POST['driver'] ?? (extension_loaded('pdo_mysql') ? '' : 'sqlite')) === 'sqlite' ? 'checked' : '' ?> <?= extension_loaded('pdo_sqlite') ? '' : 'disabled' ?>>
            <label for="d-sqlite">SQLite <small><?= e(t('install.sqlite_hint')) ?></small></label>
            <div class="zl-seg-mysql zl-grid2">
                <label class="zl-field"><span><?= e(t('install.db_host')) ?></span><input class="zl-input" name="db_host" value="<?= $v('db_host', 'localhost') ?>" spellcheck="false"><small class="zl-hint"><?= e(t('install.db_host_hint')) ?></small></label>
                <label class="zl-field"><span><?= e(t('install.db_port')) ?></span><input class="zl-input" name="db_port" inputmode="numeric" value="<?= $v('db_port', '3306') ?>"></label>
                <label class="zl-field"><span><?= e(t('install.db_name')) ?></span><input class="zl-input" name="db_name" value="<?= $v('db_name') ?>" spellcheck="false"></label>
                <label class="zl-field"><span><?= e(t('install.db_user')) ?></span><input class="zl-input" name="db_user" value="<?= $v('db_user') ?>" spellcheck="false" autocomplete="off"></label>
                <label class="zl-field zl-span2"><span><?= e(t('install.db_pass')) ?></span><input class="zl-input" type="password" name="db_pass" value="<?= $v('db_pass') ?>" autocomplete="new-password"><small class="zl-hint"><?= e(t('install.db_pass_hint')) ?></small></label>
            </div>
            <p class="zl-seg-sqlite zl-hint"><?= e(t('install.sqlite_text')) ?></p>
        </div>
        <div class="zl-grid2">
            <label class="zl-field"><span><?= e(t('install.prefix')) ?></span><input class="zl-input" name="prefix" value="<?= $v('prefix', 'zl_') ?>" pattern="[A-Za-z0-9_]{0,12}" maxlength="12" spellcheck="false"><small class="zl-hint"><?= e(t('install.prefix_hint')) ?></small></label>
            <div class="zl-field"><span>&nbsp;</span><button type="button" class="zl-btn zl-btn-soft zl-btn-block" data-db-test><?= icon('check', 'zl-i-sm') ?><?= e(t('install.test')) ?></button></div>
        </div>
        <div class="zl-db-result<?= $dbCheck ? ($dbCheck['ok'] ? ' is-ok' : ' is-bad') : '' ?>" data-db-result <?= $dbCheck ? '' : 'hidden' ?> role="status" aria-live="polite">
            <?php if ($dbCheck): ?>
                <b><?= e($dbCheck['message']) ?></b>
                <ul class="zl-reqs"><?php foreach ($dbCheck['checks'] as [$label, $state, $detail]): ?><li class="is-<?= $state === 'fail' ? 'bad' : ($state === 'warn' ? 'warn' : 'ok') ?>"><?= icon($stateIcon[$state]) ?><span><?= e($label) ?></span><small><?= e($detail) ?></small></li><?php endforeach ?></ul>
            <?php endif ?>
        </div>

        <h2 class="zl-h2 zl-step"><span>3</span><?= e(t('install.site')) ?></h2>
        <div class="zl-grid2">
            <label class="zl-field"><span><?= e(t('install.site_name')) ?></span><input class="zl-input" name="site_name" value="<?= $v('site_name', 'Zinkly Social Lite') ?>" maxlength="80" required></label>
            <label class="zl-field"><span><?= e(t('install.default_lang')) ?></span><select class="zl-input" name="default_lang"><?php foreach (Lang::LANGS as $code => $name): ?><option value="<?= e($code) ?>" <?= $code === ($_POST['default_lang'] ?? Lang::locale()) ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select></label>
        </div>

        <h2 class="zl-h2 zl-step"><span>4</span><?= e(t('install.admin')) ?></h2>
        <div class="zl-grid2">
            <label class="zl-field"><span><?= e(t('auth.username')) ?></span><input class="zl-input<?= isset($fieldErrors['username']) ? ' is-invalid' : '' ?>" name="username" value="<?= $v('username', 'admin') ?>" pattern="[A-Za-z0-9_]{3,32}" maxlength="32" required autocomplete="username" data-check="username"><?= $fe('username') ?><small class="zl-hint"><?= e(t('auth.username_rules')) ?></small></label>
            <label class="zl-field"><span><?= e(t('auth.email')) ?></span><input class="zl-input<?= isset($fieldErrors['email']) ? ' is-invalid' : '' ?>" type="email" name="email" value="<?= $v('email') ?>" required autocomplete="email" data-check="email"><?= $fe('email') ?></label>
            <label class="zl-field"><span><?= e(t('auth.password')) ?></span><input class="zl-input<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>" type="password" name="password" minlength="8" required autocomplete="new-password" data-check="password"><?= $fe('password') ?>
                <span class="zl-meter" data-meter aria-hidden="true"><i></i><i></i><i></i><i></i></span><small class="zl-hint"><?= e(t('auth.password_hint')) ?></small></label>
            <label class="zl-field"><span><?= e(t('install.password2')) ?></span><input class="zl-input<?= isset($fieldErrors['password2']) ? ' is-invalid' : '' ?>" type="password" name="password2" minlength="8" required autocomplete="new-password" data-check="password2"><?= $fe('password2') ?></label>
        </div>
        <button class="zl-btn zl-btn-main zl-btn-lg" data-install-btn <?= $reqOk ? '' : 'disabled' ?>><span data-install-label><?= e(t('install.install')) ?></span></button>
        <p class="zl-hint zl-center"><?= e(t('install.install_hint')) ?></p>
    </form>
<?php endif ?>
</main>
<script src="<?= e(asset('assets/js/zl.js')) ?>" defer></script>
</body>
</html>
