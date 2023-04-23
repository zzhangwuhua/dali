<?php
/**
 * 查找档案
 */

namespace App\Service;


use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;

class ArchLookupService implements BaseService
{
    use PlatformTrait;

    // 总入口
    public function handle($params)
    {
        $this->fun_code = 'GetPatient';
        return $this->apply($params);
    }

    // 基础信息
    private function packingBasic($param)
    {
        return '';
    }

    // 处理主循环体
    private function handleMainBody($params)
    {
        $xml = <<<EOF
 <Parms>
    <!--患者诊疗卡类型-->
    <MedCardType></MedCardType>
    <!--患者诊疗卡号-->
    <MedCardId></MedCardId>
    <!--患者ID-->
    <Id>{{medical_record_no}}</Id>
    <!--患者姓名-->
    <Name>{{cust_name}}</Name>
    <!--身份证号-->
    <IdNo>{{cust_id_card}}</IdNo>
    <!--页码-->
    <PageNo></PageNo>
</Parms>
EOF;
        return strtr($xml, [
            '{{medical_record_no}}' => $params['medical_record_no'] ?? '',
            '{{cust_name}}' => $params['cust_name'],
            '{{cust_id_card}}' => $params['cust_id_card']
        ]);
    }
}