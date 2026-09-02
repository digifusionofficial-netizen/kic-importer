<?php

namespace KIC\Importer\Style;

use DOMElement;

final class StyleResolver
{
    private Stylesheet $stylesheet;

    public function __construct(Stylesheet $stylesheet) { $this->stylesheet = $stylesheet; }

    /** @return array<string,array<string,string>> */
    public function resolve(DOMElement $element): array
    {
        $resolved = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach (array_keys($resolved) as $viewport) {
            $weighted = array();
            foreach ($this->stylesheet->rules($viewport) as $rule) {
                if (!$this->matches($element, $rule['selector'])) { continue; }
                $specificity = $this->specificity($rule['selector']);
                foreach ($rule['declarations'] as $property => $value) {
                    $weight = $specificity * 100000 + $rule['order'];
                    if (!isset($weighted[$property]) || $weight >= $weighted[$property]['weight']) { $weighted[$property] = array('weight' => $weight, 'value' => $value); }
                }
            }
            foreach ($weighted as $property => $item) { $resolved[$viewport][$property] = $item['value']; }
        }
        $resolved['tablet'] = array_merge($resolved['desktop'], $resolved['tablet']);
        $resolved['mobile'] = array_merge($resolved['desktop'], $resolved['tablet'], $resolved['mobile']);
        return $resolved;
    }

    private function matches(DOMElement $element, string $selector): bool
    {
        $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? '');
        if ($selector === ':root') { return strtolower($element->tagName) === 'html'; }
        if (preg_match('/[:\[\]+~>]/', $selector)) { return false; }
        $parts = explode(' ', $selector);
        $last = count($parts) - 1;
        if (!$this->matchesCompound($element, $parts[$last])) { return false; }
        $candidate = $element->parentNode instanceof DOMElement ? $element->parentNode : null;
        for ($index = $last - 1; $index >= 0; $index--) {
            while ($candidate && !$this->matchesCompound($candidate, $parts[$index])) { $candidate = $candidate->parentNode instanceof DOMElement ? $candidate->parentNode : null; }
            if (!$candidate) { return false; }
            $candidate = $candidate->parentNode instanceof DOMElement ? $candidate->parentNode : null;
        }
        return true;
    }

    private function matchesCompound(DOMElement $element, string $compound): bool
    {
        if ($compound === '*') { return true; }
        preg_match('/^[a-z][a-z0-9-]*/i', $compound, $tag);
        if ($tag && strtolower($element->tagName) !== strtolower($tag[0])) { return false; }
        if (preg_match('/#([a-z0-9_-]+)/i', $compound, $id) && $element->getAttribute('id') !== $id[1]) { return false; }
        preg_match_all('/\.([a-z0-9_-]+)/i', $compound, $classes);
        $actual = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: array();
        foreach ($classes[1] as $class) { if (!in_array($class, $actual, true)) { return false; } }
        return true;
    }

    private function specificity(string $selector): int
    {
        preg_match_all('/#[a-z0-9_-]+/i', $selector, $ids);
        preg_match_all('/\.[a-z0-9_-]+/i', $selector, $classes);
        preg_match_all('/(^|\s)[a-z][a-z0-9-]*/i', $selector, $tags);
        return count($ids[0]) * 100 + count($classes[0]) * 10 + count($tags[0]);
    }
}
