<?php


namespace App\Service;


use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;

class OrderRegisterService implements BaseService
{
    use PlatformTrait;

    public function handle($params)
    {
        $this->fun_code = 'OutpVisitRegistryAdd';
        $result = $this->apply($params);
        return $result['Header']['ResultCode'] == 0 ? returnSuccess() : returnFail($result['Header']['ResultMsg']);
    }

    public function packingBasic($param)
    {
        return '';
    }

    public function handleMainBody($params)
    {
        $xml = <<<EOF
<OutpVisitRegistry>
    <Tj>
        <!--终端号-->
        <terminalno>{{terminalno}}</terminalno>
        <!--渠道-->
        <source>{{source}}</source>
        <!--团体体检单人指导价-->
        <costs></costs>
        <!--团体名称（个人体检时为空）-->
        <groupname></groupname>
        <!--团体ID（个人体检时为空）-->
        <groupid></groupid>
    </Tj>
    <!--门急诊就诊流水号/体检号-->
    <Id>{{VisitId}}</Id>
    <!--就诊类型代码（1-门诊，2-急诊，4-体检）-->
    <VisitType name="体检">4</VisitType>
    <Patient>
        <!--患者ID-->
        <Id>{{medical_record_no}}</Id>
        <!--患者姓名-->
        <Name>{{cust_name}}</Name>
        <!--性别代码-->
        <Sex name="{{SexName}}">{{SexCode}}</Sex>
        <!--出生日期-->
        <BirthDay>{{BirthDay}}</BirthDay>
        <!--就诊时年龄-->
        <Age unit="{{AgeUnit}}">{{Age}}</Age>
    </Patient>
    <!--就诊次数-->
    <VisitTimes>1</VisitTimes>
    <!--就诊日期-->
    <VisitDate>{{time}}</VisitDate>
    <!--挂号类别代码（如专家、普通等）-->
    <ClinicType name=""></ClinicType>
    <!--医疗保险类别(本次就诊)代码-->
    <InsuranceType name=""></InsuranceType>
    <!--就诊科室代码-->
    <VisitDept name="体检科">001</VisitDept>
    <!--医师编号-->
    <Doctor name=""></Doctor>
    <!--就诊机构代码-->
    <VisitOrg 体检中心="体检科">4000</VisitOrg>
    <!--初诊复诊标识(1-初诊,0-复诊)-->
    <IsFistVisit></IsFistVisit>
    <!--登记状态(1-登记,99-取消)-->
    <VisitStatus>1</VisitStatus>
    <!--预约id-->
    <AppointId></AppointId>
    <!--人员类别-->
    <identity></identity>
</OutpVisitRegistry>
<Operator>
    <!--操作时间-->
    <Time>{{time}}</Time>
    <!--操作人员工号-->
    <AssignedEntity name="{{operator_name}}">{{operator_id}}</AssignedEntity>
</Operator>
EOF;

        $param = $params[0];

        switch ($param['exam_type'])
        {
            case 0:
                $source = '团体体检';
                break;
            case 1:
                $source = '个人体检';
                break;
            case 2:
                $source = '职业检';
                break;
            default:
                $source = '个人体检';
        }

        $main_body = strtr($xml, [
            '{{terminalno}}' => config('standapi.platform_ip'),
            '{{source}}' => $source,
            '{{VisitId}}' => $param['medical_record_no'] . '_1',
            '{{medical_record_no}}' => $param['medical_record_no'],
            '{{cust_name}}' => $param['cust_name'],
            '{{SexCode}}' => $param['cust_sex'],
            '{{SexName}}' => $param['cust_sex'] == 1 ? '男' : '女',
            '{{BirthDay}}' => $param['cust_birthday'],
            '{{Age}}' => howOld($param['cust_birthday']),
            '{{AgeUnit}}' => '岁',
            '{{operator_id}}' => $param['operator_id'],
            '{{operator_name}}' => $param['operator_name'],
            '{{time}}' => $this->time
        ]);
        return $main_body;
    }
}