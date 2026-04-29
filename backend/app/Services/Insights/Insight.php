<?php

declare(strict_types=1);

namespace App\Services\Insights;

class Insight
{
    public function __construct(
        public string $code,
        public string $tone,   // neutral | good | warm | warn | alert
        public string $title,
        public string $body,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'code'  => $this->code,
            'tone'  => $this->tone,
            'title' => $this->title,
            'body'  => $this->body,
        ];
    }
}
