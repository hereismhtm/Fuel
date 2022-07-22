<?php

namespace Toolly;

use App\Http\Stamp;

class Answer
{
    private array $body = [];
    private int $http_code = 0;

    public function __construct(private readonly string $default_key = 'message')
    {
    }

    public function &json(): array
    {
        return $this->body;
    }

    public function is(Stamp $stamp, ?string $extra = null): void
    {
        $this->body[$this->default_key] = $stamp->value . $extra;
        $this->http_code = $stamp->code();
    }

    public function be(?Stamp $stamp = null, ?string $extra = null): array
    {
        if ($stamp != null) {
            $this->body = [];
            $this->http_code = 0;

            $this->is($stamp, $extra);
        }

        return [$this->body, $this->http_code];
    }
}
