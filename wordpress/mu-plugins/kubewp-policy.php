<?php
/**
 * Plugin Name: KubeWP Policy Enforcement
 * Description: Enforces platform plugin blocklist/allowlist policies at runtime.
 * Version: 1.0.0
 * Author: KubeWP
 */
defined('ABSPATH') || exit;

/**
 * Runtime enforcement of plugin blocklist policies (per D-29 enforcement point 2).
 *
 * Reads the blocklist JSON file written by the data-plane agent and deactivates
 * any active plugins that match a blocklisted slug. Runs on every request to ensure
 * policy compliance even if a plugin was manually reactivated via DB.
 */
add_action('muplugins_loaded', function () {
    $blocklist_file = ABSPATH . '.kubewp-blocklist.json';
    if (!file_exists($blocklist_file)) {
        return;
    }

    $raw = file_get_contents($blocklist_file);
    if ($raw === false) {
        return;
    }

    $blocklist = json_decode($raw, true);
    if (!is_array($blocklist) || empty($blocklist)) {
        return;
    }

    add_filter('option_active_plugins', function ($plugins) use ($blocklist) {
        if (!is_array($plugins)) {
            return $plugins;
        }

        $deactivated = [];
        foreach ($plugins as $key => $plugin) {
            $slug = dirname($plugin);
            if ($slug === '.') {
                // Single-file plugin (e.g., hello.php) — use filename without .php
                $slug = basename($plugin, '.php');
            }
            if (in_array($slug, $blocklist, true)) {
                unset($plugins[$key]);
                $deactivated[] = $plugin;
            }
        }

        if (!empty($deactivated)) {
            error_log(
                '[KubeWP Policy] Deactivated blocked plugins: ' .
                implode(', ', $deactivated)
            );
        }

        return array_values($plugins);
    });
});
