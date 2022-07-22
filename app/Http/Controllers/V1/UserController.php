<?php

namespace App\Http\Controllers\V1;

use App\Http\Stamp;
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
                return response(...$this->answer->be(Stamp::LoginFirst));
            }
            $source = $this->user->id();
        }

        $now = date('Y-m-d H:i:s', date_create('now')->getTimestamp());
        $fifteenMinutesAgo = date('Y-m-d H:i:s', date_create('-15 minute')->getTimestamp());
        $fourHoursAgo = date('Y-m-d H:i:s', date_create('-4 hour')->getTimestamp());

        $this->answer->is(Stamp::Fail);
        $json = &$this->answer->json();
        if ($_vc == 0) {
            $is_code_sent = $this->db->has(
                'vcodes',
                [
                    'login_id' => $login['id'],
                    'updated_at[<>]' => [$fifteenMinutesAgo, $now],
                ]
            );

            if ($is_code_sent) {
                $this->answer->is(Stamp::VerificationCodeBeenSent);
            } else {
                $source_send_attemps = $this->db->count(
                    'vcodes',
                    [
                        'source' => $source,
                        'updated_at[<>]' => [$fourHoursAgo, $now],
                    ]
                );

                if ($source_send_attemps >= 3) {
                    return response(...$this->answer->be(Stamp::CalmDown));
                }

                // FIXME: generate random code
                // $code = random_int(1, 999999);
                $code = 123456;
                $code = str_pad($code, 6, '0', STR_PAD_LEFT);

                $successful_sent = false;
                if ($login['is_email']) {
                    if ($this->_sendVC($login['id'], $code, as_email: true)) {
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
                            if ($this->_sendVC($res['email'], $code, as_email: true)) {
                                $successful_sent = 'E-mail message';
                            }
                        } else {
                            return response(...$this->answer->be(
                                Stamp::UserDataDamage
                            ));
                        }
                    } else {
                        if ($this->_sendVC($login['id'], $code, as_email: false)) {
                            $successful_sent = 'SMS message';
                        }
                    }
                }

                if ($successful_sent !== false) {
                    $this->databaseRecord(
                        'vcodes',
                        [
                            'code' => $code,
                            'source' => $source,
                        ],
                        ['login_id' => $login['id']]
                    );
                    $this->answer->is(
                        Stamp::VerificationCodeSent,
                        " as $successful_sent"
                    );
                } else {
                    $this->answer->is(Stamp::VerificationCodeSendFailed);
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
                $this->db->delete('vcodes', ['id' => $code_id]);

                if ($login['is_email']) {
                    $this->user->setEmail($login['id']);
                    $this->answer->is(Stamp::UserEmailSaved);
                } else {
                    $user = $this->authenticIn->userHolder();
                    $user->mobile = $login['id'];

                    $conf = $user->configuration(based_on: 'mobile');
                    if ($conf === true) {
                        $this->answer->is(Stamp::Success);
                        $json['user'] = [
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
                        return response(...$this->answer->be(
                            Stamp::InternalDatabaseError
                        ));
                    } else {
                        return response(...$this->answer->be(
                            Stamp::UserDataDamage
                        ));
                    }
                }
            } else {
                $this->answer->is(Stamp::WrongVerificationCode);
            }
        }

        return response()->json(...$this->answer->be());
    }

    private function _sendVC(string $to, string $code, bool $as_email)
    {
        //TODO: write verification code send script
        return true;
    }
}
