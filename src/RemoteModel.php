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
     * 匹配到的规则
     * @var array
     */
    private $params = null;

    /**
     * 模型简单关联
     * @var array
     */
    private $withLink;

    /**
     * RemoteModel constructor.
     * @param string $modelName
     * @param string $token
     * @param string $params
     */
    public function __construct(string $modelName, string $token, string $params)
    {
        // 修正模型的名称
        $this->tableName = ucfirst(preg_replace_callback('(_\w{1,1})', function (array $item) {
            if (count($item)) {
                return strtoupper(substr($item[0], 1));
            }
            return '';
        }, $modelName));
        $this->token     = $token;
        $this->client    = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::$endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
        if ($params) {
            $this->params = json_decode($params, true);
        }
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

    /**
     * 模型关联
     * @param string $fieldName
     * @param string $searchModel
     * @param array  $condition
     * @param string $fields
     * @param bool   $binding
     * @return $this
     */
    public function with(string $fieldName, string $searchModel, array $condition, string $fields = '*', bool $binding = false): RemoteModel
    {
        $this->withLink[] = [
            'field'     => $fieldName,
            'model'     => $searchModel,
            'condition' => $condition,
            'fields'    => $fields,
            'binding'   => $binding,
        ];

        return $this;
    }

    /**
     * 搜索数据
     * @param string $columns
     * @return array
     */
    public function select(string $columns = '*'): array
    {
        $params = array_merge(['table' => $this->tableName, 'columns' => $columns, 'link' => $this->withLink], $this->buildWhere());

        try {
            $res     = $this->client->request('POST', '/database/query', [
                'body'    => json_encode($params),
                'headers' => [
                    'X-Authorization' => $this->token,
                    'Accept'          => 'application/json',
                    'Content-Type'    => 'application/json',
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return $resJson['payload'];
            } else {
                return ['rows' => [], 'total' => 0];
            }
        } catch (\Exception $e) {
            return ['rows' => [], 'total' => 0];
        }
    }

    /**
     * 返回单条记录
     * @return array
     */
    public function find(): ?array
    {
        $raw = $this->limit(1)->select();
        return count($raw['rows']) === 0 ? [] : $raw['rows'][0];
    }

    /**
     * 设置排序顺序
     * @param string $order
     * @return $this
     */
    public function orderBy(string $order): RemoteModel
    {
        $this->orderBy = $order;
        return $this;
    }

    /**
     * 更新数据库
     * @param array $params
     * @return bool
     */
    public function update(array $params): bool
    {
        $params = array_merge(['table' => $this->tableName, 'update' => $params], $this->buildWhere());

        try {
            $res     = $this->client->request('POST', '/database/update', [
                'body'    => json_encode($params),
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
            throw new \RuntimeException($e->getMessage(), 9005);
        }
    }

    /**
     * 创建新的数据
     * @param array $params
     * @param bool  $returnModel
     * @return bool|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function create(array $params, bool $returnModel = false)
    {
        try {
            $res     = $this->client->request('POST', '/database/create', [
                'body'    => json_encode(array_merge(['table' => $this->tableName, 'insert' => $params, 'returnModel' => $returnModel], $this->buildWhere())),
                'headers' => [
                    'X-Authorization' => $this->token,
                    'Accept'          => 'application/json',
                    'Content-Type'    => 'application/json',
                ],
            ]);
            $resJson = json_decode($res->getBody()->getContents(), true);
            if ($resJson['status']) {
                return $returnModel ? $resJson['payload'] : true;
            }
            // 抛出异常
            throw new \RuntimeException($resJson['msg'], $resJson['code']);
        } catch (\Exception $e) {
            // 转换异常
            throw new \RuntimeException($e->getMessage(), 9006);
        }
    }

    /**
     * 删除指定的数据
     * @return bool
     */
    public function delete(): bool
    {
        try {
            $res     = $this->client->request('POST', '/database/delete', [
                'body'    => json_encode(array_merge(['table' => $this->tableName], $this->buildWhere())),
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
            throw new \RuntimeException($e->getMessage(), 9007);
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
            $this->mergeParams($params['condition']);
        } elseif ($this->template) {
            $params['raw'] = [
                'template' => $this->template,
                'binding'  => $this->binding,
            ];

            $this->mergeParams($params['raw']['template'], $params['raw']['binding']);
        } else {
            $params['condition'] = [];
            $this->mergeParams($params['condition']);
        }

        if (count($this->paginate) > 0) {
            $params['paginate'] = $this->paginate;
        }

        if ($this->orderBy) {
            $params['orderBy'] = $this->orderBy;
        }

        return $params;
    }

    /**
     * 合并相关的变量参数
     * @param       $template
     * @param array $binding
     */
    private function mergeParams(&$template, array &$binding = [])
    {
        if ($this->params) {
            if (count($binding) === 0) {
                foreach ($this->params as $key => $v) {
                    $template[$key] = $v;
                }
            } else {
                foreach ($this->params as $key => $v) {
                    $bindingValue = is_array($v['values']) ? sprintf('(%s)', implode(', ', array_fill(0, count($v['values']), '?'))) : '?';
                    $template     .= sprintf(' AND %s %s %s', $key, $v['operator'], $bindingValue);
                    foreach ((array)$v['values'] as $item) {
                        $binding[] = $item;
                    }
                }
            }
        }
    }
}
