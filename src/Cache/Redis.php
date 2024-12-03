<?php
namespace Woocan\Cache;

use Woocan\Core\Pool;
use Woocan\Core\Interfaces\Cache as ICache;

class Redis implements ICache
{
    private $poolKey;
    private $expiration = 10800;

    public function __construct($config)
    {
        if (!isset($config['pool_key']) ) {
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'pool_key');
        }
        $this->poolKey = $config['pool_key'];

        if (isset($config['cache_expire'])) {
            $this->expiration = $config['cache_expire'];
        }
    }

    private function _redis()
    {
        $connectionPool = Pool::factory( $this->poolKey );
        return $connectionPool->pop();
    }
    
    public function set($key, $value, $expiration = null)
    {
        if ($expiration === null) {
            $expiration = $this->expiration;
        }
		if ($expiration <= 0) {
            return false;
        }
        $value = json_encode($value);
        return $this->_redis()->setex($key, $expiration, $value);
    }

    public function get($key)
    {
        $ret = $this->_redis()->get($key);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function getCache($key)
    {
        return $this->get($key);
    }

    public function delete($key)
    {
        return $this->_redis()->del($key);
    }

    public function increment($key, $offset = 1)
    {
        return $this->_redis()->incrBy($key, $offset);
    }

    public function decrement($key, $offset = 1)
    {
        return $this->_redis()->decBy($key, $offset);
    }

    public function clear()
    {
        return $this->_redis()->flushDB();
    }

    function deletes($keyPre)
    {
        $redis = $this->_redis();

        $it = NULL;
        while ($arr_keys = call_user_func_array(array($redis, 'scan'), array(&$it, $keyPre.'*'))) {
            if (is_array($arr_keys))
            {
                //dump($arr_keys);
                $redis->del($arr_keys);
            }
        }
    }
}