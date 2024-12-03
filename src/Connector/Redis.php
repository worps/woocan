<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/5/15
 * Time: 15:52
 */

namespace Woocan\Connector;

use \Woocan\Core\Context;

class Redis extends Base
{
    private $instance;
    private $config;
    private $defaultOptions = [
        \Redis::OPT_SERIALIZER  => \Redis::SERIALIZER_NONE,
        \Redis::OPT_SCAN        => \Redis::SCAN_RETRY
    ];

    /**
     * @param $config
     * @return \Redis
     * 注意事项：
     *  1.pconnect第三个参数timeout表示连接被闲置多久后自动断开，而不是创建连接的超时时间
     *  2.pconnect调用后可能会复用连接，不一定会重建。因此每次都要重新指定db
     *  3.综上，pconnect问题较多，暂不启用
     *  解决：
     *  $redis->pconnect('127.0.0.1', 6379, 2.5, 'x'); // x is sent as persistent_id and would be another connection than the three before.
     */
    function __construct($config)
    {
        $this->config = $config;
        $this->_connect();
    }

    private function _connect()
    {
        $redis = new \Redis();

        //不能使用pconnet，否则将不会创建新连接而是复用之前的连接，导致连接池无效
        $redis->connect($this->config['host'], $this->config['port'], 2);

        //连接参数
        $options = $this->config['options'] ?? $this->defaultOptions;
        foreach ($options as $key => $val) {
            $redis->setOption($key, $val);
        }

        //登录
        if (isset($this->config['user']) && isset($this->config['password'])) {
            $redis->rawCommand('auth', $this->config['user'], $this->config['password']);
        } else if (isset($this->config['password'])) {
            $redis->rawCommand('auth', $this->config['password']);
        }

        $redis->select($this->config['db']);
        $this->instance = $redis;
    }

    public function __call($action, $arguments)
    {
        $time = microtime(true);
        $result = call_user_func_array([$this->instance, $action], $arguments);
        if (false === $result) {
            $pong = $this->instance->ping();
            if ($pong != '+PONG') {
                $this->_connect($this->config);
                $result = call_user_func_array([$this->instance, $action], $arguments);
            }
        }

        //echo $action.'|'. (microtime(true) - $time) *1000 .'|'.json_encode($arguments)."<br><br>";

        //搜集redis调用次数
        Context::set('api_stats_redis_count', Context::get('api_stats_redis_count') + 1);

        return $result;
    }

    public function disconnect()
    {
        return $this->instance->close();
    }

    function __destruct()
    {
        $this->disconnect();
    }
}