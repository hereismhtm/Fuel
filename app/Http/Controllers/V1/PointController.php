<?php

namespace App\Http\Controllers\V1;

use Carbon\Carbon;
use Fuel;
use Illuminate\Http\Request;

class PointController extends Controller
{
    private $COMMISSION = [100, 30, 10, 60];

    public function lookup($worker, Fuel $fuel, $litre)
    {
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
            ['mac' => $worker]
        );

        $status = 422;
        $json['message'] = 'Fail';
        if (count($res) == 1) {
            $prices_confirmed = Carbon::parse(
                $res[0]['branch']['prices_updated_at']
            )->diffInMinutes() >= 10;

            if ($res[0]['point']['mode'] == 1 && $prices_confirmed) {
                $status = 200;
                $json['message'] = 'Success';
                $json = $json + $res[0];

                $price = $fuel->price($json['branch']['prices']);
                $json['point']['fuel'] = $fuel;
                $json['point']['litre'] = $litre;
                $json['point']['price'] = $price;
                $json['point']['sale'] = number_format($litre * $price, 2);
                $json['point']['commission'] = (string) $this->COMMISSION[0];
                $json['point']['employee'] = "{$json['point']['first_name']} {$json['point']['last_name']}";

                unset($json['branch']['prices']);
                unset($json['branch']['prices_updated_at']);
                unset($json['point']['mode']);
                unset($json['point']['first_name']);
                unset($json['point']['last_name']);
            } else {
                $json['message'] = 'Out of service';
            }
        } else {
            $json['message'] = 'Not defined';
        }

        return response()->json($json, $status);
    }

    public function payment(Request $request)
    {
        if (!$this->user->logged()) {
            return response(['message' => 'Unauthorized'], 401);
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

        $status = 422;
        $json['message'] = 'Fail';
        if ($res != null) {
            if ($res['mode'] == 1) {
                $json['receipt'] = $res;

                $price = $_fuel->price($json['receipt']['prices']);
                $json['receipt']['user_id'] = $this->user->id();
                $json['receipt']['point_id'] = (int) $_point;
                $json['receipt']['fuel'] = $_fuel;
                $json['receipt']['litre'] = number_format($_litre, 2, '.', '');
                $json['receipt']['price'] = $price;

                if ($_price == $price) {
                    $sale = number_format($_litre * $price, 2, '.', '');
                    $json['receipt']['sale'] = $sale;
                    $json['receipt']['commission'] = (string) $this->COMMISSION[0];
                    $json['receipt']['employee'] = "{$json['receipt']['first_name']} {$json['receipt']['last_name']}";
                    $sale_gems = (int) ($sale * 100);
                    $sum_gems = $sale_gems + ($this->COMMISSION[0] * 100);

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
                                    'emp_c' => $this->COMMISSION[1],
                                    'bra_c' => $this->COMMISSION[2],
                                    'app_c' => $this->COMMISSION[3],
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

                        $status = isset($json['receipt']['payment_token']) ? 200 : 507;
                        if ($status == 200) {
                            $json['message'] = 'Success';
                        }
                    } else {
                        $json['message'] = 'No enough credit';
                    }
                } else {
                    $json['message'] = 'Fuel price mismatch, try again';
                }

                unset($json['receipt']['first_name']);
                unset($json['receipt']['last_name']);
                unset($json['receipt']['session_key']);
                unset($json['receipt']['prices']);
                unset($json['receipt']['mode']);
            } else {
                $json['message'] = 'Out of service';
            }
        } else {
            $json['message'] = 'Not defined';
        }

        return response()->json($json, $status);
    }

    private function _paymentToken(&$json)
    {
        $info = $json['receipt']['payment_id'] . ':'
            . $json['receipt']['user_id'] . ':'
            . $json['receipt']['point_id'] . ':'
            . $json['receipt']['fuel']->name . ':'
            . $json['receipt']['litre'] . '-';

        $info .= $json['receipt']['sale'] . ':'
            . 0 . ':' // worker tip
            . $this->COMMISSION[1];

        $hash = hash('SHA512', $info . $json['receipt']['session_key']);
        $hash = substr($hash, 0, 32);

        $json['receipt']['payment_token'] = $info . '-' . $hash;
    }
}
