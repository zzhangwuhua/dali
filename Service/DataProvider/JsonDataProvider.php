<?php

namespace App\Service\DataProvider;

use App\Service\Common\SingletonTrait;
use App\Service\Common\BaseDataProviderInterface;

class JsonDataProvider implements BaseDataProviderInterface
{
    use SingletonTrait;
    
    public function encryptData($params)
    {
        return json_encode($params['data'], 320);
    }

    public function decryptData($params)
    {
        return json_decode($params, true);
    }
}