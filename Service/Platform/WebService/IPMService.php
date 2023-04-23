<?php

namespace App\Service\Platform\WebService;

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


        $tradeCode = $params->MessageCode;
        $body = xmlToArray($data);

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
        if (!empty($params['item']['his_union_code'])) {
            $item = $params['item'];
            $retData[] = [
                'his_union_code' => $item['his_union_code'],
                'his_union_name' => $item['his_union_name'],
                'union_request_no' => $item['union_request_no'],
                'union_barcode' => $item['union_barcode'],
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'examiner_id' => $item['examiner_id'],
                'examiner_name' => $item['examiner_name'],
                'examiner_time' =>  $item['examiner_time'],
                'reviewer_id' => $item['reviewer_id'],
                'reviewer_name' => $item['reviewer_name'],
                'reviewer_time' =>  $item['reviewer_time'],
                'item_check_result' => $item['item_check_result'],
                'item_unit' => $item['item_unit'],
                'item_ref_value' => $item['item_ref_value'],
                'exception_tag' => $item['exception_tag']
            ];

        } else {
            foreach ($params['item'] as $item) {
                $retData[] = [
                    'his_union_code' => $item['his_union_code'],
                    'his_union_name' => $item['his_union_name'],
                    'union_request_no' => $item['union_request_no'],
                    'union_barcode' => $item['union_barcode'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'examiner_id' => $item['examiner_id'],
                    'examiner_name' => $item['examiner_name'],
                    'examiner_time' =>  $item['examiner_time'],
                    'reviewer_id' => $item['reviewer_id'],
                    'reviewer_name' => $item['reviewer_name'],
                    'reviewer_time' =>  $item['reviewer_time'],
                    'item_check_result' => $item['item_check_result'],
                    'item_unit' => $item['item_unit'],
                    'item_ref_value' => $item['item_ref_value'],
                    'exception_tag' => $item['exception_tag']
                ];
            }
        }
        return $retData;
    }

    private function filterCheckReport($params)
    {
        $item = $params['item'];
        return [[
            'examiner_id' => $item['examiner_id'],
            'examiner_name' => $item['examiner_name'],
            'examiner_time' =>  $item['examiner_time'],
            'reviewer_id' => $item['reviewer_id'],
            'reviewer_name' => $item['reviewer_name'],
            'reviewer_time' => $item['reviewer_time'],
            'check_conclusion' => $item['check_conclusion'],
            'check_desc' => $item['check_desc'],
            'his_union_code' => $item['his_union_code'],
            'his_union_name' => $item['his_union_name'],
            'union_request_no' => $item['union_request_no'],
            'report_data' => $item['report_data'],
        ]];
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
        return [
            "union_request_no" => $body['item']['union_request_no'],
            "status" => $body['item']['status'],
            "operator_id" => '',
            "operator_name" => '',
            "union_execute_time" => date('Y-m-d H:i:s'),
            "comments" => '',
        ];
    }

    private function filterCheckStatus(array $body)
    {
        return [
            "union_request_no" => $body['item']['union_request_no'],
            "status" => $body['item']['status'],
            "operator_id" => '',
            "operator_name" => '',
            "union_execute_time" => date('Y-m-d H:i:s'),
            "comments" => '',
        ];
    }

    private function filterFeeStatus(array $body)
    {
        $params = [];
        if (isset($body['item'][0])) {
            foreach ($body['item'] as $item) {
                $params[] = [
                    'union_request_no' => $item['union_request_no'],
                    'fee_status' => $item['fee_status'] == 1 ? 1 : 2
                ];
            }
        } else {
            $params[] = [
                'union_request_no' => $body['item']['union_request_no'],
                'fee_status' => $body['item']['fee_status'] == 1 ? 1 : 2
            ];
        }


        return $params;
    }
}
