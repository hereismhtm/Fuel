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
        $output = [];
        if (count($res) == 1) {
            $prices_confirmed = Carbon::parse(
                $res[0]['branchData']['prices_updated_at']
            )->diffInMinutes() >= 10;

            if ($res[0]['pointData']['mode'] == 1 && $prices_confirmed) {
                $output = $res[0];

                $price = $fuel->price($output['branchData']['prices']);
                $output['pointData']['fuel'] = $fuel;
                $output['pointData']['litre'] = $litre;
                $output['pointData']['price'] = $price;
                $output['pointData']['sale'] = number_format($litre * $price, 2);
                $output['pointData']['commission'] = (string) $this->COMMISSION[0];

                unset($output['pointData']['mode']);
                unset($output['branchData']['prices']);
                unset($output['branchData']['prices_updated_at']);

                $status = 200;
            } else {
                $output['message'] = 'Out of service';
            }
        } else {
            $output['message'] = 'Not defined';
        }

        return response()->json($output, $status);
    }

    public function payment(Request $request)
    {
        if (!$this->user->logged()) {
            return response('Unauthorized.', 401);
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
        $output = [];
        if ($res != null) {
            if ($res['mode'] == 1) {
                $output = $res;

                $price = $_fuel->price($output['prices']);
                $output['user_id'] = $this->user->id();
                $output['point_id'] = (int) $_point;
                $output['fuel'] = $_fuel;
                $output['litre'] = number_format($_litre, 2, '.', '');
                $output['price'] = $price;

                if ($_price == $price) {
                    $sale = number_format($_litre * $price, 2, '.', '');
                    $output['sale'] = $sale;
                    $output['commission'] = (string) $this->COMMISSION[0];
                    $output['employee'] = "{$output['first_name']} {$output['last_name']}";
                    $sale_gems = (int) ($sale * 100);
                    $sum_gems = $sale_gems + ($this->COMMISSION[0] * 100);

                    if ($sum_gems <= $this->user->credit) {
                        $user = $this->user;

                        $this->db->action(function ($database) use (
                            $user,
                            $_point,
                            &$output,
                            $sale_gems,
                            $sum_gems,
                        ) {
                            $user->setCredit('-', $sum_gems);
                            $year = substr(Carbon::now()->year, 2);
                            $res = $database->insert(
                                'payments' . $year,
                                [
                                    'user_id' => $output['user_id'],
                                    'point_id' => $_point,
                                    'fuel' => $output['fuel']->name,
                                    'litre' => $output['litre'],
                                    'price' => $output['price'],
                                    'sale' => $sale_gems,
                                    'emp_c' => $this->COMMISSION[1],
                                    'bra_c' => $this->COMMISSION[2],
                                    'app_c' => $this->COMMISSION[3],
                                ]
                            );
                            if ($res->rowCount() != 1) {
                                return false;
                            }
                            $output['payment_id'] = (int) ($year . $database->id());
                            $output['payment_date'] = $database->get(
                                'payments' . $year,
                                'created_at',
                                ['id' => $database->id()]
                            );
                            $this->_paymentToken($output);
                        });

                        $status = isset($output['payment_token']) ? 200 : 507;
                    } else {
                        $output['message'] = 'No enough credit';
                    }
                } else {
                    $output['message'] = 'Fuel price mismatch, try again';
                }

                unset($output['first_name']);
                unset($output['last_name']);
                unset($output['session_key']);
                unset($output['prices']);
                unset($output['mode']);
            } else {
                $output['message'] = 'Out of service';
            }
        } else {
            $output['message'] = 'Not defined';
        }

        return response()->json($output, $status);
    }

    private function _paymentToken(&$output)
    {
        $info = $output['payment_id'] . ':'
            . $output['user_id'] . ':'
            . $output['point_id'] . ':'
            . $output['fuel']->name . ':'
            . $output['litre'] . '-';

        $info .= $output['sale'] . ':'
            . 0 . ':' // worker tip
            . $this->COMMISSION[1];

        $hash = hash('SHA512', $info . $output['session_key']);
        $hash = substr($hash, 0, 32);

        $output['payment_token'] = $info . '-' . $hash;
    }
}
