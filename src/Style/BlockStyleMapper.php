<?php

namespace KIC\Importer\Style;

final class BlockStyleMapper
{
    /** @var array<int,array<string,mixed>> */
    private array $fallbacks = array();
    /** @var array<int,array<string,mixed>> */
    private array $nativeMappings = array();

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function groupAttributes(array $styles, string $componentId): array
    {
        $desktop = $styles['desktop'] ?? array();
        $attributes = array('metadata' => array('name' => $componentId));
        $style = $this->coreStyle($desktop);
        if ($style) { $attributes['style'] = $style; }
        $layout = $this->layout($desktop);
        if ($layout) { $attributes['layout'] = $layout; }
        $this->captureResponsiveFallbacks($componentId, $styles, array('display', 'flex-direction', 'flex-wrap', 'justify-content', 'color', 'background-color', 'font-size', 'line-height', 'font-weight', 'letter-spacing', 'text-transform', 'padding', 'margin', 'border-radius', 'border-color', 'border-width', 'border-style'), false);
        return $attributes;
    }

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function headingAttributes(array $styles, int $level, string $uniqueId): array
    {
        $desktop = $styles['desktop'] ?? array();
        $tablet = $styles['tablet'] ?? array();
        $mobile = $styles['mobile'] ?? array();
        $attributes = array('level' => $level, 'uniqueID' => $uniqueId, 'htmlTag' => 'h' . $level, 'loadGoogleFont' => false);
        $map = array('color' => 'color', 'font-family' => 'typography', 'font-weight' => 'fontWeight', 'text-align' => 'align', 'text-transform' => 'textTransform');
        foreach ($map as $property => $attribute) { if (isset($desktop[$property])) { $attributes[$attribute] = trim($desktop[$property], "\"'"); } }
        $this->responsiveNumber($attributes, $desktop, $tablet, $mobile, 'font-size', array('size', 'tabSize', 'mobileSize'), 'sizeType');
        $this->responsiveNumber($attributes, $desktop, $tablet, $mobile, 'line-height', array('lineHeight', 'tabLineHeight', 'mobileLineHeight'), 'lineType');
        $this->responsiveNumber($attributes, $desktop, $tablet, $mobile, 'letter-spacing', array('letterSpacing', 'tabletLetterSpacing', 'mobileLetterSpacing'), 'letterSpacingType');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'padding', array('padding', 'tabletPadding', 'mobilePadding'), 'paddingType');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'margin', array('margin', 'tabletMargin', 'mobileMargin'), 'marginType');
        $handled = array_merge(array_keys($map), array('font-size', 'line-height', 'letter-spacing', 'padding', 'margin'));
        $this->captureResponsiveFallbacks($uniqueId, $styles, $handled);
        return $attributes;
    }

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function buttonAttributes(array $styles, string $uniqueId): array
    {
        $desktop = $styles['desktop'] ?? array();
        $attributes = array('uniqueID' => $uniqueId);
        foreach (array('color' => 'color', 'background-color' => 'background', 'font-weight' => 'fontWeight', 'text-transform' => 'textTransform') as $property => $attribute) {
            if (isset($desktop[$property])) { $attributes[$attribute] = $desktop[$property]; }
        }
        $this->responsiveBox($attributes, $desktop, $styles['tablet'] ?? array(), $styles['mobile'] ?? array(), 'padding', array('padding', 'tabletPadding', 'mobilePadding'), 'paddingType');
        if (isset($desktop['border-radius'])) { $attributes['borderRadius'] = $this->box($desktop['border-radius']); $attributes['borderRadiusUnit'] = $this->unit($desktop['border-radius']); }
        $this->captureResponsiveFallbacks($uniqueId, $styles, array('color', 'background-color', 'font-weight', 'text-transform', 'padding', 'border-radius'));
        return $attributes;
    }

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function rowAttributes(array $styles, string $uniqueId, int $columns = 1): array
    {
        $desktop = $styles['desktop'] ?? array(); $tablet = $styles['tablet'] ?? array(); $mobile = $styles['mobile'] ?? array();
        $attributes = array('uniqueID' => $uniqueId, 'columns' => $columns, 'colLayout' => 'equal', 'tabletLayout' => $columns >= 3 ? 'two-grid' : 'inherit', 'mobileLayout' => 'row', 'kbVersion' => 2);
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'padding', array('padding', 'tabletPadding', 'mobilePadding'), 'paddingUnit');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'margin', array('margin', 'tabletMargin', 'mobileMargin'), 'marginUnit');
        if (isset($desktop['background-color']) || isset($desktop['background'])) { $attributes['bgColor'] = $this->backgroundColor($desktop); }
        if (isset($desktop['color'])) { $attributes['textColor'] = $desktop['color']; }
        if (isset($desktop['gap']) && preg_match('/[0-9.]+/', $desktop['gap'], $gap)) { $attributes['columnGutter'] = 'custom'; $attributes['customGutter'] = array((float) $gap[0], '', ''); $attributes['gutterType'] = $this->unit($desktop['gap']); }
        if (isset($desktop['max-width']) && preg_match('/[0-9.]+/', $desktop['max-width'], $width)) { $attributes['inheritMaxWidth'] = false; $attributes['maxWidth'] = (float) $width[0]; $attributes['maxWidthUnit'] = $this->unit($desktop['max-width']); }
        $this->applyBorderAndShadow($attributes, $desktop, $tablet, $mobile);
        $this->captureResponsiveFallbacks($uniqueId, $styles, array('display', 'grid-template-columns', 'max-width', 'padding', 'margin', 'background', 'background-color', 'color', 'gap', 'border', 'border-width', 'border-style', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-radius', 'box-shadow'), true);
        return $attributes;
    }

    /** @param array<string,array<string,string>> $listStyles @param array<string,array<string,string>> $titleStyles @return array<string,mixed> */
    public function accordionAttributes(array $listStyles, array $titleStyles, string $uniqueId, int $paneCount, int $openPane, bool $startCollapsed): array
    {
        $list = $listStyles['desktop'] ?? array(); $title = $titleStyles['desktop'] ?? array();
        $attributes = array('uniqueID' => $uniqueId, 'paneCount' => $paneCount, 'openPane' => $openPane, 'startCollapsed' => $startCollapsed, 'linkPaneCollapse' => true, 'faqSchema' => true, 'titleAlignment' => $title['text-align'] ?? 'left');
        if (isset($list['max-width']) && preg_match('/[0-9.]+/', $list['max-width'], $number)) { $attributes['maxWidth'] = (float) $number[0]; }
        if (isset($list['gap']) && preg_match('/[0-9.]+/', $list['gap'], $number)) { $attributes['columnGap'] = array((float) $number[0], '', ''); $attributes['columnGapUnit'] = $this->unit($list['gap']); }
        $attributes['titleStyles'] = array(array('color' => $title['color'] ?? '', 'background' => $this->backgroundColor($title), 'weight' => $title['font-weight'] ?? '', 'size' => array($this->number($title['font-size'] ?? ''), '', ''), 'sizeType' => $this->unit($title['font-size'] ?? 'px'), 'lineHeight' => array($this->number($title['line-height'] ?? ''), '', ''), 'lineType' => $this->unit($title['line-height'] ?? 'px'), 'padding' => isset($title['padding']) ? $this->box($title['padding']) : array('', '', '', ''), 'paddingType' => $this->unit($title['padding'] ?? 'px')));
        if (isset($title['border-radius'])) { $attributes['titleBorderRadius'] = $this->box($title['border-radius']); $attributes['titleBorderRadiusUnit'] = $this->unit($title['border-radius']); }
        if (isset($list['background-color'])) { $attributes['contentBgColor'] = $list['background-color']; }
        $this->captureResponsiveFallbacks($uniqueId, $listStyles, array('display', 'gap', 'max-width', 'width'), false);
        return $attributes;
    }

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function columnAttributes(array $styles, string $uniqueId): array
    {
        $desktop = $styles['desktop'] ?? array(); $tablet = $styles['tablet'] ?? array(); $mobile = $styles['mobile'] ?? array();
        $attributes = array('uniqueID' => $uniqueId);
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'padding', array('padding', 'tabletPadding', 'mobilePadding'), 'paddingType');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'margin', array('margin', 'tabletMargin', 'mobileMargin'), 'marginType');
        if (isset($desktop['background-color']) || isset($desktop['background'])) { $attributes['background'] = $this->backgroundColor($desktop); }
        if (isset($desktop['color'])) { $attributes['textColor'] = $desktop['color']; }
        $attributes['direction'] = array($this->direction($desktop), $this->direction($tablet), $this->direction($mobile));
        $attributes['textAlign'] = array($desktop['text-align'] ?? '', $tablet['text-align'] ?? '', $mobile['text-align'] ?? '');
        $attributes['justifyContent'] = array($desktop['justify-content'] ?? '', $tablet['justify-content'] ?? '', $mobile['justify-content'] ?? '');
        $attributes['verticalAlignment'] = $this->verticalAlignment($desktop['align-items'] ?? '');
        $attributes['verticalAlignmentTablet'] = $this->verticalAlignment($tablet['align-items'] ?? '');
        $attributes['verticalAlignmentMobile'] = $this->verticalAlignment($mobile['align-items'] ?? '');
        foreach (array($desktop, $tablet, $mobile) as $index => $viewport) {
            if (isset($viewport['gap']) && preg_match('/[0-9.]+/', $viewport['gap'], $number)) { $attributes['rowGap'][$index] = (float) $number[0]; $attributes['rowGapUnit'] = $this->unit($viewport['gap']); }
            $maxWidth = $viewport['max-width'] ?? '';
            if ($maxWidth === '' && isset($viewport['width']) && preg_match('/min\([^,]+,\s*([0-9.]+(?:px|rem|em|%))\s*\)/i', $viewport['width'], $widthMatch)) { $maxWidth = $widthMatch[1]; }
            if ($maxWidth !== '' && preg_match('/[0-9.]+/', $maxWidth, $number)) { $attributes['maxWidth'][$index] = (float) $number[0]; if ($index === 0) { $attributes['maxWidthUnit'] = $this->unit($maxWidth); } elseif ($index === 1) { $attributes['maxWidthTabletUnit'] = $this->unit($maxWidth); } else { $attributes['maxWidthMobileUnit'] = $this->unit($maxWidth); } }
        }
        foreach (array($desktop, $tablet, $mobile) as $index => $viewport) { if (isset($viewport['min-height']) && preg_match('/[0-9.]+/', $viewport['min-height'], $number)) { $attributes['height'][$index] = (float) $number[0]; $attributes['heightUnit'] = $this->unit($viewport['min-height']); } }
        $this->applyBorderAndShadow($attributes, $desktop, $tablet, $mobile);
        $this->captureResponsiveFallbacks($uniqueId, $styles, array('padding', 'margin', 'background', 'background-color', 'color', 'display', 'flex-direction', 'flex-wrap', 'align-items', 'justify-content', 'text-align', 'min-height', 'max-width', 'width', 'gap', 'border', 'border-width', 'border-style', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-radius', 'box-shadow'), true);
        return $attributes;
    }

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function imageAttributes(array $styles, string $uniqueId, string $url, string $alt, int $width, int $height): array
    {
        $desktop = $styles['desktop'] ?? array(); $tablet = $styles['tablet'] ?? array(); $mobile = $styles['mobile'] ?? array();
        $attributes = array('uniqueID' => $uniqueId, 'url' => $url, 'alt' => $alt, 'width' => $width, 'height' => $height, 'sizeSlug' => 'full');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'margin', array('marginDesktop', 'marginTablet', 'marginMobile'), 'marginUnit');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'padding', array('paddingDesktop', 'paddingTablet', 'paddingMobile'), 'paddingUnit');
        if (isset($desktop['border-radius'])) { $attributes['borderRadius'] = $this->box($desktop['border-radius']); $attributes['borderRadiusUnit'] = $this->unit($desktop['border-radius']); }
        if (isset($desktop['box-shadow'])) { $attributes['displayBoxShadow'] = true; $attributes['boxShadow'] = array($this->shadow($desktop['box-shadow'])); }
        foreach (array($desktop, $tablet, $mobile) as $index => $viewport) { if (isset($viewport['max-width']) && preg_match('/[0-9.]+/', $viewport['max-width'], $number)) { $attributes[array('imgMaxWidth','imgMaxWidthTablet','imgMaxWidthMobile')[$index]] = (float) $number[0]; } }
        $this->captureResponsiveFallbacks($uniqueId, $styles, array('margin', 'padding', 'border-radius', 'box-shadow', 'max-width', 'width', 'height', 'display', 'object-fit', 'object-position'), true);
        return $attributes;
    }

    /** @param array<string,array<string,string>> $styles @return array<string,mixed> */
    public function singleButtonAttributes(array $styles, string $uniqueId, string $text, string $link): array
    {
        $desktop = $styles['desktop'] ?? array(); $tablet = $styles['tablet'] ?? array(); $mobile = $styles['mobile'] ?? array();
        $attributes = array('uniqueID' => $uniqueId, 'text' => $text, 'link' => $link);
        if (isset($desktop['color'])) { $attributes['color'] = $desktop['color']; }
        if (isset($desktop['background-color']) || isset($desktop['background'])) { $attributes['background'] = $this->backgroundColor($desktop); }
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'padding', array('padding', 'tabletPadding', 'mobilePadding'), 'paddingUnit');
        $this->responsiveBox($attributes, $desktop, $tablet, $mobile, 'margin', array('margin', 'tabletMargin', 'mobileMargin'), 'marginUnit');
        if (isset($desktop['border-radius'])) { $attributes['borderRadius'] = $this->box($desktop['border-radius']); $attributes['borderRadiusUnit'] = $this->unit($desktop['border-radius']); }
        if (isset($desktop['box-shadow'])) { $attributes['displayShadow'] = true; $attributes['shadow'] = array($this->shadow($desktop['box-shadow'])); }
        $attributes['typography'] = array(array('size' => array($this->number($desktop['font-size'] ?? ''), $this->number($tablet['font-size'] ?? ''), $this->number($mobile['font-size'] ?? '')), 'sizeType' => $this->unit($desktop['font-size'] ?? 'px'), 'lineHeight' => array($this->number($desktop['line-height'] ?? ''), $this->number($tablet['line-height'] ?? ''), $this->number($mobile['line-height'] ?? '')), 'lineType' => $this->unit($desktop['line-height'] ?? 'px'), 'letterSpacing' => array($this->number($desktop['letter-spacing'] ?? ''), $this->number($tablet['letter-spacing'] ?? ''), $this->number($mobile['letter-spacing'] ?? '')), 'letterType' => $this->unit($desktop['letter-spacing'] ?? 'px'), 'textTransform' => $desktop['text-transform'] ?? '', 'family' => trim($desktop['font-family'] ?? '', "\"'"), 'weight' => $desktop['font-weight'] ?? ''));
        $this->captureResponsiveFallbacks($uniqueId, $styles, array('color', 'background', 'background-color', 'padding', 'margin', 'border-radius', 'box-shadow', 'font-size', 'line-height', 'letter-spacing', 'text-transform', 'font-family', 'font-weight', 'display', 'align-items', 'justify-content', 'min-height'), true);
        return $attributes;
    }

    /** @return array<int,array<string,mixed>> */
    public function fallbacks(): array { return $this->fallbacks; }

    /** @return array<int,array<string,mixed>> */
    public function nativeMappings(): array { return $this->nativeMappings; }

    /** @param array<string,string> $styles @return array<string,mixed> */
    private function coreStyle(array $styles): array
    {
        $result = array();
        foreach (array('color' => array('color', 'text'), 'background-color' => array('color', 'background'), 'font-size' => array('typography', 'fontSize'), 'line-height' => array('typography', 'lineHeight'), 'font-weight' => array('typography', 'fontWeight'), 'letter-spacing' => array('typography', 'letterSpacing'), 'text-transform' => array('typography', 'textTransform')) as $property => $path) {
            if (isset($styles[$property])) { $result[$path[0]][$path[1]] = $styles[$property]; }
        }
        foreach (array('padding', 'margin') as $property) {
            if (isset($styles[$property])) {
                [$top, $right, $bottom, $left] = $this->boxWithUnits($styles[$property]);
                $result['spacing'][$property] = array('top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left);
            }
        }
        if (isset($styles['border-radius'])) { $result['border']['radius'] = $styles['border-radius']; }
        if (isset($styles['border-color'])) { $result['border']['color'] = $styles['border-color']; }
        if (isset($styles['border-width'])) { $result['border']['width'] = $styles['border-width']; }
        if (isset($styles['border-style'])) { $result['border']['style'] = $styles['border-style']; }
        return $result;
    }

    /** @param array<string,string> $styles @return array<string,mixed> */
    private function layout(array $styles): array
    {
        if (($styles['display'] ?? '') === 'flex') {
            return array('type' => 'flex', 'orientation' => ($styles['flex-direction'] ?? 'row') === 'column' ? 'vertical' : 'horizontal', 'justifyContent' => $this->justify($styles['justify-content'] ?? ''), 'flexWrap' => ($styles['flex-wrap'] ?? '') === 'wrap' ? 'wrap' : 'nowrap');
        }
        return array();
    }

    private function justify(string $value): string
    {
        return array('flex-start' => 'left', 'center' => 'center', 'flex-end' => 'right', 'space-between' => 'space-between')[$value] ?? 'left';
    }

    /** @param array<string,mixed> $attributes @param array<string,string> $desktop @param array<string,string> $tablet @param array<string,string> $mobile @param array<int,string> $names */
    private function responsiveNumber(array &$attributes, array $desktop, array $tablet, array $mobile, string $property, array $names, string $unitName): void
    {
        foreach (array($desktop, $tablet, $mobile) as $index => $styles) { if (isset($styles[$property]) && preg_match('/-?[0-9.]+/', $styles[$property], $number)) { $attributes[$names[$index]] = (float) $number[0]; } }
        if (isset($desktop[$property])) { $attributes[$unitName] = $property === 'line-height' && !preg_match('/[a-z%]/i', $desktop[$property]) ? '' : $this->unit($desktop[$property]); }
    }

    /** @param array<string,mixed> $attributes @param array<string,string> $desktop @param array<string,string> $tablet @param array<string,string> $mobile @param array<int,string> $names */
    private function responsiveBox(array &$attributes, array $desktop, array $tablet, array $mobile, string $property, array $names, string $unitName): void
    {
        foreach (array($desktop, $tablet, $mobile) as $index => $styles) { if (isset($styles[$property])) { $attributes[$names[$index]] = $this->box($styles[$property]); } }
        if (isset($desktop[$property])) { $attributes[$unitName] = $this->unit($desktop[$property]); }
    }

    /** @return array<int,float|string> */
    private function box(string $value): array
    {
        return array_map(static function (string $part) { return preg_match('/^-?[0-9.]+/', $part, $number) ? (float) $number[0] : ''; }, $this->expandBox($value));
    }

    /** @return array<int,string> */
    private function boxWithUnits(string $value): array { return $this->expandBox($value); }

    /** @return array<int,string> */
    private function expandBox(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value)) ?: array('0');
        if (count($parts) === 1) { return array($parts[0], $parts[0], $parts[0], $parts[0]); }
        if (count($parts) === 2) { return array($parts[0], $parts[1], $parts[0], $parts[1]); }
        if (count($parts) === 3) { return array($parts[0], $parts[1], $parts[2], $parts[1]); }
        return array_slice(array_pad($parts, 4, '0'), 0, 4);
    }

    private function unit(string $value): string { return preg_match('/(px|em|rem|%|vw|vh)/i', $value, $unit) ? strtolower($unit[1]) : 'px'; }

    /** @param array<string,array<string,string>> $styles @param array<int,string> $handled */
    private function captureResponsiveFallbacks(string $id, array $styles, array $handled, bool $responsiveHandled = true): void
    {
        foreach (array('desktop', 'tablet', 'mobile') as $viewport) {
            foreach (($styles[$viewport] ?? array()) as $property => $value) {
                if ($viewport === 'desktop' && in_array($property, $handled, true)) { $this->recordNative($id, $viewport, $property, $value); continue; }
                if ($viewport === 'tablet' && isset($styles['desktop'][$property]) && $styles['desktop'][$property] === $value) { continue; }
                if ($viewport === 'mobile' && ((isset($styles['tablet'][$property]) && $styles['tablet'][$property] === $value) || (isset($styles['desktop'][$property]) && $styles['desktop'][$property] === $value))) { continue; }
                if ($viewport !== 'desktop' && $responsiveHandled && in_array($property, $handled, true)) { $this->recordNative($id, $viewport, $property, $value); continue; }
                $this->fallbacks[] = array('component_id' => $id, 'viewport' => $viewport, 'property' => $property, 'value' => $value, 'reason' => 'No native attribute mapping is implemented for this block/property pair.');
            }
        }
    }

    private function recordNative(string $id, string $viewport, string $property, string $value): void
    {
        $key = $id . '|' . $viewport . '|' . $property . '|' . $value;
        $this->nativeMappings[$key] = array('component_id' => $id, 'viewport' => $viewport, 'property' => $property, 'value' => $value, 'target' => 'native Gutenberg/Kadence block attribute');
    }

    /** @param array<string,mixed> $attributes @param array<string,string> $desktop @param array<string,string> $tablet @param array<string,string> $mobile */
    private function applyBorderAndShadow(array &$attributes, array $desktop, array $tablet, array $mobile): void
    {
        foreach (array($desktop, $tablet, $mobile) as $index => $viewport) {
            if (isset($viewport['border-radius'])) { $attributes[array('borderRadius','tabletBorderRadius','mobileBorderRadius')[$index]] = $this->box($viewport['border-radius']); $attributes['borderRadiusUnit'] = $this->unit($viewport['border-radius']); }
            $border = $this->border($viewport);
            if ($border) { $attributes[array('borderStyle','tabletBorderStyle','mobileBorderStyle')[$index]] = array($border); }
        }
        if (isset($desktop['box-shadow'])) { $attributes['displayShadow'] = true; $attributes['shadow'] = array($this->shadow($desktop['box-shadow'])); }
    }

    /** @param array<string,string> $styles */
    private function border(array $styles): ?array
    {
        $width = $styles['border-width'] ?? ''; $style = $styles['border-style'] ?? ''; $color = $styles['border-color'] ?? '';
        if (isset($styles['border']) && preg_match('/([0-9.]+)(px|em|rem)?\s+(solid|dashed|dotted|double)\s+(.+)/i', $styles['border'], $match)) { $width = $match[1]; $style = strtolower($match[3]); $color = trim($match[4]); }
        $hasSide = isset($styles['border-top']) || isset($styles['border-right']) || isset($styles['border-bottom']) || isset($styles['border-left']);
        if ($width === '' && $style === '' && $color === '' && !$hasSide) { return null; }
        $side = ($width === '' && $style === '' && $color === '') ? array('', '', '') : array($this->number($width), $style ?: 'solid', $color);
        $result = array('top' => $side, 'right' => $side, 'bottom' => $side, 'left' => $side, 'unit' => $this->unit($width ?: 'px'));
        foreach (array('top', 'right', 'bottom', 'left') as $name) {
            if (isset($styles['border-' . $name]) && preg_match('/([0-9.]+)(px|em|rem)?\s+(solid|dashed|dotted|double)\s+(.+)/i', $styles['border-' . $name], $match)) { $result[$name] = array((float) $match[1], strtolower($match[3]), trim($match[4])); $result['unit'] = $match[2] ?: 'px'; }
        }
        return $result;
    }

    /** @return array<string,float|string|bool> */
    private function shadow(string $value): array
    {
        $numbers = array(); preg_match_all('/-?[0-9.]+(?:px)?/', $value, $matches); foreach ($matches[0] as $part) { $numbers[] = (float) $part; }
        $color = '#000000'; $opacity = 0.2;
        if (preg_match('/rgba?\(([^\)]+)\)/i', $value, $rgba)) { $parts = array_map('trim', explode(',', $rgba[1])); $color = sprintf('#%02x%02x%02x', (int) $parts[0], (int) $parts[1], (int) $parts[2]); if (isset($parts[3])) { $opacity = (float) $parts[3]; } }
        elseif (preg_match('/#[0-9a-f]{3,8}/i', $value, $hex)) { $color = $hex[0]; }
        return array('color' => $color, 'opacity' => $opacity, 'hOffset' => $numbers[0] ?? 0, 'vOffset' => $numbers[1] ?? 0, 'blur' => $numbers[2] ?? 0, 'spread' => $numbers[3] ?? 0, 'inset' => stripos($value, 'inset') !== false);
    }

    /** @param array<string,string> $styles */
    private function backgroundColor(array $styles): string
    {
        $value = $styles['background-color'] ?? ($styles['background'] ?? '');
        return trim($value);
    }

    /** @param array<string,string> $styles */
    private function direction(array $styles): string { return ($styles['display'] ?? '') === 'flex' ? ($styles['flex-direction'] ?? 'row') : ''; }
    private function verticalAlignment(string $value): string { return array('flex-start' => 'top', 'center' => 'middle', 'flex-end' => 'bottom', 'stretch' => 'stretch')[$value] ?? ''; }
    private function number(string $value) { return preg_match('/-?[0-9.]+/', $value, $number) ? (float) $number[0] : ''; }
}
