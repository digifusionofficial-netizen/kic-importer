<?php

namespace KIC\Importer\Compatibility;

final class CompatibilityStatus
{
    private bool $supported;
    private string $message;
    private ?string $kadenceVersion;
    private ?string $adapter;

    public function __construct(bool $supported, string $message, ?string $kadenceVersion, ?string $adapter)
    {
        $this->supported = $supported;
        $this->message = $message;
        $this->kadenceVersion = $kadenceVersion;
        $this->adapter = $adapter;
    }

    public function isSupported(): bool
    {
        return $this->supported;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return array(
            'supported' => $this->supported,
            'message' => $this->message,
            'kadence_blocks_version' => $this->kadenceVersion,
            'adapter' => $this->adapter,
        );
    }
}
