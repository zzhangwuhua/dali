<?php
/**
 * 检验申请
 */
namespace App\Service;

use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;

class LisService implements BaseService
{
    use PlatformTrait;

    // 总入口
    public function handle($params)
    {
        if ($params[0]['apply_flag'] == 'NW')
        {
            // 申请
            $this->fun_code = 'LabTestRequest';
            $result = $this->apply($params);
            return $result['Header']['ResultCode'] == 0 ? true : false;
        }
        else
        {
            // 撤销
            $this->fun_code = 'LabTestRequestStatusChanged';
            return $this->cancel($params[0]);
        }
    }

    // 状态接收
    public static function status($param)
    {
        $data = [
            // 申请单号
            'union_request_no' => '',
            // 条码号
            'union_barcode' => '',
            // 信息流水号，（申请单号，条码号，信息流水号只能存在一个不为空，互斥关系）
            'apply_no' => '',
            // 执行状态，U登记 E执行 I撤销
            'status' => '',
            // 备注
            'comments' => '',
        ];

        $data['union_request_no'] = $param['Body']['LabTestRequestStatusChanged']['LabTestRequest']['Id'];

        $status = $param['Body']['LabTestRequestStatusChanged']['LabTestRequest']['Status'];

        // 1新开2生效3取消4已打印5登记9报告发布
        switch ($status)
        {
            case 3: // 撤销
                $status = 'I';
                break;
            case 4: // 已打印
                $status = 'E';
                break;
            case 5: // 登记
                $status = 'U';
                break;
            default:
                logMessage($status . ' 状态不做处理', 'LisStatus');
        }

        $data['status'] = $status;

        toBS('performStatus', $data);
    }

    // 报告接收
    public static function report($xml)
    {
        try{
            $doc = new \DomDocument();
            $doc->loadXML($xml);
        }catch (\Exception $e){
            return ['error' => $e->getMessage()];
        }

        $data = [];
        // 获取所有数据行
        $response = $doc->getElementsByTagName('Request');

        if (!isset($response[0]))
        {
            return ['error' => '无法解析参数！'];
        }
        // 循环数据行，从每一行中取出数据列
        foreach ($response[0]->getElementsByTagName('LabTestReport') as $row)
        {
            $result = $row->getElementsByTagName('LabTestResult')[0];
            $result_request = $result->getElementsByTagName('Request')[0];

            $author = $row->getElementsByTagName('Author')[0];

            $verify = $row->getElementsByTagName('Verify')[0];

            // 获取定性和定量结果
            $quantitative_result = $result->getElementsByTagName('QuantitativeResult')[0]->nodeValue;
            $qualitative_result = $result->getElementsByTagName('QualitativeResult')[0]->nodeValue;
            if ($quantitative_result)
            {
                $item_check_result = $quantitative_result;
                $item_unit = $result->getElementsByTagName('QuantitativeResult')[0]->attributes[0]->nodeValue;
            }
            else
            {
                $item_check_result = $qualitative_result;
                $item_unit = '';
            }

            $data[] = [
                'union_request_no' => $result_request->getElementsByTagName('Id')[0]->nodeValue,
                'union_code' => $result_request->getElementsByTagName('Item')[0]->nodeValue,
                'union_barcode' => '',
                'exe_dept_code' => '',
                'exe_dept_name' => '',
                'union_examiner_id' => $author->getElementsByTagName('AssignedEntity')[0]->nodeValue,
                'union_examiner_name' => $author->getElementsByTagName('AssignedEntity')[0]->attributes[0]->nodeValue,
                'union_examiner_time' => $author->getElementsByTagName('Time')[0]->nodeValue,
                'union_reviewer_id' => $verify->getElementsByTagName('AssignedEntity')[0]->nodeValue,
                'union_reviewer_name' => $verify->getElementsByTagName('AssignedEntity')[0]->attributes[0]->nodeValue,
                'union_reviewer_time' => $verify->getElementsByTagName('Time')[0]->nodeValue,
                'item_code' => $result->getElementsByTagName('Item')[0]->nodeValue,
                'item_name' => $result->getElementsByTagName('Item')[0]->attributes[0]->nodeValue,
                'item_check_result' => $item_check_result,
                'exception_tag' => $result->getElementsByTagName('DangerFlag')[0]->nodeValue,
                'item_unit' => $item_unit,
                'item_ref_value' => $result->getElementsByTagName('RefValue')[0]->getElementsByTagName('Text')[0]->nodeValue,
            ];
        }

        toBS('lisReport', $data);
    }

