<?php

namespace App\Service\Common;

use SoapClient;

trait PlatformTrait
{
    private $fun_code;
    private $time;
    private $guid;
    public $apply_dept_code = '2009';//申请科室code
    public $apply_dept_name = '体检科';//申请科室
    public $apply_doctor_code = '001';//申请人编码
    public $apply_doctor_name = '王主任';//申请人

    public function __construct()
    {
        ini_set('display_errors', 'On');
        ini_set('soap.wsdl_cache_enabled', "0"); //关闭wsdl缓存
        $this->time = date('YmdHis');
    }

    public function getCardType($cust_card_type_code)
    {
        // $card_type = 0;
        switch ($cust_card_type_code)
        {
            case 1:
                $card_type = '01';//身份证
                break;
            case 2:
                $card_type = '03';//护照
                break;
            case 3:
                $card_type = '06'; //港澳居民来往内地通行
                break;
            case 4:
                $card_type = '07';//台湾居民来往内地通行证
                break;
            default:
                $card_type = '99';//其他
        }
        return $card_type;
    }

    // 发起http请求
    private function requestHttp($xml)
    {
        logMessage( 'http request -> ' . $xml, __CLASS__);
        $url = $this->getRequestUrl();
        logMessage( 'url -> ' . $url, __CLASS__);
        $result_xml = curlRaw($url, $xml);
        logMessage( 'http result -> ' . $result_xml, __CLASS__);
        return xmlToArray($result_xml);
    }

    // 请求到rabbitmq
    private function requestMQ($xml)
    {
//        try
//        {
            $queue = new RabbtMQService('commonServiceEx', 'physicalQueue', '5672');
            logMessage( 'rabbitmq request -> ' . $xml, __CLASS__);
            $result = $queue->send($xml);
            logMessage( 'rabbitmq result -> ' . json_encode($result, 256), __CLASS__);
            return $result ? returnSuccess() : returnFail('rabbitmq发送失败');
//        }
//        catch (\Exception $exception)
//        {
//            return returnFail($exception->getMessage());
//        }
    }

    // 发起HIS请求
    public function requestHis($xml,$func)
    {
        logMessage( 'request -> ' . $xml, 'requestHis');
        $url = config('standapi.his_url');

        // logMessage( 'url -> ' . $url, 'requestHis');
        $client = new SoapClient($url,['connection_timeout' => 20]);
        // $res = $client->__call('HIPMessageInfo',[['input1' => $func,'input2'=> $xml ]]);
        $res = $client->__call('hisService',[['func' => $func,'message'=> $xml]]);
        $ret_xml =  $res->result;
        logMessage( 'result -> ' . print_r($res,true) , 'requestHis');
        return xmlToArray($ret_xml);
    }

    // 发起LIS请求
    public function requestLis($xml,$func)
    {
        logMessage( 'request -> ' . $xml, 'requestLis');
        $url = config('standapi.lis_url');

        ini_set('display_errors', 'On');
        ini_set('soap.wsdl_cache_enabled', "0"); //关闭wsdl缓存
        $client = new SoapClient ($url,['connection_timeout' => 20]);
        $res = $client->__call('lisService',[['func' => $func,'message'=> $xml ]]);
        $ret_xml =  $res->result;
        logMessage( 'result -> ' . print_r($res,true) , 'requestLis');
        return xmlToArray($ret_xml);
    }

    // 发起检查请求
    public function requestCheck($xml,$func)
    {
        logMessage( 'request -> ' . $xml, 'requestCheck');
        $url = config('standapi.check_url');

        ini_set('display_errors', 'On');
        ini_set('soap.wsdl_cache_enabled', "0"); //关闭wsdl缓存
        $client = new SoapClient ($url,['connection_timeout' => 20]);
        $res = $client->__call('checkService',[['func' => $func,'message'=> $xml ]]);
        $ret_xml =  $res->result;
        logMessage( 'result -> ' . print_r($res,true) , 'requestCheck');
        return xmlToArray($ret_xml);
    }
}