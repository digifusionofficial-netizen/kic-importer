<?php

namespace KIC\Importer\Import;

use RuntimeException;

final class AssetUrlRewriter
{
    /** @param array<string,string> $urls */
    public function rewrite(string $content, string $sourceFile, array $urls, string $componentId = ''): string
    {
        $urls = $this->normalizeMap($urls);
        $content = preg_replace_callback('~<(img|source)\b[^>]*>~i', function (array $tag) use ($sourceFile, $urls, $componentId): string {
            return preg_replace_callback('~\b(src|srcset)\s*=\s*(["\'])(.*?)\2~is', function (array $attribute) use ($sourceFile, $urls, $componentId): string {
                $rewritten = strtolower($attribute[1]) === 'srcset' ? $this->rewriteSrcset($attribute[3], $sourceFile, $urls, $componentId, 'html.' . $attribute[1]) : $this->rewriteOne($attribute[3], $sourceFile, $urls, $componentId, 'html.' . $attribute[1]);
                return $attribute[1] . '=' . $attribute[2] . $rewritten . $attribute[2];
            }, $tag[0]) ?? $tag[0];
        }, $content) ?? $content;
        $content = preg_replace_callback('~<meta\b[^>]*\bproperty\s*=\s*(["\'])og:image\1[^>]*>~i', function (array $tag) use ($sourceFile, $urls, $componentId): string {
            return preg_replace_callback('~\bcontent\s*=\s*(["\'])(.*?)\1~is', function (array $attribute) use ($sourceFile, $urls, $componentId): string {
                return 'content=' . $attribute[1] . $this->rewriteOne($attribute[2], $sourceFile, $urls, $componentId, 'html.meta[og:image]') . $attribute[1];
            }, $tag[0]) ?? $tag[0];
        }, $content) ?? $content;
        $content = preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~i', function (array $match) use ($sourceFile, $urls, $componentId): string {
            return 'url(' . $match[1] . $this->rewriteOne($match[2], $sourceFile, $urls, $componentId, 'css.url') . $match[1] . ')';
        }, $content) ?? $content;
        $content = preg_replace_callback('~("(?:url|src|srcSet|og_image)"\s*:\s*")(.*?)(")~i', function (array $match) use ($sourceFile, $urls, $componentId): string {
            $original = json_decode('"' . $match[2] . '"', true);
            if (!is_string($original)) { $original = str_replace('\\/', '/', $match[2]); }
            $isSrcset = (bool) preg_match('/"srcSet"/i', $match[1]);
            $rewritten = $isSrcset ? $this->rewriteSrcset($original, $sourceFile, $urls, $componentId, 'block.srcSet') : $this->rewriteOne($original, $sourceFile, $urls, $componentId, 'block.attribute');
            $encoded = wp_json_encode($rewritten, JSON_UNESCAPED_UNICODE);
            return $match[1] . substr((string) $encoded, 1, -1) . $match[3];
        }, $content) ?? $content;
        return $content;
    }

    /** @param array<string,string> $urls */
    public function assertNoLocalAssetPaths(string $content, string $sourceFile, array $urls, string $componentId = ''): void
    {
        $urls = $this->normalizeMap($urls); $candidates = array();
        preg_match_all('~<(img|source)\b[^>]*>~i', $content, $tags);
        foreach ($tags[0] as $tag) {
            preg_match_all('~\b(src|srcset)\s*=\s*(["\'])(.*?)\2~is', $tag, $attributes, PREG_SET_ORDER);
            foreach ($attributes as $attribute) {
                if (strtolower($attribute[1]) === 'srcset') { foreach ($this->srcsetUrls($attribute[3]) as $url) { $candidates[] = array('location' => 'html.srcset', 'url' => $url); } }
                else { $candidates[] = array('location' => 'html.src', 'url' => $attribute[3]); }
            }
        }
        preg_match_all('~url\(\s*(["\']?)(.*?)\1\s*\)~i', $content, $css, PREG_SET_ORDER);
        foreach ($css as $value) { $candidates[] = array('location' => 'css.url', 'url' => $value[2]); }
        preg_match_all('~"(url|src|srcSet|og_image)"\s*:\s*"(.*?)"~i', $content, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $value) {
            $decoded = json_decode('"' . $value[2] . '"', true); $decoded = is_string($decoded) ? $decoded : str_replace('\\/', '/', $value[2]);
            if (strtolower($value[1]) === 'srcset') { foreach ($this->srcsetUrls($decoded) as $url) { $candidates[] = array('location' => 'block.srcSet', 'url' => $url); } }
            else { $candidates[] = array('location' => 'block.' . $value[1], 'url' => $decoded); }
        }
        foreach ($candidates as $candidate) {
            $url = html_entity_decode((string) $candidate['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('~https?:/https?:~i', $url)) { $this->fail($sourceFile, $componentId, $candidate['location'], $url, '', $url, 'Malformed absolute URL.'); }
            if ($this->isRemote($url) || str_starts_with($url, 'data:')) {
                continue;
            }
            try { $normalized = $this->normalizeReference($sourceFile, $url); }
            catch (RuntimeException $error) { $this->fail($sourceFile, $componentId, $candidate['location'], $url, '', $url, $error->getMessage()); }
            if ($this->isMediaPath($normalized)) {
                $reason = isset($urls[$normalized]) ? 'Known ZIP media path was not replaced.' : 'ZIP media path has no imported attachment mapping.';
                $this->fail($sourceFile, $componentId, $candidate['location'], $url, $normalized, $url, $reason);
            }
        }
    }

    public function normalizeReference(string $sourceFile, string $url): string
    {
        $decoded = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        for ($i = 0; $i < 3; $i++) { $next = rawurldecode($decoded); if ($next === $decoded) { break; } $decoded = $next; }
        $decoded = str_replace(array('\\', '\\/'), '/', $decoded);
        $path = preg_split('/[?#]/', $decoded, 2)[0] ?? '';
        if ($path === '' || $this->isRemote($path) || str_starts_with($path, 'data:')) { return $path; }
        $directory = $sourceFile === '' || dirname(str_replace('\\', '/', $sourceFile)) === '.' ? '' : dirname(str_replace('\\', '/', $sourceFile));
        $combined = str_starts_with($path, '/') ? ltrim($path, '/') : ($directory === '' ? $path : $directory . '/' . $path);
        $segments = array();
        foreach (explode('/', $combined) as $segment) {
            if ($segment === '' || $segment === '.') { continue; }
            if ($segment === '..') { if (!$segments) { throw new RuntimeException('Path traversal escapes the extracted ZIP root.'); } array_pop($segments); continue; }
            if (str_contains($segment, "\0")) { throw new RuntimeException('NUL byte is not allowed in a media path.'); }
            $segments[] = $segment;
        }
        return implode('/', $segments);
    }

    /** @param array<string,string> $urls */
    private function rewriteOne(string $original, string $sourceFile, array $urls, string $componentId, string $location): string
    {
        $decoded = html_entity_decode(trim($original), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('~https?:/https?:~i', $decoded)) { $this->fail($sourceFile, $componentId, $location, $original, '', $decoded, 'Malformed absolute URL.'); }
        if ($decoded === '' || $this->isRemote($decoded) || str_starts_with($decoded, 'data:') || str_starts_with($decoded, '#')) { return $original; }
        try { $normalized = $this->normalizeReference($sourceFile, $decoded); }
        catch (RuntimeException $error) { $this->fail($sourceFile, $componentId, $location, $original, '', '', $error->getMessage()); }
        return $urls[$normalized] ?? $original;
    }

    /** @param array<string,string> $urls */
    private function rewriteSrcset(string $srcset, string $sourceFile, array $urls, string $componentId, string $location): string
    {
        $rewritten = array(); foreach (preg_split('/\s*,\s*/', trim($srcset)) ?: array() as $item) { if (preg_match('/^(\S+)(\s+.*)?$/', $item, $parts)) { $rewritten[] = $this->rewriteOne($parts[1], $sourceFile, $urls, $componentId, $location) . ($parts[2] ?? ''); } else { $rewritten[] = $item; } } return implode(', ', $rewritten);
    }

    /** @return array<int,string> */
    private function srcsetUrls(string $srcset): array { $result = array(); foreach (preg_split('/\s*,\s*/', trim($srcset)) ?: array() as $item) { if (preg_match('/^(\S+)/', $item, $match)) { $result[] = $match[1]; } } return $result; }
    /** @param array<string,string> $urls @return array<string,string> */
    private function normalizeMap(array $urls): array { $result = array(); foreach ($urls as $path => $url) { $result[$this->normalizeReference('', (string) $path)] = (string) $url; } return $result; }
    private function isRemote(string $url): bool { return (bool) preg_match('~^(?:https?:)?//~i', $url); }
    private function isMediaPath(string $path): bool { return (bool) preg_match('~^assets/(?:images|icons|fonts)/~i', $path); }
    private function fail(string $page, string $component, string $location, string $original, string $normalized, string $rewritten, string $reason): void
    {
        throw new RuntimeException(sprintf('KIC media rewrite failed: page=%s; component=%s; location=%s; original=%s; normalized=%s; rewritten=%s; reason=%s', $page ?: '(global)', $component ?: '(unknown)', $location, $original, $normalized ?: '(unavailable)', $rewritten ?: '(none)', $reason));
    }
}
