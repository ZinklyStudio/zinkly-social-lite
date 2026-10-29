<?php
/**
 * Zinkly Social Lite - front controller. Every page goes through here (see .htaccess).
 * Requirements: PHP 8.1+, PDO (MySQL / MariaDB or SQLite), GD. No Composer, no terminal, no cron.
 */
require __DIR__ . '/app/bootstrap.php';

zl_start();
require ZL_ROOT . '/app/routes.php';
Router::dispatch();
