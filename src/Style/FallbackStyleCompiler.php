<?php

namespace KIC\Importer\Style;

final class FallbackStyleCompiler
{
    /** @param array<int,array<string,mixed>> $fallbacks */
    public function compile(array $fallbacks, string $siteScope = ''): string
    {
        $grouped = array();
        foreach ($fallbacks as $fallback) {
            if (!in_array($fallback['property'] ?? '', CssParser::SUPPORTED_PROPERTIES, true)) { continue; }
            $viewport = (string) ($fallback['viewport'] ?? 'desktop');
            $id = preg_replace('/[^a-z0-9-]/', '', (string) ($fallback['component_id'] ?? ''));
            if ($id === '') { continue; }
            $grouped[$viewport][$id][(string) $fallback['property']] = (string) $fallback['value'];
        }
        $css = '';
        foreach (($grouped['desktop'] ?? array()) as $id => $declarations) { $css .= $this->rule($id, $declarations, $siteScope); }
        if (!empty($grouped['tablet'])) {
            $css .= '@media (min-width:768px) and (max-width:1199px){';
            foreach ($grouped['tablet'] as $id => $declarations) { $css .= $this->rule($id, $declarations, $siteScope); }
            $css .= '}';
        }
        if (!empty($grouped['mobile'])) {
            $css .= '@media (max-width:767px){';
            foreach ($grouped['mobile'] as $id => $declarations) { $css .= $this->rule($id, $declarations, $siteScope); }
            $css .= '}';
        }
        return $css;
    }

    /** @param array<string,string> $declarations */
    private function rule(string $id, array $declarations, string $siteScope): string
    {
        $body = '';
        foreach ($declarations as $property => $value) {
            if (!preg_match('/^[a-z-]+$/', $property) || preg_match('/[{};<>]/', $value)) { continue; }
            $body .= $property . ':' . $value . ';';
        }
        $targets = '[data-kic-style-id="' . $id . '"],[data-kic-component-id="' . $id . '"],.kic-style-' . $id . ',.kb-row-layout-id' . $id . ',.kadence-column' . $id . ',.kb-image' . $id . ',.kb-btn' . $id;
        if ($siteScope === '') { return $targets . '{' . $body . '}'; }
        $scope = '.' . preg_replace('/[^a-z0-9_-]/i', '', $siteScope);
        $scoped = array();
        foreach (explode(',', $targets) as $target) { $scoped[] = $scope . $target; $scoped[] = $scope . ' ' . $target; }
        return implode(',', $scoped) . '{' . $body . '}';
    }
}
