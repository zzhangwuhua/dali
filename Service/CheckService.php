<?php
/**
 * 检查申请
 */

namespace App\Service;

use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;

class CheckService implements BaseService
{
    use PlatformTrait;

    // 总入口
    public function handle($params)
    {
        // 判断第一条记录确定整个请求是申请还是撤销
        if ($params[0]['apply_flag'] == 'NW')
        {
            // 申请
            $this->fun_code = 'ExamRequest';
            $result = $this->apply($params);
            return $result['Header']['ResultCode'] == 0 ? true : false;
        }
        else
        {
            // 撤销
            $this->fun_code = 'ExamRequestStatusChanged';
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

        $data['union_request_no'] = $param['Body']['ExamRequestStatusChanged']['ExamRequest']['Id'];

        $status = $param['Body']['ExamRequestStatusChanged']['ExamRequest']['Status'];

        switch ($status)
        {
            case 'Cancelled': // 撤销
                $status = 'I';
                break;
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
        foreach ($response[0]->getElementsByTagName('ExamReport') as $row)
        {

            $request = $row->getElementsByTagName('ExamRequest')[0];

            $technician = $row->getElementsByTagName('Technician')[0];

            $verify = $row->getElementsByTagName('Verify')[0];

            $data[] = [
                'union_request_no' => $request->getElementsByTagName('Id')[0]->nodeValue,
                'exe_dept_code' => '',
                'exe_dept_name' => '',
                'examiner_id' => $technician->getElementsByTagName('AssignedEntity')[0]->nodeValue,
                'examiner_name' => $technician->getElementsByTagName('AssignedEntity')[0]->attributes[0]->nodeValue,
                'examiner_time' => $technician->getElementsByTagName('Time')[0]->nodeValue,
                'union_reviewer_id' => $verify->getElementsByTagName('AssignedEntity')[0]->nodeValue,
                'union_reviewer_name' => $verify->getElementsByTagName('AssignedEntity')[0]->attributes[0]->nodeValue,
                'union_reviewer_time' => $verify->getElementsByTagName('Time')[0]->nodeValue,
                'check_desc' => $row->getElementsByTagName('Description')[0]->nodeValue,
                'check_conclusion' => $row->getElementsByTagName('Impression')[0]->nodeValue,
                'report_data' => $row->getElementsByTagName('ReportUrl')[0]->nodeValue,
            ];
        }

        toBS('pacsReport', $data);
    }

    // 处理撤销消息体
    public function getCancelBody($param)
    {
        $xml = <<<EOF
<Body>
     <ExamRequestStatusChanged>
        <ExamRequest>
            <!--终端号-->
            <terminalno>{{terminalno}}</terminalno>
            <!--渠道-->
            <source>{{source}}</source>
            <!--检验申请ID-->
            <Id>{{union_request_no}}</Id>
            <!--医嘱状态代码，此处固定为[Cancelled]-->
            <Status name="取消">Cancelled</Status>
            <!--诊室名称（5-登记时传）-->
            <ExamRoomName></ExamRoomName>
            <!--诊室代码（5-登记时传）-->
            <ExamRoomNo></ExamRoomNo>
            <!--检查号序（5-登记时传）-->
            <ExamQueue></ExamQueue>
        </ExamRequest>
        <Operator>
            <!--申请时间-->
            <Time>{{time}}</Time>
            <!--操作者电子签章-->
            <Signature></Signature>
            <!--申请医师工号-->
            <AssignedEntity name="{{operator_name}}">{{operator_id}}</AssignedEntity>
        </Operator>
    </ExamRequestStatusChanged>
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
<ExamRequest>
    <!--多重耐药状态-->
    <Mdro Mdro=""></Mdro>
    <!--检查部位-->
    <ExamPostion>{{his_union_body_site}}</ExamPostion>
    <Specimen>
        <!--标本采集时间-->
        <SpecimenCollectionTime></SpecimenCollectionTime>
        <!--标本固定时间-->
        <FixedTime></FixedTime>
        <!--标本固定液-->
        <FixedLiquid></FixedLiquid>
        <!--标本详情(送检标本的部位、名称、数量等的详细说明)-->
        <Detail></Detail>
        <!--标本类型-->
        <Type></Type>
    </Specimen>
    <!--手术所见-->
    <OperationFindings></OperationFindings>
    <!--病理检查类型-->
    <PathologyType></PathologyType>
    <!--床边标识-->
    <BedSide></BedSide>
    <!--需要胶片-->
    <PrintFilm>0</PrintFilm>
    <!--送检要求-->
    <ExamRequirement></ExamRequirement>
    <!--检查方法描述-->
    <ExamMethod>{{his_union_name}}</ExamMethod>
    <!--病历摘要-->
    <MrSummary></MrSummary>
    <!--医嘱计价条目-->
    <BillItem>
        <!--实收金额（折后金额）-->
        <charges>{{union_real_fee}}</charges>
        <!--费用确认标识-->
        <Status></Status>
        <!--计费项目记录Id-->
        <Id></Id>
        <!--计价金额-->
        <Costs>{{union_fee}}</Costs>
        <!--计价数量-->
        <Quantity>1</Quantity>
        <!--项目价格-->
        <Price>{{union_fee}}</Price>
        <!--计价单位-->
        <Unit></Unit>
        <!--计价项目规格-->
        <Spec></Spec>
        <!--计价项目名称-->
        <Name>{{his_union_name}}</Name>
        <!--计价项目系统内部代码-->
        <Code>{{union_code}}</Code>
        <!--计价项目类别代码-->
        <Class name=""></Class>
    </BillItem>
    <!--申请单编号-->
    <Id>{{union_request_no}}</Id>
    <!--优先级代码-->
    <PriorityCode name=""></PriorityCode>
    <!--诊断描述-->
    <Diagnosis diagDate="" name=""></Diagnosis>
    <!--主诉-->
    <ChiefComplaint></ChiefComplaint>
    <ProblemList>
        <!--症状描述内容-->
        <Text></Text>
        <!--症状开始时间-->
        <StartTime></StartTime>
        <!--症状结束时间-->
        <EndTime></EndTime>
    </ProblemList>
    <!--检查类别代码-->
    <ExamClass name=""></ExamClass>
    <!--检查项目代码-->
    <Item name="{{his_union_name}}">{{union_code}}</Item>
    <!--住院医嘱ID-->
    <InpOrderId></InpOrderId>
    <!--计划检查日期-->
    <ScheduleDate>{{time}}</ScheduleDate>
    <!--申请执行科室-->
    <PerformDept name="{{exe_dept_name}}">{{exe_dept_code}}</PerformDept>
    <!--申请执行机构-->
    <PerformOrg></PerformOrg>
    <!--申请医师工号-->
    <RequestDoctor name="{{operator_name}}">{{operator_id}}</RequestDoctor>
    <!--申请科室代码-->
    <RequestDept name="{{apply_dept_name}}">{{apply_dept_code}}</RequestDept>
    <!--申请时间-->
    <RequestTime>{{time}}</RequestTime>
    <!--申请机构代码-->
    <RequestOrg name="体检中心">01</RequestOrg>
    <!--申请单状态-->
    <Status name="生效计费">2</Status>
    <!--高风险标记-->
    <HighRiskSign></HighRiskSign>
</ExamRequest>
EOF;
        $main_body = '';
        foreach ($params as $param)
        {
            $main_body .= strtr($xml, [
                '{{union_real_fee}}' => $param['union_real_fee'],
                '{{union_fee}}' => $param['union_fee'],
                '{{his_union_name}}' => $param['his_union_name'],
                '{{union_code}}' => $param['union_code'],
                '{{union_request_no}}' => $param['union_request_no'],
                '{{operator_name}}' => $param['operator_name'],
                '{{operator_id}}' => $param['operator_id'],
                '{{apply_dept_name}}' => $param['apply_dept_name'],
                '{{apply_dept_code}}' => $param['apply_dept_code'],
                '{{exe_dept_name}}' => $param['exe_dept_name'],
                '{{exe_dept_code}}' => $param['exe_dept_code'],
                '{{his_union_body_site}}' => $param['his_union_body_site'],
                '{{time}}' => $this->time
            ]);
        }
        return $main_body;
    }
}