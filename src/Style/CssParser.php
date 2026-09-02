<?php

namespace KIC\Importer\Style;

final class CssParser
{
    public const SUPPORTED_PROPERTIES = array(
        'display', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'gap',
        'grid-template-columns', 'grid-column', 'grid-row', 'width', 'max-width', 'min-width',
        'height', 'min-height', 'aspect-ratio', 'margin', 'margin-top', 'margin-right', 'margin-bottom',
        'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'color', 'background', 'background-color', 'background-image', 'background-position',
        'background-size', 'background-repeat', 'font-family', 'font-size', 'font-weight', 'line-height',
        'letter-spacing', 'text-align', 'text-transform', 'border', 'border-width', 'border-style',
        'border-color', 'border-radius', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'box-shadow', 'opacity', 'object-fit', 'object-position', 'overflow', 'transition', 'transform',
        'text-decoration', 'margin-inline', 'box-sizing', 'list-style', 'resize'
    );

    /** @param array<string,string> $files */
    public function parseFiles(array $files): Stylesheet
    {
        $rules = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        $variables = array();
        $unsupported = array();
        $order = 0;
        foreach ($files as $path => $css) {
            $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';
            $segments = $this->extractMedia($css);
            $this->parseRules($segments['base'], 'desktop', $path, $rules, $variables, $unsupported, $order);
            foreach ($segments['media'] as $media) {
                $viewport = $this->viewportForQuery($media['query']);
                if ($viewport === null) {
                    $unsupported[] = array('file' => $path, 'selector' => '@media ' . $media['query'], 'property' => '@media', 'value' => $media['query'], 'reason' => 'Unsupported media query.');
                    continue;
                }
                $this->parseRules($media['css'], $viewport, $path, $rules, $variables, $unsupported, $order);
            }
        }
        return new Stylesheet($rules, $variables, $unsupported);
    }

    /** @return array{base:string,media:array<int,array{query:string,css:string}>} */
    private function extractMedia(string $css): array
    {
        $base = '';
        $media = array();
        $cursor = 0;
        while (($start = stripos($css, '@media', $cursor)) !== false) {
            $base .= substr($css, $cursor, $start - $cursor);
            $open = strpos($css, '{', $start);
            if ($open === false) { break; }
            $depth = 1; $end = $open + 1; $length = strlen($css);
            while ($end < $length && $depth > 0) { if ($css[$end] === '{') { $depth++; } elseif ($css[$end] === '}') { $depth--; } $end++; }
            if ($depth !== 0) { break; }
            $media[] = array('query' => trim(substr($css, $start + 6, $open - ($start + 6))), 'css' => substr($css, $open + 1, $end - $open - 2));
            $cursor = $end;
        }
        $base .= substr($css, $cursor);
        return array('base' => $base, 'media' => $media);
    }

    /** @param array<string,array<int,array{selector:string,declarations:array<string,string>,order:int}>> $rules @param array<string,string> $variables @param array<int,array<string,mixed>> $unsupported */
    private function parseRules(string $css, string $viewport, string $file, array &$rules, array &$variables, array &$unsupported, int &$order): void
    {
        if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER)) { return; }
        foreach ($matches as $match) {
            $selectorText = trim($match[1]);
            if ($selectorText === '') { continue; }
            if (str_starts_with($selectorText, '@')) {
                $unsupported[] = array('file' => $file, 'viewport' => $viewport, 'selector' => $selectorText, 'property' => $selectorText, 'value' => '', 'reason' => 'At-rule mapping is not implemented; local font registration requires a future audited font pipeline.');
                continue;
            }
            $declarations = array();
            foreach (explode(';', $match[2]) as $declaration) {
                if (!str_contains($declaration, ':')) { continue; }
                [$property, $value] = array_map('trim', explode(':', $declaration, 2));
                $property = strtolower($property);
                if ($property === '' || $value === '') { continue; }
                if (str_starts_with($property, '--')) { $variables[$property] = $value; continue; }
                if ($property === 'font' && preg_match('/(?:(\d{3})\s+)?([0-9.]+(?:px|rem|em))(?:\/([0-9.]+))?\s+(.+)/i', $value, $font)) {
                    if ($font[1] !== '') { $declarations['font-weight'] = $font[1]; }
                    $declarations['font-size'] = $font[2];
                    if (!empty($font[3])) { $declarations['line-height'] = $font[3]; }
                    $declarations['font-family'] = $this->resolveVariables($font[4], $variables);
                    continue;
                }
                if (!in_array($property, self::SUPPORTED_PROPERTIES, true)) {
                    foreach (explode(',', $selectorText) as $selector) { $unsupported[] = array('file' => $file, 'viewport' => $viewport, 'selector' => trim($selector), 'property' => $property, 'value' => $value, 'reason' => 'Property is outside the KIC mapping subset.'); }
                    continue;
                }
                $resolvedValue = $this->resolveVariables($value, $variables);
                if ($property === 'background' && !preg_match('/url\(|gradient\(/i', $resolvedValue)) { $declarations['background-color'] = $resolvedValue; continue; }
                $declarations[$property] = $resolvedValue;
            }
            foreach (explode(',', $selectorText) as $selector) {
                $selector = trim($selector);
                if ($selector === '') { continue; }
                if (preg_match('/::?|\[|\+|~|>/', $selector)) {
                    $unsupported[] = array('file' => $file, 'viewport' => $viewport, 'selector' => $selector, 'property' => '*', 'value' => '', 'reason' => 'Complex or state selector requires fallback.');
                }
                $rules[$viewport][] = array('selector' => $selector, 'declarations' => $declarations, 'order' => $order++);
            }
        }
    }

    /** @param array<string,string> $variables */
    private function resolveVariables(string $value, array $variables): string
    {
        for ($i = 0; $i < 5 && preg_match('/var\(\s*(--[a-z0-9-]+)(?:\s*,\s*([^\)]+))?\)/i', $value, $match); $i++) {
            $replacement = $variables[$match[1]] ?? ($match[2] ?? $match[0]);
            $value = str_replace($match[0], $replacement, $value);
        }
        return trim($value);
    }

    private function viewportForQuery(string $query): ?string
    {
        if (preg_match('/max-width\s*:\s*(?:767px|47\.9375em)/i', $query)) { return 'mobile'; }
        if (preg_match('/max-width\s*:\s*1199px/i', $query) || (preg_match('/min-width\s*:\s*768px/i', $query) && preg_match('/max-width\s*:\s*1199px/i', $query))) { return 'tablet'; }
        return null;
    }
}
