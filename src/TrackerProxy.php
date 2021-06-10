<?php
/***
 *
 * Class TrackerProxy
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector
 * @date        2021-06-09
 */

namespace VnConnector;

use GuzzleHttp\Client;
use VnConnector\Beans\TrackerBeans;

class TrackerProxy
{
    /**
     * 轨迹服务器接口
     * @var string
     */
    private $endpoint = 'https://tracker.vnlin.com';

    /**
     * 设置轨迹服务器接口
     * @param string $endpoint
     * @return $this
     */
    public function setEndpoint(string $endpoint) : TrackerProxy {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * 创建单条记录
     * @param string                          $shipmentNumber
     * @param \VnConnector\Beans\TrackerBeans $trackerBeans
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createTracker(string $shipmentNumber, TrackerBeans $trackerBeans) {
        $trackerBeans->setShipmentNumber($shipmentNumber);
        // 获取目标数组
        $trackerArray = $trackerBeans->toArray();
        // 发送数据
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $this->endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);

        $res = $client->request('POST', sprintf('/tracker/%s/upgrade', $shipmentNumber), [
            'body'    => json_encode($trackerArray),
            'headers' => [
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
            ],
        ]);
        $resJson = json_decode($res->getBody()->getContents(), true);
        if (!$resJson['status']) {
            throw new \RuntimeException($resJson['message']);
        }
        return true;
    }

    public function createAllTracker(array $shipmentNumbers, TrackerBeans $trackerBeans) {
        // 获取目标数组
        $trackerArray = $trackerBeans->toArray();
        // 发送数据
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $this->endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);

        $res = $client->request('POST', '/trackers/upgrade', [
            'body'    => json_encode([
                'shipmentNumber' => $shipmentNumbers,
                'trace' => $trackerArray
            ]),
            'headers' => [
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
            ],
        ]);
        $resJson = json_decode($res->getBody()->getContents(), true);
        if (!$resJson['status']) {
            throw new \RuntimeException($resJson['message']);
        }
        return true;
    }
}
