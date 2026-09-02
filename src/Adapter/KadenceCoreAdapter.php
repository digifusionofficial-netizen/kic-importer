<?php

namespace KIC\Importer\Adapter;

use DOMDocument;
use DOMElement;
use DOMNode;
use KIC\Importer\Schema\SiteSchema;
use KIC\Importer\Style\BlockStyleMapper;
use KIC\Importer\Style\StyleResolver;

/**
 * Stable adapter that emits valid editable core blocks while Kadence supplies the
 * theme/block environment. It deliberately avoids undocumented Kadence attributes.
 */
final class KadenceCoreAdapter implements AdapterInterface
{
    private ?StyleResolver $resolver = null;
    private BlockStyleMapper $mapper;
    private string $componentId = '';
    private int $elementIndex = 0;
    private string $siteScope = '';
    private float $containerWidth = 1200;
    /** @var array<string,string> */
    private array $placeholders = array();

    public function __construct() { $this->mapper = new BlockStyleMapper(); }

    public function name(): string { return 'kadence-native-v1'; }

    public function configure(string $siteScope, SiteSchema $schema, array $placeholders = array()): void
    {
        $this->siteScope = sanitize_html_class($siteScope);
        $this->containerWidth = (float) ($schema->manifest()['design']['layout']['container_width_px'] ?? 1200);
        $this->placeholders = $placeholders;
    }

    /** @return array<int,array<string,mixed>> */
    public function mappingFallbacks(): array
    {
        $unique = array();
        foreach ($this->mapper->fallbacks() as $item) {
            $key = implode('|', array($item['component_id'] ?? '', $item['viewport'] ?? '', $item['property'] ?? '', $item['value'] ?? ''));
            $unique[$key] = $item;
        }
        return array_values($unique);
    }

    /** @return array<int,array<string,mixed>> */
    public function nativeMappings(): array
    {
        return array_values($this->mapper->nativeMappings());
    }

    public function renderPage(array $page, SiteSchema $schema): string
    {
        $this->resolver = new StyleResolver($schema->stylesheet());
        $output = '';
        foreach ($page['sections'] as $section) {
            $this->componentId = (string) $section['component_id'];
            $this->elementIndex = 0;
            $html = $this->replacePlaceholders((string) $section['html']);
            $sectionStyles = $this->resolveFragmentRoot($html);
            $attributes = $this->mapper->rowAttributes($sectionStyles, $this->componentId, 1);
            $attributes['align'] = 'full';
            $attributes['className'] = trim($this->siteScope . ' alignfull kic-component kic-' . sanitize_html_class($section['component']) . ' ' . $this->classNamesFromString((string) ($section['classes'] ?? $this->fragmentRootClasses($html))));
            $output .= '<!-- wp:kadence/rowlayout ' . wp_json_encode($attributes) . ' -->' . "\n";
            $output .= $this->convertFragment($html);
            $output .= "<!-- /wp:kadence/rowlayout -->\n";
        }
        return $output;
    }

    public function renderGlobal(string $html, string $componentId, SiteSchema $schema): string
    {
        $this->resolver = new StyleResolver($schema->stylesheet());
        $this->componentId = $componentId;
        $this->elementIndex = 0;
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<!doctype html><html><body>' . $this->replacePlaceholders($html) . '</body></html>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $body = $dom->getElementsByTagName('body')->item(0);
        $root = $body ? $body->firstChild : null;
        while ($root && !$root instanceof DOMElement) { $root = $root->nextSibling; }
        if (!$root instanceof DOMElement) { return ''; }
        $styles = $this->resolver->resolve($root);
        $attributes = $this->mapper->rowAttributes($styles, $componentId, 1);
        $attributes['align'] = 'full';
        $attributes['className'] = trim($this->siteScope . ' alignfull kic-global kic-' . sanitize_html_class($componentId) . ' ' . $this->classNames($root));
        return '<!-- wp:kadence/rowlayout ' . wp_json_encode($attributes) . ' -->' . $this->kadenceColumn($componentId . '-inner', $this->mapper->columnAttributes($styles, $componentId . '-inner'), $this->convertChildren($root), 'kic-global-inner') . '<!-- /wp:kadence/rowlayout -->';
    }

