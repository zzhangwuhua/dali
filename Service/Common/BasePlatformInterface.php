<?php

namespace App\Service\Common;

interface BasePlatformInterface
{

    /**
     * @description
     * 请求平台接口
     *
     * @since 2021-07-29
     * @param array $params
     * @return mixed
     */
    public function sendToPlatform(array $params);
}