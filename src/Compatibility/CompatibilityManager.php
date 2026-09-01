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
    private const TESTED_ADAPTERS = array();

    public function inspect(): CompatibilityStatus
    {
        $version = $this->detectKadenceBlocksVersion();
        if ($version === null) {
            return new CompatibilityStatus(false, 'KIC Importer is inactive for imports: Kadence Blocks was not detected.', null, null);
        }

        foreach (self::TESTED_ADAPTERS as $constraint => $adapter) {
            if (version_compare($version, $constraint, '>=')) {
                return new CompatibilityStatus(true, 'A tested Kadence adapter is available.', $version, $adapter);
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

        return null;
    }
}
