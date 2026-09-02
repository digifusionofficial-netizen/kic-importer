<?php

namespace KIC\Importer\Style;

final class Stylesheet
{
    /** @var array<string,array<int,array{selector:string,declarations:array<string,string>,order:int}>> */
    private array $rules;
    /** @var array<string,string> */
    private array $variables;
    /** @var array<int,array<string,mixed>> */
    private array $unsupported;

    /** @param array<string,array<int,array{selector:string,declarations:array<string,string>,order:int}>> $rules @param array<string,string> $variables @param array<int,array<string,mixed>> $unsupported */
    public function __construct(array $rules, array $variables, array $unsupported)
    {
        $this->rules = $rules;
        $this->variables = $variables;
        $this->unsupported = $unsupported;
    }

    /** @return array<int,array{selector:string,declarations:array<string,string>,order:int}> */
    public function rules(string $viewport = 'desktop'): array { return $this->rules[$viewport] ?? array(); }
    /** @return array<string,string> */
    public function variables(): array { return $this->variables; }
    /** @return array<int,array<string,mixed>> */
    public function unsupported(): array { return $this->unsupported; }
}
