<?php
/**
 * author: lht
 * Date: 13-6-17
 */
namespace Woocan\Core;

class Table
{
    private static $instances = [];

    /* 创建table */
    public static function create($key, $tableSize, $columns)
    {
        $table = new \Swoole\Table($tableSize);
        foreach ($columns as $item) {
            //$table->column('count', \Swoole\Table::TYPE_INT, 4);
            $table->column($item[0], $item[1], $item[2]);
        }
        $table->create();

        self::$instances[$key] = &$table;
        return $table;
    }

    /* 获取所有table的状态 */
    public static function stats()
    {
        //4.8.0以上版本才能有统计
        $canStats = (defined('SWOOLE_VERSION') && version_compare(SWOOLE_VERSION, '4.8.0', '>='));

        $result = [];
        foreach (self::$instances as $key => $table)
        {
            if ($canStats) {
                $result[$key] = json_encode($table->stats());
            } else {
                $result[$key] = round($table->memorySize/1048576, 2). 'M';
            }
        }
        return $result;
    }
}
