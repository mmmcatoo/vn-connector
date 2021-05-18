<?php
/***
 *
 * Class RemoteModel
 *
 * @author      mmmcatoo<mmmcatoo@qq.com>
 * @version     1.0
 * @package     VnConnector
 * @date        2021-05-18
 */

namespace VnConnector;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class RemoteModel
{
    /**
     * 远程服务地址
     * @var string
     */
    public static $endpoint = 'https://sso.vnlin.com';

    /**
     * 远程模型名称
     * @var string
     */
    private $tableName;

    /**
     * 简单查询
     * @var array
     */
    private $condition = [];

    /**
     * 复杂查询表达式
     * @var string
     */
    private $template = '';

    /**
     * 复杂查询绑定值
     * @var array
     */
    private $binding = [];

    /**
     * 分页信息
     * @var array
     */
    private $paginate = [];

    /**
     * 排序类型
     * @var string
     */
    private $orderBy = '';

    /**
     * 当前操作的用户令牌
     * @var string
     */
    private $token;

    /**
     * 请求的对象
     * @var null
     */
    private $client = null;

    /**
     * RemoteModel constructor.
     * @param string $modelName
     * @param string $token
     */
    public function __construct(string $modelName, string $token)
    {
        // 修正模型的名称
        $this->tableName = ucfirst(preg_replace_callback($modelName, function (string $item) {
            var_dump($item);
            return '';
        }, $modelName));
        $this->token     = $token;
        $this->client    = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::$endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
    }

    /**
     * 设置AND条件
     * @param string $field
     * @param string $operator
     * @param        $value
     * @return $this
     */
    public function where(string $field, string $operator, $value): RemoteModel
    {
        $this->condition[$field] = [
            'operator' => $operator,
            'values'   => $value,
        ];
        return $this;
    }

    /**
     * 复杂查询
     * @param string $template
     * @param array  $binding
     * @return $this
     */
    public function whereRaw(string $template, array $binding): RemoteModel
    {
        $this->template = $template;
        $this->binding  = $binding;
        return $this;
    }

    /**
     * 分页信息
     * @param int $page
     * @param int $pageSize
     * @return $this
     */
    public function page(int $page, int $pageSize): RemoteModel
    {
        $this->paginate = ['page' => $page, 'pageSize' => $pageSize];
        return $this;
    }

    /**
     * 限制返回的条数
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): RemoteModel
    {
        $this->paginate = ['page' => 1, 'pageSize' => $limit];
        return $this;
    }

    public function select(string $columns = '*'): array
    {
        $params = array_merge(['table' => $this->tableName, 'columns' => $columns], $this->buildWhere());

        try {
            $res     = $this->client->request('POST', '/database/query', [
                'body'    => json_encode($params),
                'headers' => [
                    'X-Authorization' => $this->token,
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return $resJson['payload'];
            } else {
                return ['rows' => [], 0];
            }
        } catch (GuzzleException $e) {
            return ['rows' => [], 0];
        }
    }

    /**
     * 返回单条记录
     * @return array
     */
    public function find(): ?array
    {
        $raw = $this->select();
        return count($raw['rows']) === 0 ? [] : $raw['rows'];
    }

    /**
     * 更新数据库
     * @param array $params
     * @return bool
     */
    public function update(array $params): bool
    {
        $params = array_merge(['table'  => $this->tableName, 'update' => $params], $this->buildWhere());

        try {
            $res     = $this->client->request('POST', '/database/update', [
                'body'    => json_encode($params),
                'headers' => [
                    'X-Authorization' => $this->token,
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return true;
            } else {
                return false;
            }
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * 创建新的数据
     * @param array $params
     * @return bool
     */
    public function create(array $params): bool
    {
        try {
            $res     = $this->client->request('POST', '/database/create', [
                'body'    => json_encode(array_merge(['tableName' => $this->tableName, 'insert' => $params], $this->buildWhere())),
                'headers' => [
                    'X-Authorization' => $this->token,
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return true;
            } else {
                return false;
            }
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function delete(): bool
    {
        try {
            $res     = $this->client->request('POST', '/database/create', [
                'body'    => json_encode(array_merge(['tableName' => $this->tableName], $this->buildWhere())),
                'headers' => [
                    'X-Authorization' => $this->token,
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return true;
            } else {
                return false;
            }
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * 处理Where条件
     * @return array
     */
    private function buildWhere()
    {
        $params = [];
        if (count($this->condition)) {
            $params['condition'] = $this->condition;
        } elseif ($this->template) {
            $params['raw'] = [
                'template' => $this->template,
                'binding'  => $this->binding,
            ];
        }

        if (count($this->paginate) > 0) {
            $params['paginate'] = $this->paginate;
        }

        if ($this->orderBy) {
            $params['orderBy'] = $this->orderBy;
        }

        return $params;
    }
}
