<?php

namespace Firewl;

use Medoo\Medoo;

class Firewl
{
    private const PENALTY_POINTS = 10;

    private Medoo $database;

    public int $penaltyMinutes = 120;

    public function __construct(Medoo $conn)
    {
        $this->database = $conn;
    }

    public function isBanned(): bool
    {
        $now = date('Y-m-d H:i:s', date_create('now')->getTimestamp());
        $pm = '-' . $this->penaltyMinutes . ' minute';
        $pm = date('Y-m-d H:i:s', date_create($pm)->getTimestamp());

        $penaltyPoints = $this->database->sum(
            'firewl',
            ['penalty'],
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'created_at[<>]' => [$pm, $now],
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => self::PENALTY_POINTS,
            ]
        );

        return $penaltyPoints >= self::PENALTY_POINTS;
    }

    public function penalty(int $points, ?string $target = null): void
    {
        $this->database->insert('firewl', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'penalty' => $points,
            'target' => $target,
        ]);
    }
}
