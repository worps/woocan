<?php
namespace Woocan\AppBase;

use \Woocan\Core\Factory;

trait Service
{
	/* 获取dao层对象 */
	protected static function D($name)
	{
		$className = sprintf("\\%s\\dao\\%s", APP_FULL_NAME, $name);
		return Factory::getInstance($className);
	}
	
	/* 获取service层对象 */
	protected static function S($name, ...$params)
	{
		$className = sprintf("\\%s\\service\\%s", APP_FULL_NAME, $name);
		return Factory::getInstance($className, ...$params);
	}

    /* 锁 */
    protected function getLock()
    {
        $config = C('lock');
        $obj = Factory::getInstance('\\Woocan\\Lock\\', $config);
        return $obj;
    }
}