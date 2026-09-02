<?php

namespace KIC\Importer\Schema;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use KIC\Importer\Style\CssParser;

final class SiteSchemaBuilder
{
    /** @param array<string,mixed> $manifest */
    public function build(string $root, array $manifest): SiteSchema
    {
        $cssFiles = array();
        foreach (array('assets/css/global.css', 'assets/css/components.css', 'assets/css/responsive.css') as $cssFile) {
            $cssFiles[$cssFile] = (string) file_get_contents($root . '/' . $cssFile);
        }
        $stylesheet = (new CssParser())->parseFiles($cssFiles);
        $pages = array();
        foreach ($manifest['pages'] as $definition) {
            $file = (string) $definition['file'];
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            if (!$dom->loadHTML((string) file_get_contents($root . '/' . $file), LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
                libxml_use_internal_errors($previous);
                throw new RuntimeException('Unable to parse validated page: ' . $file);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($dom);
            $sections = array();
            foreach ($xpath->query('//main/section') as $section) {
                $sections[] = array(
                    'component' => $section->getAttribute('data-component'),
                    'component_id' => $section->getAttribute('data-component-id'),
                    'classes' => $section->getAttribute('class'),
                    'html' => $dom->saveHTML($section),
                );
            }
            $pages[] = array(
                'definition' => $definition,
                'header_html' => $dom->saveHTML($xpath->query('//header[@data-component="site-header"]')->item(0)),
                'sections' => $sections,
                'footer_html' => $dom->saveHTML($xpath->query('//footer[@data-component="site-footer"]')->item(0)),
            );
        }
        return new SiteSchema($manifest, $pages, $stylesheet);
    }
}
