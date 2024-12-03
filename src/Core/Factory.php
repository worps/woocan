<?php
/**
 * author: lht
 * Date: 13-6-17
 */
namespace Woocan\Core;

class Factory
{
    private static $instances = array();

    public static function getInstance($classNamePre, ...$params)
    {
        $className = $keyName = $classNamePre;
        if (isset($params[0]) && is_array($params[0])) {
            if (isset($params[0]['_adapter'])) {
                $className .= $params[0]['_adapter'];
                $keyName .= $params[0]['_adapter'];
            }
            if (isset($params[0]['_key'])){
                $keyName .= $params[0]['_key'];
            }
        }
        if (!isset(self::$instances[$keyName])) {
            self::$instances[$keyName] = new $className(...$params);
        }

        if (method_exists(self::$instances[$keyName], 'init')) {
            self::$instances[$keyName]->init(...$params);
        }
        return self::$instances[$keyName];
    }
}
