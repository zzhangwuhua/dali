<?php

namespace App\Service\Common;

interface BaseDataProviderInterface
{

    /**
     * @description
     * 转换请求数据为对应结构
     *
     * @since 2021-07-29
     * @param $params
     * @return mixed
     */
    public function encryptData(array $params);


    /**
     * @description
     * 解析数据
     *
     * @since 2021-07-31
     * @param $params
     * @return mixed
     */
    public function decryptData($params);
}