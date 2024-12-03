<?php

namespace Woocan\Connector;
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2019/12/2
 * Time: 14:26
 */
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query;
use MongoDB\Driver\Command;
use MongoDB\BSON\ObjectID;
use \Woocan\Core\Context;

class Mongo extends Base {

    protected $mongodb;
    protected $database;
    protected $collection;
    protected $writeConcern;
    protected $defaultOptions = [
        'authSource'   => 'admin'
    ];

    const Monitored_Methods = ['find', 'select', 'count', 'update', 'add', 'addMulti', 'delete', 'flush'];

    public function __construct($config)
    {
        $mongoServer = "mongodb://";
        if (isset($config['user']) && isset($config['password'])) {
            $mongoServer .= sprintf("%s:%s@", $config['user'], $config['password']);
        }
        $mongoServer .= sprintf("%s:%d/%s", $config['host'], $config['port'], $config['db']);

        $options = $config['options'] ?? $this->defaultOptions;

        $this->mongodb = new Manager($mongoServer, $options);
        $this->database = $config['db'];
        $this->writeConcern = new WriteConcern(WriteConcern::MAJORITY, 100);
    }

    public function coll($collection)
    {
        $this->collection = $collection;
        return $this;
    }

    private function find(array $where = [], $option = [])
    {
        $option['limit'] = 1;
        $list = $this->_fetch($where, $option);
        if (isset($list[0])){
            return $list[0];
        }
        return null;
    }

    private function select(array $where = [], $option = [])
    {
        $option['limit'] = 0;
        return $this->_fetch($where, $option);
    }

    /**
     * @param array $where
     * @param array $option
     * @return array
     * @throws \MongoDB\Driver\Exception\Exception
     * 举例：
     * 在张三、李四、王五中找年龄大于20岁的，取_id外的所有字段，按age倒序
     * _fetch([
            'age'=>['$gt'=>20],
            '$or' => [['name'=>'张三'],['name'=>'王五'], ['name'=>'李四']],
        ], [
            'projection' => ['_id'=>0],
            'sort' => ['age'=>-1],
     *      //'limit' => 2,
     *  ])
     */
    private function _fetch(array $where = [], $option = [])
    {
        if (isset($where['_id'])){
            $where['_id'] = $this->_parseId($where['_id']);
        }
        $query = new Query($where, $option);
        $result = $this->mongodb->executeQuery("$this->database.$this->collection", $query);
        $result = $result->toArray();

        if (isset($result[0]) && isset($result[0]->_id)){
            foreach ($result as &$item){
                $item->_id = (string)$item->_id;
            }
        }
        $result = json_decode(json_encode($result), true);

        return $result;
    }

    private function count(array $where = [])
    {
        if (isset($where['_id'])){
            $where['_id'] = $this->_parseId($where['_id']);
        }
        $command = new Command(['count' => $this->collection, 'query' => $where]);
        $result = $this->mongodb->executeCommand($this->database, $command);
        $res = $result->toArray();
        $count = 0;
        if ($res) {
            $count = $res[0]->n;
        }

        return $count;
    }

    private function update(array $where = [], $update = [], $upsert = false)
    {
        if (isset($where['_id'])){
            $where['_id'] = $this->_parseId($where['_id']);
        }
        unset($update['_id']);

        $bulk = new BulkWrite();
        $bulk->update($where, ['$set' => $update], ['multi' => true, 'upsert' => $upsert]);
        $result = $this->mongodb->executeBulkWrite("$this->database.$this->collection", $bulk, $this->writeConcern);

        return $result->getModifiedCount();
    }

    private function add($data = [], $returnType=0)
    {
        $bulk = new BulkWrite();
        $insertId = (string) $bulk->insert($data);
        $result = $this->mongodb->executeBulkWrite("$this->database.$this->collection", $bulk, $this->writeConcern);
        if ($returnType == 1)
            return $result->getInsertedCount();
        else
            return $insertId;
    }

    private function addMulti($data = [], $returnType=0)
    {
        $bulk = new BulkWrite();

        $insertIds = [];
        foreach ($data as $line){
            $insertIds[] = (string) $bulk->insert($line);
        }
        $result = $this->mongodb->executeBulkWrite("$this->database.$this->collection", $bulk, $this->writeConcern);
        if ($returnType == 1)
            return $result->getInsertedCount();
        else
            return $insertIds;
    }

    private function delete(array $where, $limit=1)
    {
        if (empty($where)){
            throw new \Woocan\Core\MyException('FRAME_PARAM_ERR', 'mongo delete by condition empty');
        }
        return $this->_delete($where, $limit);
    }

    private function flush()
    {
        return $this->_delete([], 0);
    }

    /**
     * @param $where
     * @param $limit 0表示不限制 >0表示删除行数
     * @return int|null
     */
    private function _delete(array $where, $limit)
    {
        if (isset($where['_id'])){
            $where['_id'] = $this->_parseId($where['_id']);
        }
        $bulk = new BulkWrite();
        $bulk->delete($where, ['limit' => $limit]);
        $result = $this->mongodb->executeBulkWrite("$this->database.$this->collection", $bulk, $this->writeConcern);
        return $result->getDeletedCount();
    }

    private function _parseId($item)
    {
        if (is_array($item)){
            foreach ($item as $k => $v){
                $item[$k] = $this->_parseId($v);
            }
            return $item;
        } else {
            try{
                return new ObjectID($item);
            }catch(\Exception $e){
                return null;
            }
        }
    }

    function __call($name, $args)
    {
        $preTime = microtime(true) * 1000;
        if (in_array($name, self::Monitored_Methods)) {
            $result = $this->$name(...$args);
        }
        $costTime = floor(microtime(true) *1000 - $preTime);

        //搜集mongo调用次数和耗时
        Context::set('api_stats_mongo_count', Context::get('api_stats_mongo_count') + 1);
        Context::set('api_stats_mongo_cost', Context::get('api_stats_mongo_cost') + $costTime);

	    return $result;
    }

    public function disconnect()
    {
        $this->mongodb = null;
        $this->writeConcern = null;
    }
}