<?php
/**
 * Plugin Name: KubeWP Platform Integration
 * Description: Platform health checks, admin notices, and K8s integration
 * Version: 1.0.0
 * Author: KubeWP
 */
defined('ABSPATH') || exit;

// Health check endpoint that bypasses WordPress loading
add_action('init', function() {
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/kubewp-health') {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo 'ok';
        exit;
    }
}, 1);

// Remove WP-Cron spawning (handled by K8s CronJob)
remove_action('init', 'wp_cron');
add_filter('cron_request', function($cron_request) {
    // Prevent wp_cron from being called via HTTP
    $cron_request['url'] = '';
    return $cron_request;
});

// Admin notice for immutable filesystem
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>KubeWP:</strong> File modifications are managed through the platform dashboard. ';
        echo 'Use the KubeWP dashboard to install or update plugins and themes.';
        echo '</p></div>';
    }
});

// Report PHP version and extensions to platform
add_filter('kubewp_runtime_info', function() {
    return [
        'php_version'  => PHP_VERSION,
        'extensions'   => get_loaded_extensions(),
        'memory_limit' => ini_get('memory_limit'),
        'max_upload'   => ini_get('upload_max_filesize'),
    ];
});
