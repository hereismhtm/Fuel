<?php

namespace AuthenticIn;

use Medoo\Medoo;

abstract class UserBaseModel
{
    private bool $logged = false;

    private ?int $id = null;
    private string $created_at = '';
    private string $updated_at = '';
    private bool $frozen = false;
    private int $otp_counter = 0;
    private string $safeguard;
    private string $otp_secret;

    private Medoo $model_database;
    private string $model_table;
    private array $model_fields;
    private array $model_basic_fields = [
        'id [Int]',
        'created_at',
        'updated_at',
        'frozen [Bool]',
        'otp_counter [Int]',
        'safeguard'
    ];

    abstract public function guardedDataset(): string;

    final protected function init(?int $id, Medoo $database, string $table, array $fields): void
    {
        $this->model_database = $database;
        $this->model_table = $table;
        $this->model_fields = $fields;
        $this->load($id);
    }

    final protected function applyMathOnInt(string $field, string $operator, int $value): mixed
    {
        $this->model_database->update(
            $this->model_table,
            ["{$field}[{$operator}]" => $value],
            ['id' => $this->id]
        );
        return $this->model_database->get(
            $this->model_table,
            "{$field} [Int]",
            ['id' => $this->id]
        );
    }

    final protected function changesNotify(array $changes = []): bool
    {
        $safeguard = AuthenticIn::generateSafeguard(
            $this->fullGuardedDataset(),
            $this->otp_secret
        );

        $data = [];
        foreach ($changes as $value) {
            $data[$value] = $this->$value;
        }
        $data = array_merge(
            $data,
            ['safeguard' => $safeguard]
        );
        $pdo = $this->model_database->update(
            $this->model_table,
            $data,
            ['id' => $this->id]
        );
        return $pdo->rowCount() == 1 ? true : false;
    }

    final public function configuration(string $based_on = 'id', array $changes = []): int|bool
    {
        $data = [];
        foreach ($changes as $value) {
            $data[$value] = $this->$value;
        }

        $process = $this->userDatabaseRecord(
            $data,
            [$based_on => $this->$based_on]
        );

        if ($process !== false) {
            if (is_numeric($process)) {
                $this->load($process);
            } else {
                $this->load([$based_on => $this->$based_on]);
                if (!$this->is_intact()) return 0;
            }

            $this->safeguard = AuthenticIn::generateSafeguard($this->fullGuardedDataset());
            $this->otp_secret = AuthenticIn::$otp_secret_value;
            $pdo = $this->model_database->update(
                $this->model_table,
                ['safeguard' => $this->safeguard],
                ['id' => $this->id]
            );
            return $pdo->rowCount() == 1 ? true : false;
        }
        return false;
    }

    final public function login(int $counter, string $otp): bool
    {
        if ($this->id !== null) {
            if ($this->frozen === false) {
                if ($counter > $this->otp_counter) {
                    if ($this->is_intact()) {
                        if (
                            $otp ==
                            AuthenticIn::otp($counter, $this->otp_secret)
                        ) {
                            $this->otp_counter = $counter;
                            $this->changesNotify(['otp_counter']);
                            $this->logged = true;
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    final public function is_intact(): bool
    {
        $data = AuthenticIn::extractSafeguard($this->safeguard);
        if ($data === false) {
            return false;
        }
        $guard = $data[0];
        $this->otp_secret = $data[1];
        return
            $guard ==
            AuthenticIn::guard($this->fullGuardedDataset(), $this->otp_secret);
    }

    final public function is_logged(): bool
    {
        return $this->logged;
    }

    final public function id(): ?int
    {
        return $this->id;
    }

    final public function created_at(): string
    {
        return $this->created_at;
    }

    final public function updated_at(): string
    {
        return $this->updated_at;
    }

    final public function frozen(): bool
    {
        return $this->frozen;
    }

    final public function otpCounter(): int
    {
        return $this->otp_counter;
    }

    private function load(mixed $identifyer): void
    {
        if ($identifyer !== null) {
            $where = is_array($identifyer) ?
                $identifyer : ['id' => $identifyer];
            $columns = array_merge(
                $this->model_fields,
                $this->model_basic_fields
            );
            $data = $this->model_database->get(
                $this->model_table,
                $columns,
                $where
            );
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $this->$key = $value;
                }
            }
        }
    }

    private function fullGuardedDataset(): string
    {
        return $this->guardedDataset() . $this->frozen . $this->otp_counter;
    }

    private function userDatabaseRecord(array $data, array $where): int|bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s', date_create('now')->getTimestamp());

        $pdo = $this->model_database->update($this->model_table, $data, $where);
        if ($pdo->rowCount() == 1) {
            return true;
        } else {
            unset($data['updated_at']);
            unset($where['id']);
            $values = array_merge($where, $data);
            $pdo = $this->model_database->insert($this->model_table, $values);
            return $pdo->rowCount() == 1 ? (int) $this->model_database->id() : false;
        }
    }
}
