<?php

namespace App\Http\Controllers\V1;

use Toolly\Kee;

class CPanelController extends Controller
{
    public function point_session($point)
    {
        $sessionKey = Kee::createSecret(43);

        $qr = $point . ':' . $sessionKey;
        return $qr;
    }
}
