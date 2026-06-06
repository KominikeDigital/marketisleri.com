<?php
// Copy this file to config.local.php inside public_html, then fill in the values you need.
// config.local.php is optional. If you do not create it, the site uses database.db with SQLite.

define('SITE_URL', 'https://marketisleri.com');

// Change these before going live.
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'guculu-bir-sifre-yazin');

// Keep DB_DRIVER as sqlite to use public_html/database.db.
// Change it to mysql after creating a cPanel database and user.
define('DB_DRIVER', 'sqlite');
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpanelprefix_databaseadi');
define('DB_USER', 'cpanelprefix_kullaniciadi');
define('DB_PASS', 'veritabani_sifresi');

// Optional SQLite path if you want to store the DB outside public_html.
// define('DB_DRIVER', 'sqlite');
// define('DB_PATH', dirname(__DIR__) . '/database.db');
