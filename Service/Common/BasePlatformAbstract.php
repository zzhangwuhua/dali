<?php

namespace App\Service\Common;

abstract class BasePlatformAbstract implements BasePlatformInterface
{

    const RESPONSE = "RESPONSE";

    #region 申请单编码
    const LIS_APPLY = 'LIS_APPLY';
    const CHECK_APPLY = 'CHECK_APPLY';
    const APPLY_CANCEL = 'APPLY_CANCEL';
    const ARCH_CREATE = 'HIS_USER_CREATE';
    const ARCH_QUERY = 'HIS_USER_QUERY';
    const ARCH_UPDATE = 'HIS_USER_UPDATE';
    const FEE_APPLY = 'FEE_APPLY';
    #endregion

    #region 平台请求编码
    const FEE_STATUS = 'FEE_STATUS';
    const CHECK_STATUS = 'CHECK_STATUS';
    const LIS_STATUS = 'LIS_STATUS';

    const CHECK_REPORT = 'CHECK_REPORT';
    const LIS_REPORT = 'LIS_REPORT';
    const MIC_REPORT = 'MIC_REPORT';
    #endregion

    public function __call($method, $args)
    {
        if (!method_exists($this, $method)) {
            return;
        }
    }

    /**
     * @description
     * 请求平台接口
     *
     * @since 2021-07-29
     * @param array $params
     * @return mixed
     */
    public function sendToPlatform(array $params) {}

    public function checkArchive($params) {}
    public function createArchive($params) {}
    public function updateArchive($params) {}
    public function sendFeeData($params) {}
    public function checkApply($params) {}
    public function applyCancel($params) {}
    public function lisApply($params) {}
}