    // 处理撤销消息体
    public function getCancelBody($param)
    {
        $xml = <<<EOF
<Body>
     <LabTestRequestStatusChanged>
        <LabTestRequest>
            <!--终端号-->
            <terminalno>{{terminalno}}</terminalno>
            <!--渠道-->
            <source>{{source}}</source>
            <!--检验申请ID-->
            <Id>{{union_request_no}}</Id>
            <!--医嘱状态代码，此处固定为[Cancelled]-->
            <Status name="取消">Cancelled</Status>
        </LabTestRequest>
        <Operator>
            <!--操作时间-->
            <Time>{{time}}</Time>
            <!--电子签章-->
            <Signature></Signature>
            <!--操作人编号-->
            <AssignedEntity name="{{operator_name}}">{{operator_id}}</AssignedEntity>
        </Operator>
    </LabTestRequestStatusChanged>
</Body>
EOF;
        switch ($param['exam_type'])
        {
            case 0:
                $source = '个人体检';
                break;
            case 1:
                $source = '团体体检';
                break;
            case 2:
                $source = '职业检';
                break;
            default:
                $source = '个人体检';
        }

        return strtr($xml, [
            '{{terminalno}}' => config('standapi.platform_ip'),
            '{{source}}' => $source,
            '{{union_request_no}}' => $param['union_request_no'],
            '{{time}}' => $this->time,
            '{{operator_name}}' => $param['operator_name'],
            '{{operator_id}}' => $param['operator_id'],
        ]);
    }

    // 条码回传
    public static function barcode($param)
    {
        $data = [];
        $union_barcode = $param['Body']['LabTestSpecimenBound']['Specimen']['Id'];

        if (isset($param['Body']['LabTestSpecimenBound']['LabTestRequest'][0]))
        {
            // 合管的返回
            foreach ($param['Body']['LabTestSpecimenBound']['LabTestRequest'] as $value)
            {
                $data[] = [
                    'union_request_no' => $value['Id'],
                    'union_barcode' => $union_barcode
                ];
            }
        }
        else
        {
            // 单条的返回
            $data[] = [
                'union_request_no' => $param['Body']['LabTestSpecimenBound']['LabTestRequest']['Id'],
                'union_barcode' => $union_barcode
            ];
        }

        toBS('lisBarcode', $data);
    }

    // 基础信息
    private function packingBasic($param)
    {
        $basic_xml = <<<EOF
<Tj>
    <!--终端号-->
    <terminalno>{{terminalno}}</terminalno>
    <!--渠道-->
    <source>{{source}}</source>
</Tj>
<!--申请批次号-->
<PaperReqNo>{{PaperReqNo}}</PaperReqNo>
<Patient>
    <!--生理周期-->
    <Cycle></Cycle>
    <!--患者ID-->
    <Id>{{Id}}</Id>
    <!--患者姓名-->
    <Name>{{Name}}</Name>
    <!--性别代码（CF02.01.991）-->
    <Sex name="{{SexName}}">{{SexCode}}</Sex>
    <!--出生日期-->
    <BirthDay>{{BirthDay}}</BirthDay>
    <!--年龄-->
    <Age unit="{{AgeUnit}}">{{Age}}</Age>
    <!--身份证号-->
    <IdNo>{{IdNo}}</IdNo>
    <!--电话号码-->
    <PhoneNo>{{PhoneNo}}</PhoneNo>
    <MedCard>
        <!--诊疗卡类型代码-->
        <Type name="身份证">1</Type>
        <!--诊疗卡号-->
        <Id>{{IdNo}}</Id>
    </MedCard>
</Patient>
<Visit>
    <!--就诊流水号-->
    <Id>{{VisitId}}</Id>
    <!--就诊类型代码-->
    <Type name="体检">4</Type>
    <!--科室代码-->
    <Dept name="{{DeptName}}">{{DeptCode}}</Dept>
    <!--病区代码-->
    <Ward name=""></Ward>
    <!--病房ID-->
    <Room name=""></Room>
    <!--床位ID-->
    <Bed name=""></Bed>
    <!--就诊机构代码-->
    <Org name=""></Org>
</Visit>
EOF;

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

        return strtr($basic_xml, [
            // 患者ID
            '{{Id}}' => $param['medical_record_no'],
            // 患者姓名
            '{{Name}}' => $param['cust_name'],
            // 性别文字
            '{{SexName}}' => $param['cust_sex'] == 1 ? '男' : '女',
            // 性别代码
            '{{SexCode}}' => $param['cust_sex'],
            // 出生日期
            '{{BirthDay}}' => date('Ymd', strtotime($param['cust_birthday'])),
            // 年龄单位
            '{{AgeUnit}}' => '岁',
            // 年龄
            '{{Age}}' => howOld($param['cust_birthday']),
            // 身份证号
            '{{IdNo}}' => $param['cust_id_card'],
            // 电话号码
            '{{PhoneNo}}' => $param['cust_mobile'],
            // 科室名称
            '{{DeptName}}' => '体检科',
            // 科室代码
            '{{DeptCode}}' => '001',
            // 终端号 ip地址
            '{{terminalno}}' => config('standapi.platform_ip'),
            // 渠道 个人体检还是团体体检
            '{{source}}' => $source,
            // 申请批次号，传消息ID
            '{{PaperReqNo}}' => $this->guid,
            // 就诊流水号
            '{{VisitId}}' => $param['medical_record_no'] . '_1',
        ]);

    }

