<?php

namespace App\Service\Platform\WebApi;

use App\Service\Common\BasePlatformAbstract;
use App\Service\DataProvider\JsonDataProvider;
use App\Service\Common\BaseDataProviderinterface;
use SoapClient;

/**
 * @description
 * 集成平台
 *
 * @since 2021-07-28
 */
class IPMService extends BasePlatformAbstract
{

    const SUCCESS_CODE = '1';
    const ERROR_CODE = '0';

    const APPLY_DR_CODE = '00205004';
    const APPLY_DR_NAME = '李绒';

    private $dataProvider;

    public function __construct(BaseDataProviderinterface $dataProvider = null)
    {
        $this->dataProvider = $dataProvider ?? JsonDataProvider::getInstance();
    }

    //region ---------------------------------------------------------------- SOAP_1_2 --------------------------------
    public function ipm_service($params)
    {
        logInfo("[\$MessageCode]{$params->MessageCode};[\$MessageData]" . $params->MessageData, [], "soap_server");
        $data = $params->MessageData;
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }

        $tradeCode = $params->MessageCode;
        $body = $data;

        switch ($tradeCode) {
            case self::FEE_STATUS:
                $params = $this->filterFeeStatus($body);
                $res = toBS('check_fee_status', $params);
                break;
            case self::CHECK_STATUS:
                $params = $this->filterCheckStatus($body);
                $res = toBS('perform_status', $params);
                break;
            case self::LIS_STATUS:
                $params = $this->filterLisStatus($body);
                $res = toBS('perform_status', $params);
                break;
            case self::CHECK_REPORT:
                $params = $this->filterCheckReport($body);
                $res = toBS('check_report', $params);
                break;
            case self::LIS_REPORT:
                $params = $this->filterLisReport($body);
                $res = toBS('lis_report', $params);
                break;
            case self::MIC_REPORT:
                $params = $this->filterMicReport($body);
                $res = toBS('mic_report', $params);
                break;
            default:
                $res['status'] = true;
        }
        logInfo("[处理数据]" . json_encode($params, 320), [], "soap_server");
        if ($res['status']) {
            return $this->getResp([
                "status" => self::SUCCESS_CODE,
                "msg" => "",
            ]);
        }
        $res = $this->getResp([
            "status" => self::ERROR_CODE,
            "msg" => $res['msg'] ?? '',
        ]);
        logInfo("[返回数据]" . json_encode($res, 320), [], "soap_server");
        return $res;
    }

    private function getResp(array $params)
    {
        $data = $this->dataProvider->encryptData([
            'message_code' => BasePlatformAbstract::RESPONSE,
            'data' => [
                'Response' => [
                    'Header' => '',
                    'Body' => [
                        'ResultCode' => $params['status'],
                        'ResultContent' => $params['msg'],
                    ]
                ]
            ]
        ]);

        return ['Response' => $data];
    }



    private function filterMicReport($params)
    {
        // TODO 待解析微生物报告
        $retData = [];
        foreach ($params['BioResults'] as $item) {
            $result = '';
            if (isset($item['AntiResults'])) {
                foreach ($item['AntiResults'] as $anti) {
                }
            }
            $retData[] = [
                'union_barcode' => $params['BarCode'],
                "medical_record_no" => $params['PatientId'],
                "cust_name" => $params['PatientName'],
                "clinic_id" => $params['VisitNo'],
                // "" => $params['OrderId'],
                //                "doctor_id" => $params['ApplyNo'],
                "doctor_id" => $params['OrderNo'],
                // "report_no" => $params['ReportId'],
                "status" => $params['LabStatus'],
                "examiner_name" => $params['ReportDoctorName'],
                'examiner_time' => $params['ReportTime'],
                'reviewer_name' => $params['VerifierDoctorName'] ?: $params['ReportDoctorName'],
                'reviewer_time' => $params['VerifierTime'],
                // 'check_desc' => $item['Description'],
                // 'check_conclusion' => $item['Recommendation'],
                // 'report_data' => $item['ReportUrl'],
                'item_code' => $item['BioCode'],
                'item_name' => $item['BioName'],
                'item_check_result' => $result,
                // 'item_unit' => $item['Units'],
                // 'item_ref_value' => $item['UpperAndlowerLimits'],
                // 'exception_tag' => $item['AbnormalIndicator'],
                'result_date_time' => $item['ResultDateTime'], // 检验日期及时间
                // 'item_ill_value' => $item['CrisisValue'],

            ];
        }
        return $retData;
    }

    private function filterLisReport($params)
    {
        $retData = [];
        foreach ($params['Results'] as $item) {
            //            $item['AbnormalIndicator'];
            $tag = '';
            if ($item['AbnormalIndicator'] == '1H') {
                $tag = '↑';
            }
            if ($item['AbnormalIndicator'] == '2H') {
                $tag = '↑↑';
            }
            if ($item['AbnormalIndicator'] == '3H') {
                $tag = '↑↑↑';
            }
            if ($item['AbnormalIndicator'] == '1L') {
                $tag = '↓';
            }
            if ($item['AbnormalIndicator'] == '2L') {
                $tag = '↓↓';
            }
            if ($item['AbnormalIndicator'] == '3L') {
                $tag = '↓↓↓';
            }
            if ($item['AbnormalIndicator'] == 'P') {
                $tag = '阳性';
            }
            if ($item['AbnormalIndicator'] == 'N') {
                $tag = '';
            }
            if ($item['AbnormalIndicator'] == 'Q') {
                $tag = '弱阳性/可疑';
            }
            $retData[] = [
                // "OperType" => "",
                'union_barcode' => $params['BarCode'],
                "medical_record_no" => $params['PatientId'],
                // "cust_name" => $params['PatientName'],
                "clinic_id" => $params['VisitNo'],
                // "" => $params['OrderId'],
                //                "doctor_id" => $params['ApplyNo'],
                "doctor_id" => $params['OrderNo'],
                // "report_no" => $params['ReportId'],
                "status" => $params['LabStatus'],
                "examiner_name" => $params['ReportDoctorName'],
                'examiner_time' => $params['ReportTime'],
                'reviewer_name' => $params['VerifierDoctorName'] ?: $params['ReportDoctorName'],
                'reviewer_time' => $params['VerifierTime'],
                // 'check_desc' => $item['Advice'],
                // 'check_conclusion' => $item['Recommendation'],
                // 'report_data' => $item['ImagePath'],
                'item_code' => $item['ItemCode'],
                'item_name' => $item['ItemName'],
                'item_check_result' => $item['Result'],
                'item_unit' => str_replace('10#', '10^', $item['Units']),
                'item_ref_value' => $item['UpperAndlowerLimits'],
                //                'exception_tag' => $item['AbnormalIndicator'],
                'exception_tag' => $tag,
                'result_date_time' => $item['ResultDateTime'], // 检验日期及时间
                'item_ill_value' => $item['CrisisValue'],
            ];
        }
        return $retData;
    }

    private function filterCheckReport($params)
    {
        $retData = [];
        foreach ($params['Reports'] as $item) {

            $retData[] = [
                // "OperType" => "",
                "medical_record_no" => $params['PatientId'],
                "cust_name" => $params['PatientName'],
                "clinic_id" => $params['VisitNo'],
                // "" => $params['OrderId'],
                "doctor_id" => $params['ExamNo'],
                //                "doctor_id" => $params['ExamNo'],
                "report_no" => $item['ReportId'],
                "status" => $this->getCheckStatus($params['ExamStatus']),
                "examiner_name" => $params['TechnicianName'],
                'examiner_time' => $params['ExecuteTime'],
                'reviewer_name' => $params['VerifierDoctorName'] ?: $params['ReportDoctorName'],
                'reviewer_time' => $params['VerifierTime'],
                'check_desc' => $item['Description'],
                'check_conclusion' => $item['Impression'],
                'report_data' => str_replace('\\', '/', $item['UrlPath']),
            ];
        }
        return $retData;
    }

    private function getLisStatus($exam_status)
    {
        switch ($exam_status) {
            case in_array($exam_status, ['3', '4', '5']):
                return "U";
            case in_array($exam_status, ['6']):
                return "E";
            case in_array($exam_status, ['11']):
                return "I";
            default:
                return "U";
        }
    }

    private function filterLisStatus(array $body)
    {
        $params = [
            'medical_record_no' => $body['PatientId'],
            "clinic_id" => $body['VisitNo'],
            // "" => $item['PatientClass'],
            // "" => $item['OrderNo'],
            "union_request_no" => $body['ApplyNo'],
            "status" => $this->getLisStatus($body['LabStatus']),
            "operator_id" => $body['OperatorId'],
            "operator_name" => $body['OperatorName'],
            "union_execute_time" => $body['OperationTime'],
            "comments" => $body['Remark'],
        ];
        return $params;
    }

    private function getCheckStatus($exam_status)
    {
        switch ($exam_status) {
            case in_array($exam_status, ['3', '4', '5']):
                return "U";
            case in_array($exam_status, ['6', '8', '9']):
                return "E";
            case in_array($exam_status, ['11']):
                return "I";
            default:
                return "U";
        }
    }

    private function filterCheckStatus(array $body)
    {
        $params = [
            'medical_record_no' => $body['PatientId'],
            "clinic_id" => $body['VisitNo'],
            // "" => $item['PatientClass'],
            // "" => $item['OrderNo'],
            "doctor_id" => $body['ExamNo'],
            "status" => $this->getCheckStatus($body['ExamStatus']),
            "operator_id" => $body['OperatorId'],
            "operator_name" => $body['OperatorName'],
            "union_execute_time" => $body['OperationTime'],
            "comments" => $body['Remark'],
        ];

        return $params;
    }

    private function getFeeStatus($feeFlag)
    {
        $map = [
            '1' => '1',
            '2' => '3',
        ];

        return $map[$feeFlag];
    }

    private function filterFeeStatus(array $body)
    {
        $params = [];
        foreach ($body as $item) {
            $feeStatus = $this->getFeeStatus($item['popFlay']);
            $feeNo = $item['rcptNo'];
            $unionPayTime = $item['time'];
            $orderCode = $item['TjSerialNo'];
            $operatorName = $item['operationName'];
            $operatorCode = $item['operationCode'];
            foreach ($item['Items'] as $union) {
                $key = $union['externalId'] . '-' . $feeStatus;
                if (isset($params[$key])) {
                    continue;
                }
                $params[$key] = [
                    'order_code' => $orderCode,
                    'operator_name' => $operatorName,
                    'operator_code' => $operatorCode,
                    'fee_status' => $feeStatus,
                    'fee_no' => $feeNo,
                    'fee_request_code' => $union['externalId'],
                    'union_pay_time' => $unionPayTime,
                    // 'union_code' => $union['itemCode'],
                    // 'union_name' => $union['itemName'],
                    // 'union_pay_fee' => $union['money'],
                ];
            }
        }

        return array_values($params);
    }

    //endregion ---------------------------------------------------------------- SOAP_1_2 END ------------------------------------------------------------------

    public function getSexName($code)
    {
        $map = [
            '0' => '未知',
            '1' => '男',
            '2' => '女',
        ];

        return $map[$code] ?? '未知';
    }

    public function getServerDomain()
    {
        return config('standapi.platform_http_url');
    }

    public function sendToPlatform($params)
    {
        $messageCode = $params['message_code'];
        $postData = $this->dataProvider->encryptData($params);
        logMessage('[请求参数]' . $postData, 'IPMService');

        $client = new SoapClient($this->getServerDomain() . "?wsdl");
        $res = $client->__soapCall('ipm_service', [['MessageCode' => $messageCode, 'MessageData' => $postData]]);
        logMessage('[返回结果]' . json_encode($res, 320), 'IPMService');
        $arr = $this->dataProvider->decryptData($res->Response);
        return $arr;
    }

    /**
     * @description
     *
     * @since 2021-09-24
     * @param array $params
     * @return array
     */
    public function checkArchive($params)
    {
        $archData = [
            'message_code' => self::ARCH_QUERY,
            'data' => [
                'cust_name' => $params['cust_name'],
                'cust_sex' => $params['cust_sex_code'],
                'cust_id_card' => $params['cust_id_card'],
                'cust_type' => $params['cust_card_type_code'],
                'cust_mobile' => $params['cust_mobile'],
            ],
        ];

        $res = $this->sendToPlatform($archData);

        if ($res['Body']['ResultCode'] == self::ERROR_CODE) {
            return ['status' => FAIL_CODE, 'msg' => $res['Body']['ResultContent'] ?? ''];
        }

        $ret_data = [
            'medical_record_no' => $res['Body']['patient_id'] ?? '',
        ];

        // $ret_data = [
        //     'medical_record_no' => $res['Body']['PatientID'] ?? guid(),
        //     'clinic_id' => $res['Body']['VisitNo'] ?? guid(),
        // ];

        return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => $ret_data];
    }

    /**
     * @description
     *
     * @since 2021-09-24
     * @param array $params
     * @return array
     */
    public function createArchive($params)
    {
        $archData = [
            'message_code' => self::ARCH_CREATE,
            'data' => [
                'cust_name' => $params['cust_name'],
                'cust_sex' => $params['cust_sex_code'],
                'cust_id_card' => $params['cust_id_card'],
                'cust_type' => $params['cust_card_type_code'],
                'cust_mobile' => $params['cust_mobile'],
            ],
        ];

        $res = $this->sendToPlatform($archData);

        if ($res['Body']['ResultCode'] == self::ERROR_CODE) {
            return ['status' => FAIL_CODE, 'msg' => $res['Body']['ResultContent'] ?? ''];
        }

        $ret_data = [
            'medical_record_no' => $res['Body']['patient_id'],
        ];

        // $ret_data = [
        //     'medical_record_no' => $res['Body']['PatientID'] ?? guid(),
        //     'clinic_id' => $res['Body']['VisitNo'] ?? guid(),
        // ];

        return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => $ret_data];
    }

    /**
     * @description
     * 检查申请单
     *
     * @since 2021-07-29
     * @param array $NWApply
     * @return array
     */
    public function checkApply($NWApply)
    {
        $totalFee = 0;
        $items = [];
        $no = 1;

        // $testData = [];
        // $doctor_id = getTimeFor17();
        foreach ($NWApply as $item) {

            // $testData[] = [
            //     'union_request_no' => $item['union_request_no'],
            //     'doctor_id' => $doctor_id,
            // ];

            $VisitNo = $item['his_register_num'];
            $PatientID = $item['medical_record_no'];
            $TjSerialNo = $item['order_code'];
            $PatName = $item['cust_name'];
            $ApplyTime = $item['order_register_date'];
            $PatSex = $this->getSexName($item['cust_sex']);
            $PhoneNo = $item['cust_mobile'];
            $PatAge = $item['cust_age'];
            $ApplyDrCode = $item['user_emp_num'];
            $ApplyDrName = $item['operator_name'];
            $totalFee = bcadd($totalFee, $item['union_real_fee'], 2);
            $groupCustId = $item['group_cust_id'];
            $checkItem = [
                'ItemNo' => $no,
                'ItemCode' => $item['union_code'],
                'ItemName' => $item['his_union_name'],
                'Cost' => $item['union_real_fee'],
                'TjApplyNo' => $item['union_request_no'],
                'ExecDeptCode' => $item['exe_dept_code'],
                'ExecDeptName' => $item['exe_dept_name'],
                'ExamCategCode' => '',
                'ExamCategName' => '',
                'ExamSubclassCode' => '',
                'ExamSubclassName' => '',
                'ExamMethodCode' => $item['his_union_method_code'],
                'ExamBodysCode' => $item['his_union_body_code'],
                'BlFlag' => $item['dept_code'] == 'LWPIMS' ? '1' : '0',
                'SamplePlace' => config('standapi.tj_dept_name'),
                'CollectMethod' => '',
                'BlSubClassCode' => '',
            ];

            if ($checkItem['BlFlag']) {
                $checkItem['SpecimenItems'][] = [
                    'SpecimenSource' => '',
                    'Amount' => '',
                    'SpecimenPart' => '',
                    'SpecimenName' => '',
                    'Frozen' => '',
                    'TakeOutTime' => '',
                    'FixationTime' => '',
                    'SpecimenNo' => '',
                    'Memo' => '',
                ];
            }

            array_push($items, $checkItem);
            $no++;
        }

        $applyData = [
            'trade_code' => self::CHECK_APPLY,
            'body' => [
                'VisitNo' => $VisitNo,
                'PatientID' => $PatientID,
                'TjSerialNo' => $TjSerialNo,
                'PatName' => $PatName,
                'PatSex' => $PatSex,
                'PhoneNo' => $PhoneNo,
                'PatAge' => $PatAge,
                'TestPurpose' => '体检',
                'ApplyTime' => $ApplyTime,
                'ApplyDeptCode' => config('standapi.tj_dept_code'),
                'ApplyDeptName' => config('standapi.tj_dept_name'),
                //                'ApplyDrCode' => $ApplyDrCode,
                'ApplyDrCode' => self::APPLY_DR_CODE,
                //                'ApplyDrName' => $ApplyDrName,
                'ApplyDrName' => self::APPLY_DR_NAME,
                'ChargeType' => '1',
                'RealTotalCost' => $totalFee,
                'ClinicDiagName' => '',
                'ApplyStatus' => '1',
                'ChargeFlag' => '1',
                'GroupFlag' => $groupCustId ? 2 : 1,
                'Items' => $items,
            ],
        ];
        logMessage('[checkApply请求参数]' . json_encode($applyData, 320), 'IPMService');
        $checkResult = $this->sendToPlatform($applyData);

        if ($checkResult['Head']['TradeStatus'] == self::ERROR_CODE) {
            return ['status' => FAIL_CODE, 'msg' => $checkResult['Head']['TradeMessage'] ?? ''];
        }
        $retData = [];
        foreach ($checkResult['Body'] as $item) {
            $retData[] = [
                'union_request_no' => $item['TJApplyNo'],
                'doctor_id' => $item['ExamNo'],
            ];
        }

        // return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => $testData];
        return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => $retData];
    }

    /**
     * @description
     * 检验申请单
     *
     * @since 2021-07-29
     * @param array $NWApply
     * @return array
     */
    public function lisApply($NWApply)
    {
        $totalFee = 0;
        $items = [];
        $no = 1;

        // $testData = [];
        foreach ($NWApply as $item) {

            // $testData[] = [
            //     'union_request_no' => $item['union_request_no'],
            //     'union_barcode' => guid(),
            //     'doctor_id' => $item['union_request_no'],
            //     'union_code' => $item['union_code'],
            //     'union_name' => $item['his_union_name'],
            // ];

            $VisitNo = $item['his_register_num'];
            $PatientID = $item['medical_record_no'];
            $TjSerialNo = $item['order_code'];
            $PatName = $item['cust_name'];
            $ApplyTime = $item['order_register_date'];
            $PatSex = $this->getSexName($item['cust_sex']);
            $PhoneNo = $item['cust_mobile'];
            $PatAge = $item['cust_age'];
            $ApplyDrCode = $item['user_emp_num'];
            $ApplyDrName = $item['operator_name'];
            $totalFee = bcadd($totalFee, $item['union_real_fee'], 2);
            $groupCustId = $item['group_cust_id'];
            $items[] = [
                'ItemNo' => $no,
                'ItemCode' => $item['union_code'],
                'ItemName' => $item['his_union_name'],
                'Cost' => $item['union_real_fee'],
                'TjApplyNo' => $item['union_request_no'],
                'SampleName' => $item['sample_type_name'],
                'ExecDeptCode' => $item['exe_dept_code'],
                'ExecDeptName' => $item['exe_dept_name'],
            ];
            $no++;
        }

        $applyData = [
            'trade_code' => self::LIS_APPLY,
            'body' => [
                'VisitNo' => $VisitNo,
                'PatientID' => $PatientID,
                'TjSerialNo' => $TjSerialNo,
                'PatName' => $PatName,
                'PatSex' => $PatSex,
                'PhoneNo' => $PhoneNo,
                'PatAge' => $PatAge,
                'TestPurpose' => '体检',
                'ApplyTime' => $ApplyTime,
                'ApplyDeptCode' => config('standapi.tj_dept_code'),
                'ApplyDeptName' => config('standapi.tj_dept_name'),
                //                'ApplyDrCode' => $ApplyDrCode,
                'ApplyDrCode' => self::APPLY_DR_CODE,
                //                'ApplyDrName' => $ApplyDrName,
                'ApplyDrName' => self::APPLY_DR_NAME,
                'ChargeType' => '1',
                'RealTotalCost' => $totalFee,
                'ClinicDiagName' => '',
                'ApplyStatus' => '1',
                'ChargeFlag' => '1',
                'GroupFlag' => $groupCustId ? 2 : 1,
                'Items' => $items,
            ],
        ];
        logMessage('[lisApply请求参数]' . json_encode($applyData, 320), 'IPMService');
        $lisResult = $this->sendToPlatform($applyData);

        if ($lisResult['Head']['TradeStatus'] == self::ERROR_CODE) {
            return ['status' => FAIL_CODE, 'msg' => $lisResult['Head']['TradeMessage'] ?? ''];
        }

        $retData = [];
        foreach ($lisResult['Body'] as $item) {
            $retData[] = [
                'union_request_no' => $item['TJApplyNo'],
                'union_barcode' => $item['BarCode'],
                'doctor_id' => $item['LabNo'],
                'union_code' => $item['ItemCode'],
                'union_name' => $item['ItemName'],

            ];
        }
        // return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => $testData];
        return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => $retData];
    }

    /**
     * @description
     * 申请单撤销
     *
     * @since 2021-07-29
     * @param array $CAApply
     * @return array
     */
    public function applyCancel($CAApply)
    {
        $items = [];
        foreach ($CAApply as $item) {
            $items[] = [
                'VisitNo' => $item['his_register_num'],
                'PatientID' => $item['medical_record_no'],
                'TjSerialNo' => $item['order_code'],
                'TJApplyNo' => $item['union_request_no'],
                'ExamOrLab' => $item['dept_code'] == 'LIS' ? 'C' : 'D',
                'HisApplyNo' => $item['doctor_id'],
                'OperatorId' => $item['operator_id'],
                'OperatorName' => $item['operator_name'],
            ];
        }

        $applyData = [
            'trade_code' => self::APPLY_CANCEL,
            'body' => $items,
        ];
        logMessage('[applyCancel请求参数]' . json_encode($applyData, 320), 'IPMService');
        $lis_result = $this->sendToPlatform($applyData);

        if ($lis_result['Head']['TradeStatus'] == self::ERROR_CODE) {
            return ['status' => FAIL_CODE, 'msg' => $lis_result['Head']['TradeMessage'] ?? ''];
        }
        return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => []];
    }

    /**
     * @description
     * 发送缴费申请
     *
     * @since 2021-08-07
     * @param array $params
     * @return array
     */
    public function sendFeeData($params)
    {
        $applyItems = [];
        $id = 1;

        $items = json_decode($params['items'], true);
        foreach ($items as $item) {
            if (!$item['his_item_code']) continue;
            $applyItems[] = [
                'itemCode' => $item['his_item_code'],
                'itemName' => $item['his_item_name'],
                'num' => $item['his_fee_num'],
                'money' => $item['his_item_price'],
                'id' => $id,
                'discount' => '1',
                'performedBy' => $item['exe_dept_code'], // 执行科室
                'externalId' => $item['fee_request_code'],
            ];
            $id++;
        }
        if (!$applyItems) {
            return ['status' => FAIL_CODE, 'msg' => '收费项为空！', 'data' => []];
        }
        $body = [
            'action' => $params['apply_type'],
            'PatientID' => $params['medical_record_no'],
            'TjSerialNo' => $params['order_code'],
            // 'operatorId' => $params['operator_id'],
            'operatorId' => self::APPLY_DR_CODE,
            // 'operatorName' => $params['operator_name'],
            'operatorName' => self::APPLY_DR_NAME,
            'operatorDate' => $params['order_register_date'],
            'physicalType' => $params['pay_code'] == '1' ? '1' : '2', // 体检类别 2 团体 1 个人
            'orderedByDept' => config('standapi.tj_dept_code'),
            'orderedByDeptName' => config('standapi.tj_dept_name'),
            'Items' => $applyItems,
        ];

        $applyData = [
            'trade_code' => self::FEE_APPLY,
            'body' => $body,
        ];
        logMessage('[sendFeeData请求参数]' . json_encode($applyData, 320), 'IPMService');

        $lis_result = $this->sendToPlatform($applyData);

        if ($lis_result['Head']['TradeStatus'] == self::ERROR_CODE) {
            return ['status' => FAIL_CODE, 'msg' => $lis_result['Head']['TradeMessage'] ?? ''];
        }
        return ['status' => SUCCESS_CODE, 'msg' => '', 'data' => []];
    }

    // 检查项目同步
    public function examItemDict($params, $action)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = [
                'his_union_code' => $param['ExamItemCode'],
                'his_union_name' => $param['ExamItemName'],
                'his_item_code' => $param['ExamSubclassCode'],
                'his_item_name' => $param['ExamSubclassName'],
            ];
        }

        if ($data) {
            toBS('sync_union', ['action' => $action, 'data' => $data]);
        }
    }

    // 检验项目同步
    public function labItemDict($params, $action)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = [
                'his_union_code' => $param['LabItemCode'],
                'his_union_name' => $param['LabItemName'],
                'his_item_code' => '',
                'his_item_name' => '',
            ];
        }
        if ($data) {
            toBS('sync_union', ['action' => $action, 'data' => $data]);
        }
    }

    // 费用同步
    public function costDict($params, $action)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = [
                'his_union_code' => $param['ItemCode'],
                'his_union_name' => $param['ItemName'],
                'his_item_code' => '',
                'his_item_name' => '',
                'item_fee' => $param['Price'],
                'item_unit' => $param['Units'],
            ];
        }

        if ($data) {
            toBS('sync_union', ['action' => $action, 'data' => $data]);
        }
    }
}
