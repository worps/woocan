<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 2019/12/13
 * Time: 23:01
 */

namespace Woocan\Midware;

use \Woocan\Core\Interfaces\Midware as IMidware;

/**
 * 限流
 * 配置格式：
 * [
 *   key=>['ip', 'arg-cmd'],
 *   gap=>200,
 *   white => ['ip'=>['127.0.0.1'],'arg_cmd'=>[11,25]]
 *   black => ['ip'=>['123.1.23.85']],
 *   tips => ['code'=>-1,'msg'=>'请求过于频繁']
 * ]
 */
class RequestLimit implements IMidware
{
    const Table_Length = 409600;
    const Keys_Method_Map = [
        'ip' => '\\Woocan\\Core\\Request::getClientIp',
        'arg' => '\\Woocan\\Core\\Request::getParams',
        'fd' => '\\Woocan\\Midware\\RequestLimit::_getFd',
    ];
    //回收作业间隔，秒
    const Gc_Time = 10;
    //回收数据保留，秒
    const Gc_Expire = 10;

    private $table;
    private $rawkeyMethods = [];
    private $keyResult = [];
    private $gcTimer = null;

    //限制请求间隔ms
    private $limitGap = 200;
    private $limitWhite = [];
    private $limitBlack = [];
    private $limitTips = ['code'=>-1,'msg'=>'请求过于频繁'];

    /* swoole前初始化table */
    public function initial($config)
    {
        $this->table = \Woocan\Core\Table::create('RequestLimitTable', self::Table_Length, [
            ['limit_count', \Swoole\Table::TYPE_INT, 2],
            ['time', \Swoole\Table::TYPE_INT, 8],
        ]);

        if (is_array($config['key'])){
            foreach ($config['key'] as $rawKey){
                $rawKeyArr = explode('-', $rawKey);
                $keyName = $rawKeyArr[0];
                $param = isset($rawKeyArr[1]) ? $rawKeyArr[1] : null;
                if (isset(self::Keys_Method_Map[$keyName])){
                    $this->rawkeyMethods[$rawKey] = [
                        self::Keys_Method_Map[$keyName],    //method
                        $param,                             //method的参数
                    ];
                }
            }
        }
        if (isset($config['white'])){
            foreach ($config['white'] as $rawKey => $item){
                $this->limitWhite[$rawKey] = array_flip($item);
            }
        }
        if (isset($config['black'])){
            foreach ($config['black'] as $rawKey => $item){
                $this->limitBlack[$rawKey] = array_flip($item);
            }
        }
        isset($config['gap']) && $this->limitGap = $config['gap'];
        isset($config['tips']) && $this->limitTips = $config['tips'];
    }

    //过期数据清理
    private function _gc()
    {
        $this->gcTimer = \Swoole\Timer::tick(self::Gc_Time * 1000, function ()
        {
            $expireTime = (int)(microtime(true) * 1000) - $this->limitGap - Gc_Expire* 1000;

            foreach ($this->table as $key => $row)
            {
                if ($row['time'] < $expireTime) {
                    $this->table->del($key);
                }
            }
        });
    }

    function start($params)
    {
        $this->_setRawkeyVal();

        if ($this->_isWhite()){
            return null;
        }
        if ($this->_isBlack()){
            return $this->limitTips;
        }

        $result = null;
        $key = $this->_makeKey();
        $data = $this->table->get($key);
        $now = (int)(microtime(true) * 1000);
        if (!$data){
            $data = ['limit_count'=>0, 'time'=>0];
        }
        if ($data['time'] + $this->limitGap > $now){
            $data['limit_count'] += 1;
            $result = $this->limitTips;
        }
        $data['time'] = $now;
        $this->table->set($key, $data);
        return $result;
    }

    function end($response){}

    /* 生成配置中ip、arg-xxx对应本次请求的值 */
    private function _setRawkeyVal()
    {
        foreach ($this->rawkeyMethods as $rawKey =>$funcInfo){
            $func = $funcInfo[0];
            $param = $funcInfo[1];
            $this->keyResult[$rawKey] = call_user_func($func, $param);
        }
    }

    /* 生成单次请求的id */
    private function _makeKey()
    {
        if (!$this->keyResult){
            return 'default';
        }
        return implode(':', $this->keyResult);
    }

    private function _isWhite()
    {
        foreach ($this->limitWhite as $rawKey => $pool){
            if (isset($this->keyResult[$rawKey]) && isset($pool[$this->keyResult[$rawKey]])){
                return true;
            }
        }
        return false;
    }

    private function _isBlack()
    {
        foreach ($this->limitBlack as $rawKey => $pool){
            if (isset($this->keyResult[$rawKey]) && isset($pool[$this->keyResult[$rawKey]])){
                return true;
            }
        }
        return false;
    }

    public static function _getFd()
    {
        return \Woocan\Core\Context::baseCoGet('_fd');
    }

    //\Swoole\Table的运行状态
    function tableStats()
    {
        return $this->table ? $this->table->stats() : null;
    }

    function showAll()
    {
        $ret = [];
        if ($this->table) {
            foreach ($this->table as $k => $item){
                $item['_id'] = $k;
                $ret[$k] = $item;
            }
        }
        return $ret;
    }

    function __destruct()
    {
        if ($this->gcTimer) {
            \Swoole\Timer::clear($this->gcTimer);
        }
    }
}