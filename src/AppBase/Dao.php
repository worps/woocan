<?php
namespace Woocan\AppBase;

use \Woocan\Core\Factory;
use \Woocan\Core\Pool;

trait Dao
{
    /* 获取数据库服务 */
	public function db($db_name)
	{
	    $connectionPool = Pool::factory($db_name);
		$db = $connectionPool->pop();
		return $db;
	}
	
	/* 获取缓存服务 */
	protected function getCache($cache_name)
	{
        $config = C('cache.'. $cache_name);
		$obj = Factory::getInstance('\\Woocan\\Cache\\', $config);
		return $obj;
	}
	
	/* 生成缓存key */
	protected function makeKey($postfix)
	{
		$proName = C('project.cache_prefix');
		return $proName . $postfix;
	}
	
	/* 获取dao层对象 */
	protected static function D($name)
	{
		$className = sprintf("\\%s\\dao\\%s", APP_FULL_NAME, $name);
		return Factory::getInstance($className);
	}

	/* 锁 */
    protected function getLock()
    {
        $config = C('lock');
        $obj = Factory::getInstance('\\Woocan\\Lock\\', $config);
        return $obj;
    }
}