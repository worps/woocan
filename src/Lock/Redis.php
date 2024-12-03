<?php
namespace Woocan\Lock;
/**
 * @author lht
 * Redis内存锁
 */
use Woocan\Core\Pool;
use Woocan\Core\Context;
use Woocan\Core\Interfaces\Lock as ILock;

class Redis implements ILock
{
    private $expire;
    private $config;
    const KEY_PREFIX = '_lock:';
    const Context_Key = 'context_locks';

    public function __construct($config)
    {
        if (!isset($config['pool_key']) ){
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'pool_key');
        }
        $this->config = $config;
        $this->expire = $config['cache_expire'] ?? 120;
    }

    private function _redis()
    {
        $connectionPool = Pool::factory( $this->config['pool_key'] );
        return $connectionPool->pop();
    }

	/*
	 * 增加一个事务锁
	 */
    public function Lock($uid, $key, $expire=0)
	{
	    if ($expire <= 0){
	        $expire = $this->expire;
	    }
		$retry = 2;
		$mkey = $this->_getKey($uid,$key);
		for ($i = 1; $i <= $retry; $i++) {
			$time = time();
			$ret = $this->_redis()->setnx($mkey, $time+$expire);
			if ($ret){
                $this->_rememberLock($mkey, $uid, $key);
				return true;
			} else {
				$lock_time = $this->_redis()->get($mkey);
				if ($time > $lock_time){ //锁已过期
					$this->_redis()->del($mkey);
				} else { //锁未过期
					Context::sleep(0.5); // wait for 0.5 seconds
				}
			}
		}
		return false;
	}

	/* 记录当前锁 */
	private function _rememberLock($mkey, $uid, $key)
    {
        //写入上下文
        $lockList = Context::get(self::Context_Key) ?? [];
        $lockList[$mkey] = [
            'id' => $uid,
            'key'=> $key,
        ];
        Context::set(self::Context_Key, $lockList);

        //延迟解锁，防止异常不能解
        if (IS_ENABLE_CO) {
            defer(function() use ($uid, $key) {
                $this->_rememberUnlock();
            });
        }
    }

    /* 按记录解锁，防止发生异常时不能解 */
	private function _rememberUnlock()
    {
        $lockList = Context::get(self::Context_Key);
        if ($lockList) {
            foreach ($lockList as $lockInfo) {
                $this->unLock($lockInfo['id'], $lockInfo['key']);
            }
        }
    }

	/* 解锁 */
	public function unLock($uid, $key)
	{
		$mkey = $this->_getKey($uid, $key);
		$rows = $this->_redis()->del($mkey);

		if ($rows > 0) {
            $lockList = Context::get(self::Context_Key) ?? [];
            unset($lockList[$mkey]);
            Context::set(self::Context_Key, $lockList);
        }
	}

	private function _getKey($uid,$key)
	{
		return self::KEY_PREFIX.$uid.'-'.$key;
	}

	function __destruct()
    {
        $this->_rememberUnlock();
    }
}