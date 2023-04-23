<?php

/**
 * Oracle 数据交互
 *
 */

namespace App\Service;



class OracleService
{
    public $user;  //用户

    public $pass;  //密码

    public $dbname; //数据库

    public $bm; //字符集编码

    public function __construct($user = 'XKTJ', $pass = 'XKTJ2022', $dbname = '//192.16.1.105:1521/hisdata', $bm= 'UTF8')
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->dbname = $dbname;
        $this->bm = $bm;
    }

    //连接数据库
    public function conn()
    {
        $link = oci_connect($this->user, $this->pass, $this->dbname, $this->bm);
        return $link;
    }

    public function oracle_fetch_array($sql, $status = '1')
    {

        $link = $this->conn();
        // if (!$status) {
        //     echo $sql . "<br/>";
        // }

        $stmt = oci_parse($link, $sql);
        $r = oci_execute($stmt);
        if (!$r) {
            $this->oracle_error($stmt);
        }
        $data=[];

        while (!!$row = oci_fetch_array($stmt, OCI_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    public function oracle_fetch_all($sql, $status = '1')
    {

        $link = $this->conn();
        // if (!$status) {
        //     echo $sql . "<br/>";
        // }

        $stmt = oci_parse($link, $sql);
        $r = oci_execute($stmt);
        if (!$r) {
            $this->oracle_error($stmt);
        }

        while (!!$row = oci_fetch_array($stmt, OCI_BOTH)) {
            $data[] = $row;
        }

        return $data;
    }

    public function oracle_fetch_row($sql, $status = '1')
    {

        $link = $this->conn();
        // if (!$status) {
        //     echo $sql . "<br/>";
        // }

        $stid = oci_parse($link, $sql);
        $r = oci_execute($stid);

        if (!$r) {
            $this->oracle_error($stid);
        }

        $data = oci_fetch_assoc($stid);
        return $data;
    }

    public function oracle_query($sql)
    {
        $link = $this->conn();
        $stmt = oci_parse($link, $sql);
        $r = oci_execute($stmt);
        if (!$r) {
           logInfo("oracle执行错误". $this->oracle_error($r)."---sql为--".$sql,[],"oracle_query");
            return false;
        }

        return true;
    }

    public function oracle_commit()
    {
        $link = $this->conn();
        $committed = oci_commit($link);
        return $committed;
    }

    public function oracle_error($stid)
    {
        if (!empty($stid)) {
            $e = oci_error($stid);
            trigger_error(htmlentities($e['message']), E_USER_ERROR);
        }
    }

    public function oracle_rollback()
    {
        $link = $this->conn();
        if (!empty($link)) {
            $r = oci_rollback($link);
        }

        return $r;
    }


}