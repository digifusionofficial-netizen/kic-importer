<?php

namespace KIC\Importer\Adapter;

use KIC\Importer\Schema\SiteSchema;

interface AdapterInterface
{
    public function name(): string;
    /** @param array<string,mixed> $page */
    public function renderPage(array $page, SiteSchema $schema): string;
}
