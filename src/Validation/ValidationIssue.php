<?php

namespace KIC\Importer\Validation;

final class ValidationIssue
{
    private string $code;
    private string $message;
    private ?string $path;

    public function __construct(string $code, string $message, ?string $path = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->path = $path;
    }

    public function toArray(): array
    {
        return array_filter(array(
            'code' => $this->code,
            'message' => $this->message,
            'path' => $this->path,
        ), static fn ($value): bool => $value !== null);
    }
}
