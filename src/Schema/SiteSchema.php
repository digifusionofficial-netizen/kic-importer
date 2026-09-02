<?php

namespace KIC\Importer\Schema;

final class SiteSchema
{
    /** @var array<string,mixed> */
    private array $manifest;
    /** @var array<int,array<string,mixed>> */
    private array $pages;
    private \KIC\Importer\Style\Stylesheet $stylesheet;

    /** @param array<string,mixed> $manifest @param array<int,array<string,mixed>> $pages */
    public function __construct(array $manifest, array $pages, \KIC\Importer\Style\Stylesheet $stylesheet)
    {
        $this->manifest = $manifest;
        $this->pages = $pages;
        $this->stylesheet = $stylesheet;
    }

    /** @return array<string,mixed> */
    public function manifest(): array { return $this->manifest; }
    /** @return array<int,array<string,mixed>> */
    public function pages(): array { return $this->pages; }
    public function stylesheet(): \KIC\Importer\Style\Stylesheet { return $this->stylesheet; }
}
