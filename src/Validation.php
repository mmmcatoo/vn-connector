<?php
/***
 *
 * Class Validation
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector
 * @date        2021-05-20
 */

namespace VnConnector;

class Validation
{
    /**
     * 计算IP地址是否内网IP
     * @param string $ipAddress
     * @return bool
     * @throws \RuntimeException
     */
    public static function fromInternalAddress(string $ipAddress): bool
    {
        $ipAddress = filter_var($ipAddress, FILTER_FLAG_IPV4);
        if ($ipAddress === false) {
            // 不是有效的IP地址
            throw new \RuntimeException($ipAddress);
        }

        if (substr($ipAddress, 0, 3) === '10.') {
            // 10.x.x.x直接返回
            return true;
        }

        if (substr($ipAddress, 0, 8) === '192.168.') {
            // 192.168.0.0 - 192.168.255.255
            return true;
        }

        $longIP = ip2long($ipAddress);
        if (2886729728 <= $longIP && $longIP <= 2887778303) {
            // 172.16.0.0 - 172.31.255.255
            return true;
        }
        return false;
    }
}
