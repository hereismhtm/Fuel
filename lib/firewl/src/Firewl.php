<?php

namespace Firewl;

use Medoo\Medoo;

class Firewl
{
    private Medoo $database;

    public function __construct(Medoo $database)
    {
        $this->database = $database;
    }

    public function isBanned(): bool
    {
        $now = date('Y-m-d H:i:s', date_create('now')->getTimestamp());
        $fourHoursAgo = date('Y-m-d H:i:s', date_create('-4 hour')->getTimestamp());

        $penalty_points = $this->database->sum(
            'firewl',
            ['penalty'],
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'created_at[<>]' => [$fourHoursAgo, $now],
            ]
        );

        return $penalty_points >= 10;
    }

    public function penalty(int $points): void
    {
        $this->database->insert('firewl', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'penalty' => $points,
        ]);
    }
}
