<?php
/**
 * basedemo
 */
namespace App\Service\Demo;

use SoapClient;

class BaseDemoService
{
    /**
     * 请求体检标准接口接口
     * @return
     */
    public function requestIPEIS($xml,$func)
    {
        logMessage( 'request -> ' . $xml, 'requestIPEIS');
        $url = config('standapi.ipeis_url');
        $client = new SoapClient ($url,['connection_timeout' => 20]);
        $res = $client->__call('ipm_service',[['MessageCode' => $func,'MessageData'=> $xml ]]);
        $ret_xml =  $res->Response;
        logMessage( 'result -> ' . print_r($res,true) , 'requestIPEIS');
        return xmlToArray($ret_xml);
    }
}