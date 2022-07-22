<?php

namespace App\Http\Controllers\V1;

use AuthenticIn\AuthenticIn;
use App\Models\AuthenticInUser;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;
use Medoo\Medoo;
use Toolly\Answer;

class Controller extends BaseController
{
    protected string $lang;
    protected Medoo $db;
    protected Answer $answer;
    protected AuthenticIn $authenticIn;
    protected AuthenticInUser $user;

    public function __construct(Request $request)
    {
        $this->lang = $request->route('lang');

        $this->db = app('medoo');
        $this->answer = app('answer');

        $this->authenticIn = new AuthenticIn($this->db);
        $this->user = $this->authenticIn->identifyUser($request);
    }

    public function databaseRecord(string $table, array $data, array $where): int|bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s', date_create('now')->getTimestamp());

        $pdo = $this->db->update($table, $data, $where);
        if ($pdo->rowCount() == 1) {
            return true;
        } else {
            unset($data['updated_at']);
            unset($where['id']);
            $values = array_merge($where, $data);
            $pdo = $this->db->insert($table, $values);
            return $pdo->rowCount() == 1 ? (int) $this->db->id() : false;
        }
    }
}
