<?php
/**
 * 条码打印状态同步
 */
namespace App\Service;

use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;
use Illuminate\Support\Facades\DB;

class PrintService implements BaseService
{
    const UserCode = '510561';
    use PlatformTrait;

    // 总入口
    public function handle($params)
    {
        $param = json_decode($params['param'],true);

        if ($param[0]['apply_type'] == 'NW') {
            return $this->sendPrintStatus($param);
        } else {
            return success();
        }
    }

    private function sendPrintStatus($param)
    {
        $sendData = [
            'SttDateTime'    => date('Y-m-d H:i:s'),
            'OrdItemId'  => $param[0]['fee_request_code'],
            'UserId'    => self::UserCode,
            'ExecFlag' => $param[0]['apply_type'] == 'NW' ? '1':'0'
        ];

        $sendXml =  arrayToXml($sendData,'Request');

        try {
            $res =  $this->requestHis($sendXml,'MES0064');//MES0881
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