    // 处理主循环体
    private function handleMainBody($params)
    {
        $xml = <<<EOF
<LabTestRequest>
    <!--申请单备注信息-->
    <Remark></Remark>
    <!--住院医嘱ID-->
    <InpOrderId></InpOrderId>
    <!--计费条目-->
    <BillItem>
        <!--折后金额-->
        <charges>{{union_real_fee}}</charges>
        <!--计费项目类别（0-医嘱项目，1-收费项目）-->
        <ItemType>1</ItemType>
        <!--费用确认标识-->
        <Status></Status>
        <!--费用记录唯一id-->
        <Id></Id>
        <!--计价总金额-->
        <Costs>{{union_fee}}</Costs>
        <!--计价数量-->
        <Quantity>1</Quantity>
        <!--项目单价-->
        <Price>{{union_fee}}</Price>
        <!--计价单位-->
        <Unit></Unit>
        <!--计价项目规格-->
        <Spec></Spec>
        <!--计价项目名称-->
        <Name>{{union_name}}</Name>
        <!--计价项目系统内部代码-->
        <Code>{{union_code}}</Code>
        <!--计价项目类别代码-->
        <Class name=""></Class>
    </BillItem>
    <!--申请单编号-->
    <Id>{{union_request_no}}</Id>
    <!--优先级代码-->
    <PriorityCode name=""></PriorityCode>
    <!--西医诊断代码（ICD-10）(CF05.01.990)-->
    <Diagnosis name="" diagDate=""></Diagnosis>
    <!--检验类别代码-->
    <Class name=""></Class>
    <!--标本类别代码-->
    <SpecimenType name="{{sample_type_name}}">{{sample_type_code}}</SpecimenType>
    <!--项目代码-->
    <Item name="{{union_name}}">{{union_code}}</Item>
    <!--检验方法-->
    <Method></Method>
    <!--申请医师工号-->
    <RequestDoctor name="{{operator_name}}">{{operator_id}}</RequestDoctor>
    <!--申请科室代码-->
    <RequestDept name="{{apply_dept_name}}">{{apply_dept_code}}</RequestDept>
    <!--申请时间-->
    <RequestTime>{{time}}</RequestTime>
    <!--申请机构代码-->
    <RequestOrg name="体检中心">01</RequestOrg>
    <!--医嘱状态代码，此处固定为[New]-->
    <Status name="生效计费">2</Status>
    <!--申请执行科室-->
    <PerformDept name="{{exe_dept_name}}">{{exe_dept_code}}</PerformDept>
    <!--申请执行机构-->
    <PerformOrg></PerformOrg>
    <!--高风险标记-->
    <HighRiskSign></HighRiskSign>
</LabTestRequest>
EOF;
        $main_body = '';
        foreach ($params as $param)
        {
            $main_body .= strtr($xml, [
                '{{union_real_fee}}' => $param['union_real_fee'],
                '{{union_fee}}' => $param['union_fee'],
                '{{union_name}}' => $param['union_name'],
                '{{union_code}}' => $param['union_code'],
                '{{union_request_no}}' => $param['union_request_no'],
                '{{sample_type_name}}' => $param['sample_type_name'],
                '{{sample_type_code}}' => $param['sample_type_code'],
                '{{operator_name}}' => $param['operator_name'],
                '{{operator_id}}' => $param['operator_id'],
                '{{apply_dept_name}}' => $param['apply_dept_name'],
                '{{apply_dept_code}}' => $param['apply_dept_code'],
                '{{exe_dept_name}}' => $param['exe_dept_name'],
                '{{exe_dept_code}}' => $param['exe_dept_code'],
                '{{time}}' => $this->time
            ]);
        }
        return $main_body;
    }
}