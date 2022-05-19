<?php

namespace App\Http\Controllers\V1;

use AuthenticIn\AuthenticIn;
use Illuminate\Http\Request;
use Toolly\Typ;

class UserController extends Controller
{
    public function login($who, Request $request)
    {
        $_vc = $request->input('vc');
        $source = $request->ip();
        $login = ['id' => explode(':', $who)[1]];
        $login['is_email'] = strpos($who, 'email:') === 0;
        if ($login['is_email']) {
            if (!$this->user->logged()) {
                return response(['message' => 'Unauthorized'], 401);
            }
            $source = $this->user->id();
        }

        $now = date('Y-m-d H:i:s', date_create('now')->getTimestamp());
        $fifteenMinutesAgo = date('Y-m-d H:i:s', date_create('-15 minute')->getTimestamp());
        $fourHoursAgo = date('Y-m-d H:i:s', date_create('-4 hour')->getTimestamp());

        $status = 422;
        $output = [];
        if ($_vc == 0) {
            $is_code_sent = $this->db->has(
                'vcodes',
                [
                    'login_id' => $login['id'],
                    'updated_at[<>]' => [$fifteenMinutesAgo, $now],
                ]
            );

            if ($is_code_sent) {
                $status = 200;
                $output['message'] = 'Verification code already has been sent';
            } else {
                $source_send_attemps = $this->db->count(
                    'vcodes',
                    [
                        'source' => $source,
                        'updated_at[<>]' => [$fourHoursAgo, $now],
                    ]
                );

                if ($source_send_attemps >= 3) {
                    return response(['message' => 'Too Many Requests'], 429);
                }

                // FIXME: generate random code
                // $code = random_int(1, 999999);
                $code = 123456;
                $code = str_pad($code, 6, '0', STR_PAD_LEFT);

                $successful_sent = false;
                if ($login['is_email']) {
                    if ($this->_sendVC(true, $login['id'], $code)) {
                        $successful_sent = 'E-mail message intended for account linkup';
                    }
                } else {
                    $res = $this->db->get(
                        'users',
                        ['id', 'email'],
                        ['mobile' => $login['id']]
                    );
                    if (
                        Typ::validate($res, ['email' => Typ::email()])
                    ) {
                        $user = $this->authenticIn->userHolder($res['id']);
                        if ($user->legit()) {
                            if ($this->_sendVC(true, $res['email'], $code)) {
                                $successful_sent = 'E-mail message';
                            }
                        } else {
                            return response(['message' => 'User data stored in server '
                                . 'are not legit'], 500);
                        }
                    } else {
                        if ($this->_sendVC(false, $login['id'], $code)) {
                            $successful_sent = 'SMS message';
                        }
                    }
                }

                if ($successful_sent !== false) {
                    $status = 200;
                    $this->databaseRecord(
                        'vcodes',
                        [
                            'code' => $code,
                            'source' => $source,
                        ],
                        ['login_id' => $login['id']]
                    );
                    $output['message'] = "Verification code sent successfully as $successful_sent";
                } else {
                    $output['message'] = 'Verification code send failed';
                }
            }
        } else {
            $code_id = $this->db->get(
                'vcodes',
                'id',
                [
                    'login_id' => $login['id'],
                    'code' => $_vc,
                    'updated_at[<>]' => [$fifteenMinutesAgo, $now],
                ]
            );

            if ($code_id != null) {
                $status = 200;
                $this->db->delete('vcodes', ['id' => $code_id]);

                if ($login['is_email']) {
                    $this->user->setEmail($login['id']);
                    $output['message'] = 'User E-mail saved';
                } else {
                    $user = $this->authenticIn->userHolder();
                    $user->mobile = $login['id'];

                    $conf = $user->configuration(based_on: 'mobile');
                    if ($conf === true) {
                        $output['user_data'] = [
                            'user_id' => $user->id(),
                            'mobile' => $user->mobile,
                            'email' => $user->email,
                            'name' => $user->name,
                            'credit' => number_format($user->credit / 100, 2, '.', ''),
                            'frozen' => $user->frozen(),
                            'otp_secret' => AuthenticIn::$otp_secret_value,
                            'otp_counter' => $user->otpCounter(),
                        ];
                    } else if ($conf === false) {
                        return response(['message' => 'Internal database Error'], 500);
                    } else {
                        return response(['message' => 'User data stored in server '
                            . 'are not legit'], 500);
                    }
                }
            } else {
                $output['message'] = 'Wrong verification code';
            }
        }

        return response()->json($output, $status);
    }

    private function _sendVC(bool $as_email, string $to, string $code)
    {
        //TODO: write verification code send script
        return true;
    }
}
