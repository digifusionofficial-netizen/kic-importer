<?php

namespace KIC\Importer\Compatibility;

final class CompatibilityManager
{
    /**
     * Empty until a Kadence version/adapter pair has been tested and approved.
     * Unknown versions must never be guessed.
     *
     * @var array<string, string>
     */
    private const TESTED_ADAPTERS = array(
        array('min' => '3.0.0', 'max' => '5.0.0', 'adapter' => 'kadence-native-v1'),
    );

    public function inspect(): CompatibilityStatus
    {
        $version = $this->detectKadenceBlocksVersion();
        if ($version === null) {
            return new CompatibilityStatus(false, 'KIC Importer is inactive for imports: Kadence Blocks was not detected.', null, null);
        }

        foreach (self::TESTED_ADAPTERS as $definition) {
            if (version_compare($version, $definition['min'], '>=') && version_compare($version, $definition['max'], '<')) {
                if (class_exists('WP_Block_Type_Registry')) {
                    $requirements = array(
                        'kadence/advancedheading' => array('uniqueID', 'size', 'tabSize', 'mobileSize', 'padding', 'tabletPadding', 'mobilePadding'),
                        'kadence/rowlayout' => array('uniqueID', 'columns', 'tabletLayout', 'mobileLayout', 'padding', 'tabletPadding', 'mobilePadding'),
                        'kadence/column' => array('uniqueID', 'padding', 'tabletPadding', 'mobilePadding', 'background', 'borderStyle', 'displayShadow'),
                        'kadence/advancedbtn' => array('uniqueID'),
                        'kadence/singlebtn' => array('uniqueID', 'text', 'link', 'padding', 'tabletPadding', 'mobilePadding', 'background'),
                        'kadence/image' => array('uniqueID', 'url', 'alt', 'borderRadius', 'displayBoxShadow'),
                        'kadence/accordion' => array('uniqueID', 'paneCount', 'openPane', 'startCollapsed', 'titleStyles'),
                    );
                    foreach ($requirements as $blockName => $required) {
                        $block = \WP_Block_Type_Registry::get_instance()->get_registered($blockName);
                        if (!$block) { return new CompatibilityStatus(false, sprintf('KIC Importer is inactive: required block %s is not registered.', $blockName), $version, null); }
                        foreach ($required as $attribute) {
                            if (!array_key_exists($attribute, (array) $block->attributes)) {
                                return new CompatibilityStatus(false, sprintf('KIC Importer is inactive: Kadence %s block %s is missing tested attribute %s.', $version, $blockName, $attribute), $version, null);
                            }
                        }
                    }
                }
                return new CompatibilityStatus(true, 'Kadence Blocks is inside the tested compatibility range.', $version, $definition['adapter']);
            }
        }

        return new CompatibilityStatus(
            false,
            sprintf('KIC Importer is inactive for imports: Kadence Blocks %s has no tested adapter.', $version),
            $version,
            null
        );
    }

    private function detectKadenceBlocksVersion(): ?string
    {
        if (defined('KADENCE_BLOCKS_VERSION')) {
            return (string) constant('KADENCE_BLOCKS_VERSION');
        }

        if (function_exists('get_plugins')) {
            foreach (get_plugins() as $file => $data) {
                if (str_contains($file, 'kadence-blocks') && !empty($data['Version'])) {
                    return (string) $data['Version'];
                }
            }
        }

        return null;
    }
}
