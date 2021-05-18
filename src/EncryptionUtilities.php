<?php
/***
 *
 * Class EncryptionUtilities
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector
 * @date        2021-05-18
 */

namespace VnConnector;

class EncryptionUtilities
{
    /**
     * 加密响应文本
     * @param string $hash
     * @param string $key
     * @return string
     */
    public static function encrypt(string $hash, string $key) {
        $iv = substr(sha1($key), 0, 8);
        $key = substr(hash('sha256', $key), 0, 24);
        return base64_encode(mcrypt_encrypt(MCRYPT_3DES, $key, utf8_encode($hash), MCRYPT_MODE_CBC, $iv));
    }

    /**
     * 解密响应文本
     * @param string $hash
     * @param string $key
     * @return string
     */
    public static function decrypt($hash, string $key) {
        $iv = substr(sha1($key), 0, 8);
        $key = substr(hash('sha256', $key), 0, 24);
        return mcrypt_decrypt(MCRYPT_3DES, $key, base64_decode($hash), MCRYPT_MODE_CBC, $iv);
    }
}