<?php

namespace App\Http\Controllers\V1;

use App\Http\Stamp;
use Carbon\Carbon;
use Fuel;
use Illuminate\Http\Request;

class PointController extends Controller
{
    public function checkout($mac, Fuel $fuel, $litre)
    {
        if (!$this->user->is_logged()) {
            return response(...$this->answer->be(Stamp::LoginFirst));
        }

        $L = $this->lang;

        $res = $this->db->select(
            'points',
            [
                '[>]employees' => ['at_employee' => 'id'],
                '[>]branches' => ['at_branch' => 'id'],
                '[>]vendors' => ['branches.vendor_id' => 'id'],
            ],
            [
                'vendor' => [
                    'vendors.id(vendor_id) [Int]',
                    $L . '_vendor(vendor)',
                    'sector',
                    'vendors.' . $L . '_contact(vendor_contact)',
                ],
                'branch' => [
                    'branches.id(branch_id) [Int]',
                    $L . '_branch(branch)',
                    $L . '_address(branch_address)',
                    'branches.' . $L . '_contact(branch_contact)',
                    'prices',
                    'prices_updated_at',
                ],
                'point' => [
                    'points.id(point_id) [Int]',
                    'mode',
                    $L . '_fname(first_name)',
                    $L . '_lname(last_name)',
                ],
            ],
            ['mac' => $mac]
        );

        $this->answer->is(Stamp::Fail);
        if (count($res) == 1) {
            $prices_confirmed = Carbon::parse(
                $res[0]['branch']['prices_updated_at']
            )->diffInMinutes() >= 10;

            if ($res[0]['point']['mode'] == 1 && $prices_confirmed) {
                $this->answer->is(Stamp::Success);

                $json = &$this->answer->json();
                $json = $json + $res[0];

                $price = $fuel->price($json['branch']['prices']);
                $json['point']['fuel'] = $fuel;
                $json['point']['litre'] = $litre;
                $json['point']['price'] = $price;
                $json['point']['sale'] = number_format($litre * $price, 2);
                $json['point']['commission'] = Commission::total->str();
                $json['point']['employee'] = "{$json['point']['first_name']} {$json['point']['last_name']}";

                unset($json['branch']['prices']);
                unset($json['branch']['prices_updated_at']);
                unset($json['point']['mode']);
                unset($json['point']['first_name']);
                unset($json['point']['last_name']);
            } else {
                $this->answer->is(Stamp::OutOfService);
            }
        } else {
            $this->answer->is(Stamp::NotDefined);
        }

        return response()->json(...$this->answer->be());
    }

    public function payment(Request $request)
    {
        if (!$this->user->is_logged()) {
            return response(...$this->answer->be(Stamp::LoginFirst));
        }

        $_point = $request->input('point');
        $_fuel = Fuel::from($request->input('fuel'));
        $_litre = $request->input('litre');
        $_price = $request->input('price');

        $res = $this->db->get(
            'points',
            [
                '[>]employees' => ['at_employee' => 'id'],
                '[>]branches' => ['at_branch' => 'id'],
            ],
            [
                $this->lang . '_fname(first_name)',
                $this->lang . '_lname(last_name)',
                'session_key',
                'prices',
                'mode',
            ],
            ['points.id' => $_point]
        );

        $this->answer->is(Stamp::Fail);
        if ($res != null) {
            if ($res['mode'] == 1) {
                $json = &$this->answer->json();
                $json = $json + $res;

                $price = $_fuel->price($json['prices']);
                $json['receipt']['user_id'] = $this->user->id();
                $json['receipt']['point_id'] = (int) $_point;
                $json['receipt']['fuel'] = $_fuel;
                $json['receipt']['litre'] = number_format($_litre, 2, '.', '');
                $json['receipt']['price'] = $price;

                if ($_price == $price) {
                    $sale = number_format($_litre * $price, 2, '.', '');
                    $json['receipt']['sale'] = $sale;
                    $json['receipt']['commission'] = Commission::total->str();
                    $json['receipt']['employee'] = "{$json['first_name']} {$json['last_name']}";
                    $sale_gems = (int) $sale * 100;
                    $sum_gems = $sale_gems + Commission::total->value * 100;

                    if ($sum_gems <= $this->user->credit) {
                        $user = $this->user;

                        $this->db->action(function ($database) use (
                            $user,
                            $_point,
                            &$json,
                            $sale_gems,
                            $sum_gems,
                        ) {
                            $user->setCredit('-', $sum_gems);
                            $year = substr(Carbon::now()->year, 2);
                            $res = $database->insert(
                                'payments' . $year,
                                [
                                    'user_id' => $json['receipt']['user_id'],
                                    'point_id' => $_point,
                                    'fuel' => $json['receipt']['fuel']->name,
                                    'litre' => $json['receipt']['litre'],
                                    'price' => $json['receipt']['price'],
                                    'sale' => $sale_gems,
                                    'emp_c' => Commission::employee->value,
                                    'bra_c' => Commission::branch->value,
                                    'app_c' => Commission::application->value,
                                ]
                            );
                            if ($res->rowCount() != 1) {
                                return false;
                            }
                            $json['receipt']['payment_id'] = (int) ($year . $database->id());
                            $json['receipt']['payment_date'] = $database->get(
                                'payments' . $year,
                                'created_at',
                                ['id' => $database->id()]
                            );
                            $this->_paymentToken($json);
                        });

                        if (isset($json['receipt']['payment_id'])) {
                            $this->answer->is(Stamp::Success);
                        } else {
                            $this->answer->is(Stamp::FailedProcess);
                        }
                    } else {
                        $this->answer->is(Stamp::NoEnoughCredit);
                    }
                } else {
                    $this->answer->is(Stamp::PriceMismatch);
                }

                unset($json['first_name']);
                unset($json['last_name']);
                unset($json['session_key']);
                unset($json['prices']);
                unset($json['mode']);
            } else {
                $this->answer->is(Stamp::OutOfService);
            }
        } else {
            $this->answer->is(Stamp::NotDefined);
        }

        return response()->json(...$this->answer->be());
    }

    private function _paymentToken(&$json)
    {
        $info = $json['receipt']['payment_id'] . ':'
            . $json['receipt']['user_id'] . ':'
            . $json['receipt']['point_id'] . ':'
            . $json['receipt']['fuel']->name . ':'
            . $json['receipt']['litre'] . '-';

        $info .= $json['receipt']['sale'] . ':'
            . 0 . ':' // employee tip
            . Commission::employee->value;

        $hash = hash('SHA512', $info . $json['session_key']);
        $hash = substr($hash, 0, 32);

        $json['receipt']['payment_token'] = $info . '-' . $hash;
    }
}

enum Commission: int
{
    case employee = 30;
    case branch = 10;
    case application = 60;
    case total = 100;

    public function str(): string
    {
        return $this->value;
    }
}
