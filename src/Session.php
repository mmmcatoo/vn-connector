<?php
/***
 *
 * Class Session
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector
 * @date        2021-05-18
 */

namespace VnConnector;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Session
{
    /**
     * 远程令牌
     * @var string
     */
    private static $token = '';

    /**
     * 用户信息
     * @var array
     */
    private static $userInfo = [];

    /**
     * 用户的权限树
     * @var array
     */
    private static $rules = [];

    /**
     * 用户所在的组织树
     * @var array
     */
    private static $departments = [];

    /**
     * 过期时间
     * @var int
     */
    private static $expired = 0;

    /**
     * 请求的签名数据
     * @var string
     */
    private static $sign = '';

    /**
     * 远程服务地址
     * @var string
     */
    public static $endpoint = 'https://sso.vnlin.com';

    const ErrUserNotLogin = 1000;
    const ErrTokenExpired = 1001;

    /**
     * 检查用户是否登录
     * @return bool
     * @throws \RuntimeException
     */
    public static function isUserLogin(): bool
    {
        if (self::$token && self::$expired > time()) {
            return true;
        } elseif (!self::$token) {
            // 用户尚未登录
            throw new \RuntimeException('用户尚未登录', self::ErrUserNotLogin);
        } else {
            // 用户已经过期
            throw new \RuntimeException('用户令牌已经过期', self::ErrTokenExpired);
        }
    }

    /**
     * 获取Token值
     * @return string
     */
    public static function getToken(): string
    {
        return self::$token;
    }

    /**
     * 返回用户登录的信息
     * @return array
     * @throws \RuntimeException
     */
    public static function getUserInfo(): array
    {
        if (count(self::$userInfo) > 0) {
            return [
                'user' => self::$userInfo,
                'rules' => self::$rules,
                'departments' => self::$departments
            ];
        }
        throw new \RuntimeException('用户尚未登录', self::ErrUserNotLogin);
    }

    /**
     * 跳转到登录页面
     * @param string $host
     * @param string $callbackUrl 回调地址
     * @param string $role        请求角色
     * @return string 拼接好的HTML
     */
    public static function redirect(string $host, string $callbackUrl, string $role): string
    {
        $callback = sprintf('https://%s%s', $host, $callbackUrl);
        $endpoint = self::$endpoint;
        // 输出HTML
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>SSO Login Redirecting...</title>
</head>
<body>
<form id="frm" method="post" action="{$endpoint}" enctype="application/x-www-form-urlencoded">
    <input type="hidden" name="callback" value="{$callback}/session/login"/>
    <input type="hidden" name="role" value="{$role}"/>
</form>
<script type="text/javascript">
    document.querySelector('#frm').submit()
</script>
</body>
</html>
HTML;
    }

    /**
     * 回调处理SSO返回的数据
     * @param string $hash
     * @return bool
     */
    public static function decrypt(string $hash): bool
    {
        try {
            $binary = json_decode(EncryptionUtilities::decrypt($hash, self::$endpoint));
            if (json_last_error()) {
                return false;
            }
            // 处理数据
            self::$token = $binary->token;
            self::$expired = $binary->expired + time();
            self::$userInfo = $binary->userInfo;
            self::$rules = $binary->rules;
            self::$departments = $binary->departments;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 申请SSO退出
     * @param string $token
     */
    public static function logout(string $token)
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::$endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
        try {
            $client->request('POST', '/session/token', [
                'body' => json_encode(['token' => $token])
            ]);
        } catch (GuzzleException $e) {

        }
    }

    /**
     * 刷新用户信息
     * @param string $token
     */
    public static function refresh(string $token)
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::$endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
        try {
            $res = $client->request('GET', '/session/current', [
                'headers' => [
                    'X-Authorization' => $token
                ]
            ]);
            self::decrypt($res->getBody()->getContents());
        } catch (GuzzleException $e) {

        }
    }

    /**
     * 远程获取客户信息
     * @param string $appId
     * @param string $appSign
     * @param string $remoteIP
     * @return integer|array
     */
    public static function queryCustomer(string $appId, string $appSign, string $remoteIP)
    {
        $model = new RemoteModel('SsoCustomer', '');
        $customer = $model->where('app_id', '=', $appId)->find();
        if ($customer === null) {
            // 客户不存在
            return -2;
        }
        $sign = md5(sprintf('%s%s', $appId, $customer['app_secret']));
        if ($sign !== $appSign) {
            // 签名验证失败
            return -1;
        }
        $ipWhiteList = json_decode($customer['ip_list'], true);
        if (is_array($ipWhiteList) && !in_array($remoteIP, $ipWhiteList)) {
            // 验证IP失败
            return -3;
        }
        return $customer;
    }
}
