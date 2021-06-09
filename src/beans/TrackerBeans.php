<?php
/***
 *
 * Class TrackerBeans
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector\Beans
 * @date        2021-06-09
 */

namespace VnConnector\Beans;

class TrackerBeans
{
    // 转单号
    public $referCode;

    // 城市名称
    public $destination;

    // 目标城市
    public $deliveredTo;

    // 代理转单号
    public $agentReferCode;

    // 代理联系电话
    public $telephone;

    // 轨迹发生地址
    public $location;

    // 轨迹代号
    public $status;

    // 轨迹描述
    public $remarks;

    // 创建时间
    public $createTime;

    // 时间所在的时区
    public $timezone;

    // 客户ID
    public $clientId;

    // 渠道标识
    public $flag;

    // 操作员ID 系统默认为9999
    public $operator;

    // 运单号
    private $shipmentNumber;

    /**
     * @return mixed
     */
    public function getShipmentNumber()
    {
        return $this->shipmentNumber;
    }

    /**
     * @param mixed $shipmentNumber
     */
    public function setShipmentNumber($shipmentNumber): void
    {
        $this->shipmentNumber = $shipmentNumber;
    }

    /**
     * 获取对象数组
     * @return array
     */
    public function toArray(): array
    {
        if (!$this->destination) {
            throw new \RuntimeException('目的地城市不能为空');
        }
        if (!$this->deliveredTo) {
            throw new \RuntimeException('目的地所属国家不能为空');
        }
        if (!$this->location) {
            throw new \RuntimeException('轨迹发生城市不能为空');
        }
        if (!$this->status) {
            throw new \RuntimeException('状态描述不能为空');
        }
        if (!$this->remarks) {
            throw new \RuntimeException('描述文本不能为空');
        }

        if (!$this->clientId) {
            throw new \RuntimeException('运单归属的客户ID不能为空');
        }

        if (!$this->flag) {
            throw new \RuntimeException('录入渠道不能为空');
        }

        if (!$this->timezone) {
            throw new \RuntimeException('状态发生时区不能为空');
        }
        try {
            new \DateTimeZone($this->timezone);
        } catch (\Exception $e) {
            throw new \RuntimeException('输入的时区格式不能为空');
        }

        try {
            $result = [];
            $reflect = new \ReflectionClass($this);
            $properties = $reflect->getProperties();
            foreach ($properties as $property) {
                $result[$property->getName()] = $property->getValue($this);
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
