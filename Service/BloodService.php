<?php
/**
 * 采血状态同步
 */
namespace App\Service;

use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;
use Illuminate\Support\Facades\DB;

class BloodService implements BaseService
{
    const UserCode = '510561';
    use PlatformTrait;

    // 总入口
    public function handle($params)
    {
        $param = json_decode($params['param'],true);

        if ($param[0]['apply_type'] == 'NW') {
            return $this->sendGetBloodStatus($param);
        } else {
            return success();
        }
    }

    private function sendGetBloodStatus($param)
    {
        $sendData = [
            'Barcode'    => $param[0]['union_barcode'],
            'UserId'    => self::UserCode,
        ];

        $sendXml =  arrayToXml($sendData,'Request');

        try {
            $res =  $this->requestHis($sendXml,'MES0066');//MES0881
            if ($res['ResultCode'] == 0) {
                return success();
            } else {
                $resultContent =  strtr($res['ResultContent'],['<![CDATA[' => '',']]>' => '']);
                $resultContent =  xmlToArray($resultContent);
                return fail($resultContent['ResultContent']);
            }
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

}