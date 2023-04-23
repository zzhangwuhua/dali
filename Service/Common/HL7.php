<?php


namespace App\Service\Common;


class HL7
{
    private $msh;
    private $pid;
    private $pv1;
    private $orc;
    private $obr;
    private $obx;
    private $ft1;
    private $nte;
    private $dg1;
    private $row_len;
    private $container;

    public function __construct()
    {
        $this->row_len = [
            'msh' => 16,
            'pid' => 16,
            'pv1' => 16,
            'orc' => 16,
            'obr' => 16,
            'obx' => 16,
            'ft1' => 16,
            'nte' => 16,
            'dg1' => 16,
        ];

        $this->container = [
            'msh' => false,
            'pid' => false,
            'pv1' => false,
            'orc' => false,
            'obr' => false,
            'obx' => false,
            'ft1' => false,
            'nte' => false,
            'dg1' => false,
        ];
    }

    /**
     * @return array
     */
    public function getMsh(): array
    {
        return $this->msh;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setMsh(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('msh', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setMshLen(int $len = 16)
    {
        $this->setLen('msh', $len);
    }

    /**
     * @return array
     */
    public function getPid(): array
    {
        return $this->pid;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setPid(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('pid', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setPidLen(int $len = 16)
    {
        $this->setLen('pid', $len);
    }

    /**
     * @return array
     */
    public function getPv1(): array
    {
        return $this->pv1;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setPv1(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('pv1', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setPv1Len(int $len = 16)
    {
        $this->setLen('pv1', $len);
    }

    /**
     * @return array
     */
    public function getOrc(): array
    {
        return $this->orc;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setOrc(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('orc', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setOrcLen(int $len = 16)
    {
        $this->setLen('orc', $len);
    }

    /**
     * @return array
     */
    public function getObr(): array
    {
        return $this->obr;
    }

    /**
     * @description 二维数组
     * @param $first_key
     * @param $second_key
     * @param $value
     */
    public function setObr(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('obr', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setObrLen(int $len = 16)
    {
        $this->setLen('obr', $len);
    }

    /**
     * @return array
     */
    public function getObx(): array
    {
        return $this->obx;
    }

    /**
     * @description 二维数组
     * @param $first_key
     * @param $second_key
     * @param $value
     */
    public function setObx(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('obx', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setObxLen(int $len = 16)
    {
        $this->setLen('obx', $len);
    }

    /**
     * @return array
     */
    public function getFt1(): array
    {
        return $this->ft1;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setFt1(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('ft1', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setFt1Len(int $len = 16)
    {
        $this->setLen('ft1', $len);
    }

    /**
     * @return array
     */
    public function getNte(): array
    {
        return $this->nte;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setNte(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('nte', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setNteLen(int $len = 16)
    {
        $this->setLen('nte', $len);
    }

    /**
     * @return array
     */
    public function getDg1(): array
    {
        return $this->dg1;
    }

    /**
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    public function setDg1(int $first_key, int $second_key, string $value): void
    {
        $this->setCommon('dg1', $first_key, $second_key, $value);
    }

    /**
     * @param int $len
     */
    public function setDg1Len(int $len = 16)
    {
        $this->setLen('dg1', $len);
    }

    /**
     * @param $row
     * @param $len
     */
    private function setLen($row, $len)
    {
        $this->row_len[$row] = $len;
    }

    /**
     * @param string $row
     * @param int $first_key
     * @param int $second_key
     * @param string $value
     */
    private function setCommon(string $row, int $first_key, int $second_key, string $value)
    {
        if (!isset($this->$row[$first_key - 1]))
        {
            $this->$row[$first_key - 1] = array_fill(0, $this->row_len[$row], '');
        }
        $this->$row[$first_key - 1][$second_key - 1] = $value;
        $this->container[$row] = true;
    }

    /**
     * @return string
     */
    public function generate(): string
    {
        $data = '';

        foreach ($this->container as $name => $status)
        {
            if ($status)
            {
                $arr = $this->$name;
                $zero = strtoupper($name);
                foreach ($arr as $second)
                {
                    $second[0] = $zero;
                    $data = $data . "\r" . implode('|', $second) . '|';
                }
            }
        }

        return $data;
    }

    /**
     * 解析hl7
     * @param $hl7
     * @return array|string
     */
    public static function parse($hl7)
    {
        try
        {
            $hl7 = explode("\r", $hl7);
            $data = [];
            foreach ($hl7 as $line)
            {
                $line = explode('|', $line);
                $data[$line[0]][] = $line;
            }
            foreach ($data as $key => $datum)
            {
                if (count($datum) == 1)
                {
                    $data[$key] = $datum[0];
                }
            }
            return $data;
        }
        catch (\Exception | \Error $exception)
        {
            return '解析错误' . $exception->getMessage();
        }
    }
}