<?php
namespace app_test\dao;

/**
 * mongo_test等库配置信息需要先在public.php中添加
 */
class test
{
    use \Woocan\AppBase\Dao;

    function mongoTest()
    {
        // 清空collection
        $this->db('mongo_test')->coll('tab')->flush();
        // 批量写入
        $this->db('mongo_test')->coll('tab')->addMulti([
            ['name'=>'张三', 'age'=>12],
            ['name'=>'李四', 'age'=>20],
            ['name'=>'王五', 'age'=>25],
            ['name'=>'赵六', 'age'=>30],
        ]);
        

        // 查询年龄>20，且名字为张三或王五，按年龄倒序
        $list = $this->db('mongo_test')->coll('tab')->select([
            'age'=>['$gt'=>11],
            '$or' => [['name'=>'张三'],['name'=>'王五']],
        ], [
            'projection' => ['_id'=>0],
            'sort' => ['age'=>-1],
            'limit' => 2,
        ]);

        // 删除年龄>20且<30
        $this->db('mongo_test')->coll('tab')->delete(['age'=>['$gt'=>20,'$lt'=>30]], 100);
        return $this->db('mongo_test')->coll('tab')->select();
    }

    function pdoTest()
    {
        $db = $this->db('pdo_db');
        // 增
        $db->table('test')->addMulti([
            ['name'=>'张三', 'age'=>20],
            ['name'=>'李四', 'age'=>18],
            ['name'=>'王五', 'age'=>25],
            ['name'=>'赵六', 'age'=>30],
        ]);
        // 删
        $db->table('test')->delete("age>? AND age<?", [20, 30]);
        // 改
        $db->table('test')->update(['name'=>'woocan'], ['name'=>'张三']);
        // 查
        return $db->table('test')->select('age>?', [10], '*', 'age desc');
    }

    function sqliteTest()
    {
        $db = $this->db('example_sqlite');
        // 增
        $db->table('test')->addMulti([
            ['name'=>'张三', 'age'=>20],
            ['name'=>'李四', 'age'=>18],
            ['name'=>'王五', 'age'=>25],
            ['name'=>'赵六', 'age'=>30],
        ]);
        // 删
        $db->table('test')->delete("age>? AND age<?", [20, 30]);
        // 改
        $db->table('test')->update(['name'=>'woocan'], ['name'=>'张三']);
        // 查
        return $db->table('test')->select('age>?', [10], '*', 'age desc');
    }

    function cacheTest()
    {
        $cacher = $this->getCache('global');
        $result = $cacher->get('autoincre') ?? '';

        $result .= mt_rand(0, 9);
        $cacher->set('autoincre', $result);
        return $result;
    }
}