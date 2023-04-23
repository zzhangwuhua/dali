<?php
/**
 * 建档
 */

namespace App\Service;


use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;
use App\Service\ChangIngService;

class ArchCreateService implements BaseService
{
    use PlatformTrait;

    public $patient_id;

    public function handle($params)
    {
//        $this->fun_code = 'PatientRegistryAdd';
//        return $this->apply($params);
        //建档

        $hisDb = config('standapi.his');

        $link = oci_connect($hisDb['user'], $hisDb['password'], $hisDb['host'], 'UTF8');

        $ChangIngService = new ChangIngService();

        $create = $ChangIngService->sendPutRecord($link, $params, $params["operator_code"]);

        return $create;

    }

    // 基础信息
    private function packingBasic($param)
    {
        $platform_ip = config('standapi.platform_ip');
        return <<<EOF
<!--终端号-->
<terminalno>$platform_ip</terminalno>
<!--渠道-->
<source>个人体检</source>
EOF;
    }

    // 处理主循环体
    private function handleMainBody($params)
    {
        $xml = <<<EOF
<Patient>
    <!--证件类别-->
    <idType>{{card_type}}</idType>
    <!--患者ID-->
    <Id>{{id}}</Id>
    <!--患者姓名-->
    <Name>{{cust_name}}</Name>
    <!--性别代码(GB/T2261.1-2003)-->
    <Sex Name="{{cust_sex_name}}">{{cust_sex}}</Sex>
    <!--出生日期-->
    <BirthDay>{{cust_birthday}}</BirthDay>
    <!--身份证号-->
    <IdNo>{{cust_id_card}}</IdNo>
    <!--国籍代码(GB/T2659-2000)-->
    <Citizenship name="中国">156</Citizenship>
    <!--民族代码(GB3304-1991)-->
    <Nation name=""></Nation>
    <!--职业代码(GB/T2261.4-2003)-->
    <Occupation name=""></Occupation>
    <!--婚姻状况代码(GB/T2261.2-2003)-->
    <MaritalStatus name="{{MaritalStatusName}}">{{MaritalStatusCode}}</MaritalStatus>
    <!--电话号码-->
    <PhoneNo>{{cust_mobile}}</PhoneNo>
    <Address>
        <!--地址完整描述-->
        <Line></Line>
        <!--省/自治区/直辖市-->
        <State></State>
        <!--市-->
        <City></City>
        <!--县/区-->
        <County></County>
        <!--乡/镇/街道-->
        <TownShip></TownShip>
        <!--街/村-->
        <Street></Street>
        <!--门牌号-->
        <HouseNumber></HouseNumber>
        <!--邮编-->
        <PostalCode></PostalCode>
    </Address>
    <Contact>
        <!--联系人姓名-->
        <Name>{{cust_name}}</Name>
        <!--与患者关系代码(GB/T4761-2008)-->
        <Relationship name=""></Relationship>
        <!--联系人电话-->
        <Phone>{{cust_mobile}}</Phone>
        <Address>
            <!--地址完整描述-->
            <Line></Line>
        </Address>
    </Contact>
    <!--医疗保险类别代码-->
    <InsuranceType name=""></InsuranceType>
    <!--居民健康档案号-->
    <HealthRecId></HealthRecId>
    <!--居民健康卡号-->
    <HealthCardNo></HealthCardNo>
    <!--建档时间-->
    <CreateTime>{{time}}</CreateTime>
    <!--操作员编号-->
    <CreatedBy name="{{operator_name}}">{{operator_id}}</CreatedBy>
    <!--院区编号-->
    <Org name="丹江口第一医院">001</Org>
</Patient>
EOF;
        switch ($params['cust_card_type_code'])
        {
            case 1:
                $card_type = '身份证';
                break;
            case 2:
                $card_type = '护照';
                break;
            case 3:
                $card_type = '回乡证';
                break;
            case 4:
                $card_type = '台胞证';
                break;
            default:
                $card_type = '其他';
        }

        switch ($params['cust_marriage_code'])
        {
            case 1:
                $marriage_code = '10';
                $marriage_name = '未婚';
                break;
            case 2:
                $marriage_code = '20';
                $marriage_name = '已婚';
                break;
            case 3:
                $marriage_code = '30';
                $marriage_name = '丧偶';
                break;
            case 4:
                $marriage_code = '40';
                $marriage_name = '离婚';
                break;
            default:
                $marriage_code = '90';
                $marriage_name = '其它';
        }

        $this->patient_id = 'TJ' . str_pad($params['cust_num'], 8, '0', STR_PAD_LEFT);

        return strtr($xml, [
            '{{id}}' => $this->patient_id,
            '{{card_type}}' => $card_type,
            '{{cust_name}}' => $params['cust_name'],
            '{{cust_sex_name}}' => $params['cust_sex_code'] == 1 ? '男' : '女',
            '{{cust_sex}}' => $params['cust_sex_code'],
            '{{cust_birthday}}' => $params['cust_birthday'],
            '{{cust_id_card}}' => $params['cust_id_card'],
            '{{MaritalStatusName}}' => $marriage_code,
            '{{MaritalStatusCode}}' => $marriage_name,
            '{{cust_mobile}}' => $params['cust_mobile'],
            '{{cust_name}' => $params['cust_name'],
            '{{operator_name}}' => $params['operator_name'],
            '{{operator_id}}' => $params['operator_id'],
            '{{time}}' => $this->time
        ]);
    }
}
