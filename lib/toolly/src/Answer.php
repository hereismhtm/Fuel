<?php

namespace Toolly;

use App\Http\Stamp;
use Firewl\Firewl;

class Answer
{
    private array $body = [];
    private int $httpCode = 0;

    public ?Firewl $firewl = null;

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
        $data = $stamp->data();
        $this->httpCode = $data['code'];

        if ($this->firewl != null && isset($data['penalty'])) {
            $this->firewl->penalty(
                points: $data['penalty'],
                target: $stamp->name
            );
        }
    }

    public function be(?Stamp $stamp = null, ?string $extra = null): array
    {
        if ($stamp != null) {
            $this->body = [];
            $this->httpCode = 0;

            $this->is($stamp, $extra);
        }

        return [$this->body, $this->httpCode];
    }
}
