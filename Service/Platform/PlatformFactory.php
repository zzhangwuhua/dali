<?php


namespace App\Service\Platform;

use App\Service\Platform\Webapi\IPMService;
use App\Service\Platform\WebService\IPMService as SOAP_IPMService;
use App\Service\Common\BasePlatformAbstract;
use App\Service\DataProvider\JsonDataProvider;


class PlatformFactory 
{
    const WEB_API = "WEB_API";
    
    const WEB_SERVICE = "WEB_SERVICE";


    /**
     * @description
     *
     * @since 2021-09-24
     * @param string $dataProciderClass
     * @param string $interactionType
     * @return BasePlatformAbstract
     */
    public static function getPlatform($dataProciderClass = JsonDataProvider::class, $interactionType = self::WEB_API)
    {
        $dataProvider = $dataProciderClass::getInstance();
        switch ($interactionType) {
            case self::WEB_SERVICE:
                return new SOAP_IPMService($dataProvider);
            case self::WEB_API:
                return new IPMService($dataProvider);
            default:
                return new IPMService($dataProvider);
        }
    }
}