<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('wp_json_encode')) { function wp_json_encode($value, int $flags = 0): string { return (string) json_encode($value, $flags); } }
if (!function_exists('sanitize_html_class')) { function sanitize_html_class(string $value): string { return preg_replace('/[^A-Za-z0-9_-]/', '-', $value) ?? ''; } }
if (!function_exists('esc_attr')) { function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('esc_html')) { function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('wp_kses_post')) { function wp_kses_post(string $value): string { return $value; } }
