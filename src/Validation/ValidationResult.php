<?php

namespace KIC\Importer\Validation;

final class ValidationResult
{
    /** @var ValidationIssue[] */
    private array $errors = array();

    public function addError(ValidationIssue $issue): void
    {
        $this->errors[] = $issue;
    }

    public function merge(self $other): void
    {
        foreach ($other->errors() as $issue) {
            $this->addError($issue);
        }
    }

    public function passed(): bool
    {
        return $this->errors === array();
    }

    /** @return ValidationIssue[] */
    public function errors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return array(
            'status' => $this->passed() ? 'pass' : 'fail',
            'errors' => array_map(
                static fn (ValidationIssue $issue): array => $issue->toArray(),
                $this->errors
            ),
        );
    }
}
