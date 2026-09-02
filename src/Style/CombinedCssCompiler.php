<?php

namespace KIC\Importer\Style;

use RuntimeException;

/** Builds the permanent, import-scoped safety-net stylesheet. */
final class CombinedCssCompiler
{
    /** @param array<string,string> $files @return array{css:string,files:array<int,string>,bytes:int,rules:int,sanitized:array<int,string>} */
    public function compile(array $files, string $siteScope): array
    {
        if (!preg_match('/^kic-site-[a-z0-9-]+$/', $siteScope)) {
            throw new RuntimeException('The CSS fallback scope is invalid.');
        }
        $combined = '';
        $sanitized = array();
        foreach ($files as $name => $css) {
            $css = $this->sanitize($css, (string) $name, $sanitized);
            $combined .= "\n/* KIC source: " . preg_replace('/[^a-z0-9.\/-]/i', '', (string) $name) . " */\n" . $css;
        }
        $rules = 0;
        $compiled = $this->scopeRules($combined, '.' . $siteScope, $rules);
        return array('css' => trim($compiled), 'files' => array_keys($files), 'bytes' => strlen($compiled), 'rules' => $rules, 'sanitized' => $sanitized);
    }

    /** @param array<int,string> $events */
    private function sanitize(string $css, string $file, array &$events): string
    {
        $css = str_replace("\0", '', $css);
        $css = preg_replace('/<\/?style\b[^>]*>/i', '', $css) ?? '';
        $count = 0;
        $css = preg_replace('/@import\s+[^;]+;/i', '', $css, -1, $count) ?? '';
        if ($count) { $events[] = $file . ': removed prohibited @import rule'; }
        foreach (array('/expression\s*\(/i', '/url\s*\(\s*["\']?\s*javascript\s*:/i', '/-moz-binding\s*:/i') as $pattern) {
            if (preg_match($pattern, $css)) { throw new RuntimeException('Unsafe CSS was rejected in ' . $file . '.'); }
        }
        return $css;
    }

    private function scopeRules(string $css, string $scope, int &$count): string
    {
        $out = ''; $length = strlen($css); $offset = 0;
        while ($offset < $length) {
            if (preg_match('/\G\s*(\/\*[\s\S]*?\*\/\s*)/A', $css, $comment, 0, $offset)) {
                $out .= $comment[0]; $offset += strlen($comment[0]); continue;
            }
            while ($offset < $length && ctype_space($css[$offset])) { $out .= $css[$offset++]; }
            if ($offset >= $length) { break; }
            $open = strpos($css, '{', $offset);
            if ($open === false) { break; }
            $header = trim(substr($css, $offset, $open - $offset));
            $close = $this->matchingBrace($css, $open);
            if ($close === null) { throw new RuntimeException('Malformed CSS: an opening brace is not closed.'); }
            $body = substr($css, $open + 1, $close - $open - 1);
            if (preg_match('/^@(media|supports|layer|container)\b/i', $header)) {
                $out .= $header . '{' . $this->scopeRules($body, $scope, $count) . '}';
            } elseif (preg_match('/^@(font-face|keyframes|-webkit-keyframes|page|property)\b/i', $header)) {
                $out .= $header . '{' . $body . '}';
            } elseif (str_starts_with($header, '@')) {
                $out .= '/* KIC omitted unsupported at-rule: ' . preg_replace('/[^a-z0-9@_-]/i', '', $header) . ' */';
            } else {
                $selectors = array();
                foreach (explode(',', $header) as $selector) {
                    $selector = trim($selector);
                    if ($selector === '') { continue; }
                    $selector = preg_replace('/\.([a-z_][a-z0-9_-]*)/i', '.kic-src-$1', $selector) ?? $selector;
                    $selector = preg_replace('/\[data-menu(?:=[^\]]+)?\]/i', '.kic-menu', $selector) ?? $selector;
                    $selector = preg_replace('/(^|[\s>+~])(html|body|:root)(?=\b|\s|[>+~.:#\[])/i', '$1', $selector) ?? $selector;
                    $selector = trim($selector);
                    $selectors[] = $selector === '' ? $scope : $scope . ' ' . $selector;
                    if (str_starts_with($selector, '.')) { $selectors[] = $scope . $selector; }
                    if (str_contains($selector, '.kic-src-button')) {
                        $selectors[] = ($selector === '' ? $scope : $scope . ' ' . $selector) . ' .kb-button';
                    }
                    if (preg_match('/(?:display\s*:\s*(?:grid|flex)|grid-template-columns|flex-direction|justify-content|align-items|\bgap\s*:)/i', $body)) {
                        $selectors[] = ($selector === '' ? $scope : $scope . ' ' . $selector) . ' > .kt-inside-inner-col';
                        $selectors[] = ($selector === '' ? $scope : $scope . ' ' . $selector) . ' > .kt-row-column-wrap';
                        if (str_starts_with($selector, '.')) {
                            $selectors[] = $scope . $selector . ' > .kt-inside-inner-col';
                            $selectors[] = $scope . $selector . ' > .kt-row-column-wrap';
                        }
                    }
                }
                if ($selectors) { $out .= implode(',', array_unique($selectors)) . '{' . $body . '}'; $count++; }
            }
            $offset = $close + 1;
        }
        return $out;
    }

    private function matchingBrace(string $css, int $open): ?int
    {
        $depth = 0; $quote = ''; $comment = false; $length = strlen($css);
        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i]; $next = $i + 1 < $length ? $css[$i + 1] : '';
            if ($comment) { if ($char === '*' && $next === '/') { $comment = false; $i++; } continue; }
            if ($quote !== '') { if ($char === '\\') { $i++; continue; } if ($char === $quote) { $quote = ''; } continue; }
            if ($char === '/' && $next === '*') { $comment = true; $i++; continue; }
            if ($char === '"' || $char === "'") { $quote = $char; continue; }
            if ($char === '{') { $depth++; }
            if ($char === '}' && --$depth === 0) { return $i; }
        }
        return null;
    }
}
