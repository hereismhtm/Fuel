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
                'vendorData' => [
                    'vendors.id(vendor_id) [Int]',
                    $L . '_vendor(vendor)',
                    'category',
                    'vendors.' . $L . '_contact(vendor_contact)',
                ],
                'branchData' => [
                    $L . '_branch(branch)',
                    $L . '_address(address)',
                    'branches.' . $L . '_contact(branch_contact)',
                    'prices',
                    'prices_updated_at',
                ],
                'pointData' => [
                    'employee' => [
                        $L . '_fname(first_name)',
                        $L . '_lname(last_name)',
                    ],
                    'points.id(point_id) [Int]',
                    'mode',
                ],
            ],
            ['mac' => $worker]
        );

        $status = 422;
        $json = [];
        if (count($res) == 1) {
            $prices_confirmed = Carbon::parse(
                $res[0]['branchData']['prices_updated_at']
            )->diffInMinutes() >= 10;

            if ($res[0]['pointData']['mode'] == 1 && $prices_confirmed) {
                $json = $res[0];

                $price = $fuel->price($json['branchData']['prices']);
                $json['pointData']['fuel'] = $fuel;
                $json['pointData']['litre'] = $litre;
                $json['pointData']['price'] = $price;
                $json['pointData']['sale'] = number_format($litre * $price, 2);
                $json['pointData']['commission'] = (string) $this->COMMISSION[0];

                unset($json['pointData']['mode']);
                unset($json['branchData']['prices']);
                unset($json['branchData']['prices_updated_at']);

                $status = 200;
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
        $json = [];
        if ($res != null) {
            if ($res['mode'] == 1) {
                $json = $res;

                $price = $_fuel->price($json['prices']);
                $json['user_id'] = $this->user->id();
                $json['point_id'] = (int) $_point;
                $json['fuel'] = $_fuel;
                $json['litre'] = number_format($_litre, 2, '.', '');
                $json['price'] = $price;

                if ($_price == $price) {
                    $sale = number_format($_litre * $price, 2, '.', '');
                    $json['sale'] = $sale;
                    $json['commission'] = (string) $this->COMMISSION[0];
                    $json['employee'] = "{$json['first_name']} {$json['last_name']}";
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
                                    'user_id' => $json['user_id'],
                                    'point_id' => $_point,
                                    'fuel' => $json['fuel']->name,
                                    'litre' => $json['litre'],
                                    'price' => $json['price'],
                                    'sale' => $sale_gems,
                                    'emp_c' => $this->COMMISSION[1],
                                    'bra_c' => $this->COMMISSION[2],
                                    'app_c' => $this->COMMISSION[3],
                                ]
                            );
                            if ($res->rowCount() != 1) {
                                return false;
                            }
                            $json['payment_id'] = (int) ($year . $database->id());
                            $json['payment_date'] = $database->get(
                                'payments' . $year,
                                'created_at',
                                ['id' => $database->id()]
                            );
                            $this->_paymentToken($json);
                        });

                        $status = isset($json['payment_token']) ? 200 : 507;
                    } else {
                        $json['message'] = 'No enough credit';
                    }
                } else {
                    $json['message'] = 'Fuel price mismatch, try again';
                }

                unset($json['first_name']);
                unset($json['last_name']);
                unset($json['session_key']);
                unset($json['prices']);
                unset($json['mode']);
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
        $info = $json['payment_id'] . ':'
            . $json['user_id'] . ':'
            . $json['point_id'] . ':'
            . $json['fuel']->name . ':'
            . $json['litre'] . '-';

        $info .= $json['sale'] . ':'
            . 0 . ':' // worker tip
            . $this->COMMISSION[1];

        $hash = hash('SHA512', $info . $json['session_key']);
        $hash = substr($hash, 0, 32);

        $json['payment_token'] = $info . '-' . $hash;
    }
}
