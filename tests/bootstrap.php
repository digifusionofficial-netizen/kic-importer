<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('wp_json_encode')) { function wp_json_encode($value, int $flags = 0): string { return (string) json_encode($value, $flags); } }
if (!function_exists('sanitize_html_class')) { function sanitize_html_class(string $value): string { return preg_replace('/[^A-Za-z0-9_-]/', '-', $value) ?? ''; } }
if (!function_exists('esc_attr')) { function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('esc_html')) { function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('esc_url')) {
    // Mirrors the one behavior of WordPress's real esc_url() that matters for
    // these tests: a value with no scheme (":") that also doesn't start with
    // "/", "#", or "?" is treated as a bare host and gets "http://" prepended.
    // A naive htmlspecialchars()-only stub hides exactly this class of bug —
    // calling esc_url() on a not-yet-rewritten local relative path (e.g.
    // "assets/images/logo.png") silently corrupts it into
    // "http://assets/images/logo.png" and it never reaches AssetUrlRewriter.
    function esc_url(string $url): string {
        if ($url === '') { return $url; }
        $url = str_replace(' ', '%20', ltrim($url));
        if (strpos($url, ':') === false && !in_array($url[0], array('/', '#', '?'), true) && !preg_match('/^[a-z0-9-]+?\.php/i', $url)) {
            $url = 'http://' . $url;
        }
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('wp_kses_post')) { function wp_kses_post(string $value): string { return $value; } }
