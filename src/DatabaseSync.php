<?php
/***
 *
 * Class DatabaseSync
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector
 * @date        2021-06-08
 */

namespace VnConnector;

use GuzzleHttp\Client;

/**
 * Class DatabaseSync
 * @package VnConnector
 * @method static onCreateEvent(\Closure $callback)
 * @method static onUpdateEvent(\Closure $callback)
 * @method static onDeleteEvent(\Closure $callback)
 */
class DatabaseSync
{
    /**
     * 创建数据
     * @var string
     */
    const DataCreate = 'create';

    /**
     * 更新数据
     * @var string
     */
    const DataUpdate = 'update';

    /**
     * 删除数据
     * @var string
     */
    const DataDelete = 'delete';

    /**
     * 远程服务地址
     * @var string
     */
    private $endpoint = 'https://sso.vnlin.com';

    /**
     * 请求的令牌
     * @var string
     */
    private $token = '';

    /**
     * 实例对象
     * @var \VnConnector\DatabaseSync
     */
    private static $instance = null;

    /**
     * 回调事件处理器
     * @var array
     */
    private static $eventHandler = [];

    /**
     * 私有化创建函数
     * DatabaseSync constructor.
     */
    private function __construct()
    {

    }

    /**
     * 禁止拷贝
     */
    private function __close()
    {

    }

    /**
     * @param string $token    当前用户的令牌
     * @param string $endpoint 服务端信息
     * @return \VnConnector\DatabaseSync
     */
    public static function getInstance(string $token, string $endpoint = 'https://sso.vnlin.com'): DatabaseSync
    {
        if (is_null(self::$instance)) {
            self::$instance           = new static();
            self::$instance->token    = $token;
            self::$instance->endpoint = $endpoint;
        }

        return self::$instance;
    }

    /**
     * 发布数据库变更通知
     * @param string $type   事件类型
     * @param string $model  关联的模型名称
     * @param array  $params 变动的数据
     * @param string $endpoint
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function publish(string $type, string $model, array $params, string $endpoint = ''): bool
    {
        try {
            $client = new Client([
                // Base URI is used with relative requests
                'base_uri' => $endpoint ?: $this->endpoint,
                // You can set any number of default request options.
                'timeout'  => 2.0,
            ]);

            $res     = $client->request('POST', '/database/sync', [
                'body'    => json_encode([
                    'type'   => strtoupper($type),
                    'model'  => $model,
                    'params' => $params,
                ]),
                'headers' => [
                    'X-Authorization' => $this->token,
                    'Accept'          => 'application/json',
                    'Content-Type'    => 'application/json',
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return true;
            }
            // 抛出异常
            throw new \RuntimeException($resJson['msg'], $resJson['code']);
        } catch (\Exception $e) {
            // 转换异常
            throw new \RuntimeException($e->getMessage(), 9006);
        }
    }

    /**
     * 接收数据库事件
     * @param string $type   事件类型
     * @param string $model  模型名称
     * @param array  $params 事件参数
     */
    public static function handleDataSyncEvent(string $type, string $model, array $params)
    {
        $key = strtolower($type);
        if (isset(self::$eventHandler[$key])) {
            call_user_func(self::$eventHandler[$key], $model, $params);
        } else {
            throw new \RuntimeException('错误的触发事件');
        }
    }

    /**
     * 设置静态处理器
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        if (substr($name, 0, 2) === 'on') {
            $method = strtolower(str_replace('Event', '', substr($name, 2)));
            if (in_array($method, [self::DataCreate, self::DataUpdate, self::DataDelete])) {
                // 设置事件回调
                if (isset($arguments[0]) && $arguments[0] instanceof \Closure) {
                    // 绑定回调事件
                    self::$eventHandler[$method] = $arguments[0];
                } else {
                    throw new \RuntimeException('回调事件需要为闭包');
                }
            } else {
                throw new \RuntimeException('回调事件不合法');
            }
        }
    }

}