    private function convertFragment(string $html): string
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $section = $dom->getElementsByTagName('section')->item(0);
        return $section ? $this->convertChildren($section) : '';
    }

    private function convertChildren(DOMNode $parent): string
    {
        $result = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $result .= $this->convertElement($node);
            }
        }
        return $result;
    }

    private function convertElement(DOMElement $element): string
    {
        $tag = strtolower($element->tagName);
        $innerText = trim($element->textContent);
        $styles = $this->resolver ? $this->resolver->resolve($element) : array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        $uniqueId = sanitize_html_class($element->getAttribute('data-component-id') ?: $this->componentId . '-' . (++$this->elementIndex));
        if (preg_match('/(^|\s)faq-list(\s|$)/', $element->getAttribute('class'))) {
            return $this->kadenceAccordion($element, $styles, $uniqueId);
        }
        if ($element->getAttribute('data-component') === 'faq-item') {
            $question = '';
            $answer = '';
            $questionId = $uniqueId . '-question';
            foreach ($element->childNodes as $child) {
                if (!$child instanceof DOMElement) { continue; }
                if (strtolower($child->tagName) === 'button') { $question = trim($child->textContent); if ($this->resolver) { $this->mapper->groupAttributes($this->resolver->resolve($child), $questionId); } } else { $answer .= $this->convertElement($child); }
            }
            $attributes = $this->mapper->groupAttributes($styles, $uniqueId);
            return '<!-- wp:details ' . wp_json_encode($attributes) . ' --><details class="wp-block-details" data-kic-style-id="' . esc_attr($uniqueId) . '"><summary class="faq-question kic-style-' . esc_attr($questionId) . '">' . esc_html($question) . '</summary>' . $answer . '</details><!-- /wp:details -->' . "\n";
        }
        if (preg_match('/^h([1-6])$/', $tag, $match)) {
            $level = (int) $match[1];
            $content = wp_kses_post($this->innerHtml($element));
            $attributes = $this->mapper->headingAttributes($styles, $level, $uniqueId);
            $attributes['content'] = $content;
            $sourceClasses = $this->classNames($element); if ($sourceClasses !== '') { $attributes['className'] = $sourceClasses; }
            return '<!-- wp:kadence/advancedheading ' . wp_json_encode($attributes) . ' --><h' . $level . ' class="kt-adv-heading-' . esc_attr($uniqueId) . ' wp-block-kadence-advancedheading ' . esc_attr($sourceClasses) . '" data-kb-block="kb-adv-heading-' . esc_attr($uniqueId) . '">' . $content . '</h' . $level . '><!-- /wp:kadence/advancedheading -->' . "\n";
        }
        if ($tag === 'p') {
            $attributes = $this->mapper->groupAttributes($styles, $uniqueId);
            unset($attributes['metadata'], $attributes['layout']);
            $sourceClasses = $this->classNames($element); if ($sourceClasses !== '') { $attributes['className'] = $sourceClasses; }
            return '<!-- wp:paragraph ' . wp_json_encode($attributes) . ' --><p class="' . esc_attr($sourceClasses) . '">' . wp_kses_post($this->innerHtml($element)) . '</p><!-- /wp:paragraph -->' . "\n";
        }
        if ($tag === 'img') {
            $attributes = $this->mapper->imageAttributes($styles, $uniqueId, $element->getAttribute('src'), $element->getAttribute('alt'), (int) $element->getAttribute('width'), (int) $element->getAttribute('height'));
            if ($element->hasAttribute('srcset')) { $attributes['srcSet'] = $element->getAttribute('srcset'); }
            $srcset = $element->hasAttribute('srcset') ? ' srcset="' . esc_attr($element->getAttribute('srcset')) . '"' : '';
            return '<!-- wp:kadence/image ' . wp_json_encode($attributes) . ' --><figure class="wp-block-kadence-image kb-image' . esc_attr($uniqueId) . ' size-full"><img src="' . esc_url($element->getAttribute('src')) . '"' . $srcset . ' alt="' . esc_attr($element->getAttribute('alt')) . '" class="kb-img" width="' . (int) $element->getAttribute('width') . '" height="' . (int) $element->getAttribute('height') . '"/></figure><!-- /wp:kadence/image -->' . "\n";
        }
        if ($tag === 'ul' || $tag === 'ol') {
            return '<!-- wp:list {"ordered":' . ($tag === 'ol' ? 'true' : 'false') . '} --><' . $tag . '>' . wp_kses_post($this->innerHtml($element)) . '</' . $tag . '><!-- /wp:list -->' . "\n";
        }
        if ($tag === 'a' && preg_match('/(^|\s)button(\s|$)/', $element->getAttribute('class'))) {
            $single = $this->mapper->singleButtonAttributes($styles, $uniqueId, $innerText, $element->getAttribute('href'));
            $wrapperId = $uniqueId . '-wrap';
            $sourceClasses = $this->classNames($element);
            return '<!-- wp:kadence/advancedbtn ' . wp_json_encode(array('uniqueID' => $wrapperId, 'className' => $sourceClasses)) . ' --><div class="wp-block-kadence-advancedbtn kb-buttons-wrap kb-btns' . esc_attr($wrapperId) . ' ' . esc_attr($sourceClasses) . '"><!-- wp:kadence/singlebtn ' . wp_json_encode($single) . ' /--></div><!-- /wp:kadence/advancedbtn -->' . "\n";
        }
        if ($tag === 'a') {
            $hasElements = false; foreach ($element->childNodes as $child) { if ($child instanceof DOMElement) { $hasElements = true; break; } }
            if ($hasElements) {
                $attributes = $this->mapper->columnAttributes($styles, $uniqueId); $attributes['link'] = $element->getAttribute('href');
                return $this->kadenceColumn($uniqueId, $attributes, $this->convertChildren($element), $this->classNames($element));
            }
            $attributes = $this->mapper->groupAttributes($styles, $uniqueId);
            unset($attributes['metadata'], $attributes['layout']);
            return '<!-- wp:paragraph ' . wp_json_encode($attributes) . ' --><p><a href="' . esc_url($element->getAttribute('href')) . '">' . esc_html($innerText) . '</a></p><!-- /wp:paragraph -->' . "\n";
        }
        if ($tag === 'form') {
            $fields = array();
            foreach ($element->getElementsByTagName('*') as $control) {
                if (!in_array(strtolower($control->tagName), array('input', 'textarea', 'select'), true)) { continue; }
                $id = $control->getAttribute('id');
                $xpath = new \DOMXPath($element->ownerDocument);
                $labelNode = $id !== '' ? $xpath->query('.//label[@for="' . addslashes($id) . '"]', $element)->item(0) : null;
                $label = $labelNode ? trim($labelNode->textContent) : '';
                $fieldId = $uniqueId . '-field-' . sanitize_html_class($control->getAttribute('name') ?: (string) count($fields));
                $controlId = $fieldId . '-control'; $labelStyleId = $fieldId . '-label';
                if ($this->resolver) {
                    $this->mapper->groupAttributes($this->resolver->resolve($control), $controlId);
                    if ($labelNode instanceof DOMElement) { $this->mapper->groupAttributes($this->resolver->resolve($labelNode), $labelStyleId); }
                    if ($control->parentNode instanceof DOMElement) { $this->mapper->groupAttributes($this->resolver->resolve($control->parentNode), $fieldId); }
                }
                $field = array('label' => $label, 'name' => $control->getAttribute('name'), 'type' => strtolower($control->tagName) === 'input' ? ($control->getAttribute('type') ?: 'text') : strtolower($control->tagName), 'required' => $control->hasAttribute('required'), 'styleId' => $fieldId, 'controlStyleId' => $controlId, 'labelStyleId' => $labelStyleId);
                if (strtolower($control->tagName) === 'select') {
                    $field['options'] = array();
                    foreach ($control->getElementsByTagName('option') as $option) { $field['options'][] = array('label' => trim($option->textContent), 'value' => $option->getAttribute('value')); }
                }
                $fields[] = $field;
            }
            $buttons = $element->getElementsByTagName('button');
            $buttonStyleId = $uniqueId . '-submit';
            if ($buttons->length && $this->resolver) { $this->mapper->groupAttributes($this->resolver->resolve($buttons->item(0)), $buttonStyleId); }
            $mapped = $this->mapper->groupAttributes($styles, $uniqueId);
            $attributes = array('formId' => $element->getAttribute('data-form-id'), 'fields' => $fields, 'submitText' => $buttons->length ? trim($buttons->item(0)->textContent) : 'Submit', 'styleId' => $uniqueId, 'buttonStyleId' => $buttonStyleId, 'buttonClassName' => $buttons->length ? $this->classNames($buttons->item(0)) : '', 'className' => $this->classNames($element), 'style' => $mapped['style'] ?? array());
            return '<!-- wp:kic/contact-form ' . wp_json_encode($attributes) . ' /-->' . "\n";
        }
        if ($tag === 'nav') {
            $links = '';
            foreach ($element->getElementsByTagName('a') as $link) {
                $links .= '<!-- wp:navigation-link ' . wp_json_encode(array('label' => trim($link->textContent), 'url' => $link->getAttribute('href'), 'kind' => 'custom')) . ' /-->';
            }
            $this->mapper->groupAttributes($styles, $uniqueId);
            $menuName = sanitize_html_class($element->getAttribute('data-menu'));
            $attributes = array('overlayMenu' => 'mobile', 'className' => trim($this->classNames($element) . ' kic-menu' . ($menuName !== '' ? ' kic-menu-' . $menuName : '') . ' kic-style-' . $uniqueId), 'layout' => array('type' => 'flex', 'justifyContent' => 'right'));
            return '<!-- wp:navigation ' . wp_json_encode($attributes) . ' -->' . $links . '<!-- /wp:navigation -->';
        }
        if (preg_match('/(^|\s)grid(\s|$)/', $element->getAttribute('class')) || (($styles['desktop']['display'] ?? '') === 'grid')) {
            $children = array(); foreach ($element->childNodes as $child) { if ($child instanceof DOMElement) { $children[] = $child; } }
            $attributes = $this->mapper->rowAttributes($styles, $uniqueId, max(1, count($children)));
            if (preg_match('/(^|\s)container(\s|$)/', $element->getAttribute('class'))) { $attributes['inheritMaxWidth'] = false; $attributes['maxWidth'] = $this->containerWidth; $attributes['maxWidthUnit'] = 'px'; }
            $attributes['className'] = $this->classNames($element);
            $columns = '';
            foreach ($children as $child) {
                $childStyles = $this->resolver ? $this->resolver->resolve($child) : array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
                $childId = sanitize_html_class($child->getAttribute('data-component-id') ?: $uniqueId . '-col-' . (++$this->elementIndex));
                $convertWhole = in_array(strtolower($child->tagName), array('form', 'img', 'nav', 'ul', 'ol'), true);
                $childContent = $convertWhole || $child->childElementCount === 0 ? $this->convertElement($child) : $this->convertChildren($child);
                $columns .= $this->kadenceColumn($childId, $this->mapper->columnAttributes($childStyles, $childId), $childContent, $this->classNames($child));
            }
            return '<!-- wp:kadence/rowlayout ' . wp_json_encode($attributes) . ' -->' . $columns . '<!-- /wp:kadence/rowlayout -->' . "\n";
        }
        $children = $this->convertChildren($element);
        if ($children !== '') {
            $attributes = $this->mapper->columnAttributes($styles, $uniqueId);
            if (preg_match('/(^|\s)container(\s|$)/', $element->getAttribute('class'))) {
                $attributes['maxWidth'] = array($this->containerWidth, '', '');
                $attributes['maxWidthUnit'] = 'px';
            }
            return $this->kadenceColumn($uniqueId, $attributes, $children, $this->classNames($element));
        }
        if (in_array($tag, array('span', 'strong', 'em', 'small'), true) && $innerText !== '') {
            return '<!-- wp:paragraph --><p><' . $tag . '>' . esc_html($innerText) . '</' . $tag . '></p><!-- /wp:paragraph -->';
        }
        return '';
    }

    /** @return array<string,array<string,string>> */
    private function resolveFragmentRoot(string $html): array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $section = $dom->getElementsByTagName('section')->item(0);
        return $section instanceof DOMElement && $this->resolver ? $this->resolver->resolve($section) : array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
    }

    private function classNames(DOMElement $element): string
    {
        return $this->classNamesFromString($element->getAttribute('class'));
    }

    private function classNamesFromString(string $value): string
    {
        $classes = preg_split('/\s+/', trim($value)) ?: array();
        return implode(' ', array_filter(array_map(static function (string $class): string {
            $clean = sanitize_html_class($class);
            return $clean === '' ? '' : 'kic-src-' . $clean;
        }, $classes)));
    }

    private function fragmentRootClasses(string $html): string
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $section = $dom->getElementsByTagName('section')->item(0);
        return $section instanceof DOMElement ? $section->getAttribute('class') : '';
    }

    /** @param array<string,mixed> $attributes */
    private function kadenceColumn(string $uniqueId, array $attributes, string $content, string $classes = ''): string
    {
        $attributes['uniqueID'] = $uniqueId;
        if ($classes !== '') { $attributes['className'] = $classes; }
        return '<!-- wp:kadence/column ' . wp_json_encode($attributes) . ' --><div class="wp-block-kadence-column kadence-column' . esc_attr($uniqueId) . ($classes !== '' ? ' ' . esc_attr($classes) : '') . '"><div class="kt-inside-inner-col">' . $content . '</div></div><!-- /wp:kadence/column -->';
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /** @param array<string,array<string,string>> $styles */
    private function kadenceAccordion(DOMElement $list, array $styles, string $uniqueId): string
    {
        $panes = ''; $count = 0; $openPane = 0; $hasOpen = false; $titleStyles = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach ($list->childNodes as $item) {
            if (!$item instanceof DOMElement || $item->getAttribute('data-component') !== 'faq-item') { continue; }
            $count++; $question = ''; $answer = ''; $expanded = false;
            foreach ($item->childNodes as $child) {
                if (!$child instanceof DOMElement) { continue; }
                if (strtolower($child->tagName) === 'button') {
                    $question = trim($child->textContent); $expanded = strtolower($child->getAttribute('aria-expanded')) === 'true';
                    if ($count === 1 && $this->resolver) { $titleStyles = $this->resolver->resolve($child); }
                } else { $answer .= $this->convertElement($child); }
            }
            if ($expanded && !$hasOpen) { $openPane = $count - 1; $hasOpen = true; }
            $paneId = sanitize_html_class($item->getAttribute('data-component-id') ?: $uniqueId . '-pane-' . $count);
            $paneAttributes = array('id' => $count, 'uniqueID' => $paneId, 'title' => $question, 'titleTag' => 'div');
            $panes .= '<!-- wp:kadence/pane ' . wp_json_encode($paneAttributes) . ' --><div class="wp-block-kadence-pane kt-accordion-pane kic-src-faq-item kt-accordion-pane-' . $count . ' kt-pane' . esc_attr($paneId) . '"><div class="kt-accordion-header-wrap"><button class="kt-blocks-accordion-header kic-src-faq-question kt-acccordion-button-label-show" type="button"><span class="kt-blocks-accordion-title-wrap"><span class="kt-blocks-accordion-title">' . esc_html($question) . '</span></span><span class="kt-blocks-accordion-icon-trigger"></span></button></div><div class="kt-accordion-panel"><div class="kt-accordion-panel-inner kic-src-faq-answer">' . $answer . '</div></div></div><!-- /wp:kadence/pane -->';
        }
        $attributes = $this->mapper->accordionAttributes($styles, $titleStyles, $uniqueId, $count, $openPane, !$hasOpen);
        return '<!-- wp:kadence/accordion ' . wp_json_encode($attributes) . ' --><div class="wp-block-kadence-accordion alignnone kic-src-faq-list"><div class="kt-accordion-wrap kt-accordion-id' . esc_attr($uniqueId) . ' kt-accordion-has-' . $count . '-panes kt-active-pane-' . $openPane . ' kt-accordion-block kt-pane-header-alignment-left kt-accodion-icon-style-basic kt-accodion-icon-side-right"><div class="kt-accordion-inner-wrap" data-allow-multiple-open="false" data-start-open="' . ($hasOpen ? $openPane : 'none') . '">' . $panes . '</div></div></div><!-- /wp:kadence/accordion -->';
    }

    private function replacePlaceholders(string $value): string
    {
        foreach ($this->placeholders as $name => $replacement) {
            if ($replacement !== '') { $value = str_replace('{{' . $name . '}}', $replacement, $value); }
        }
        return $value;
    }
}
