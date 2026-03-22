<?php
/**
 * KubeWP Platform wp-config additions.
 * This file is included by wp-config.php via:
 *   require_once('/var/www/html/wp-config-kubewp.php');
 *
 * All configuration comes from environment variables.
 */

// === DISABLE WP-CRON (replaced by K8s CronJob) ===
define('DISABLE_WP_CRON', true);

// === AUTH KEYS from K8s Secret (NOT auto-generated) ===
define('AUTH_KEY',         getenv('WP_AUTH_KEY')         ?: 'put-your-unique-phrase-here');
define('SECURE_AUTH_KEY',  getenv('WP_SECURE_AUTH_KEY')  ?: 'put-your-unique-phrase-here');
define('LOGGED_IN_KEY',    getenv('WP_LOGGED_IN_KEY')    ?: 'put-your-unique-phrase-here');
define('NONCE_KEY',        getenv('WP_NONCE_KEY')        ?: 'put-your-unique-phrase-here');
define('AUTH_SALT',        getenv('WP_AUTH_SALT')         ?: 'put-your-unique-phrase-here');
define('SECURE_AUTH_SALT', getenv('WP_SECURE_AUTH_SALT') ?: 'put-your-unique-phrase-here');
define('LOGGED_IN_SALT',   getenv('WP_LOGGED_IN_SALT')   ?: 'put-your-unique-phrase-here');
define('NONCE_SALT',       getenv('WP_NONCE_SALT')       ?: 'put-your-unique-phrase-here');

// === DATABASE from environment ===
define('DB_NAME',     getenv('WORDPRESS_DB_NAME')     ?: 'wordpress');
define('DB_USER',     getenv('WORDPRESS_DB_USER')     ?: 'wordpress');
define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: '');
define('DB_HOST',     getenv('WORDPRESS_DB_HOST')     ?: 'localhost');
define('DB_CHARSET',  'utf8mb4');
define('DB_COLLATE',  '');

// === IMMUTABLE FILESYSTEM ===
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);

// === OBJECT STORAGE MEDIA OFFLOAD ===
define('KUBEWP_S3_BUCKET',   getenv('KUBEWP_S3_BUCKET')   ?: '');
define('KUBEWP_S3_ENDPOINT', getenv('KUBEWP_S3_ENDPOINT') ?: '');
define('KUBEWP_S3_REGION',   getenv('KUBEWP_S3_REGION')   ?: 'auto');
define('KUBEWP_S3_KEY',      getenv('KUBEWP_S3_KEY')      ?: '');
define('KUBEWP_S3_SECRET',   getenv('KUBEWP_S3_SECRET')   ?: '');
define('KUBEWP_CDN_URL',     getenv('KUBEWP_CDN_URL')     ?: '');

// === REDIS OBJECT CACHE ===
define('WP_REDIS_HOST',     getenv('REDIS_HOST')     ?: 'redis');
define('WP_REDIS_PORT',     getenv('REDIS_PORT')     ?: '6379');
define('WP_REDIS_DATABASE', getenv('REDIS_DATABASE') ?: '0');

// === PLATFORM OVERRIDES ===
define('WP_POST_REVISIONS', 10);
define('AUTOSAVE_INTERVAL', 120);
define('EMPTY_TRASH_DAYS', 30);

// === DEBUG (controlled by platform) ===
define('WP_DEBUG',         filter_var(getenv('WP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_LOG',     filter_var(getenv('WP_DEBUG_LOG') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_DISPLAY', false);

// Table prefix
$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';
