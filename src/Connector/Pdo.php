<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/5/15
 * Time: 15:37
 */

namespace Woocan\Connector;

use \Woocan\Core\MyException;
use \Woocan\Core\Context;

class Pdo extends Base
{
    protected $masterPdo;
    protected $slavePdo;
    protected $enableSlave = false;
    protected $tableName;
    protected $config;
    protected $lastSql;
    protected $isTransction = false; //是否在事务中
    protected $defaultOptions = [
        \PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_PERSISTENT       => false
    ];

    //受监控方法
    const Monitored_Methods = ['add','addMulti','replace','update','UpdateBulk','select','find','count','delete','flush','increase','decrease','fetchBySql','execute'];

    public function __construct($config)
    {
        $this->config = $config;
        $this->connectMaster();
        $this->connectSlave();
    }

    public function disconnect()
    {
        //如果是长链接，PDO的析构函数中会断开socket
        $this->masterPdo = null;
        $this->slavePdo = null;
    }

    protected function connectMaster()
    {
        if (isset($this->config['host'])) {
            $options = $this->config['options'] ?? $this->defaultOptions;
            if (!is_array($this->config) || !isset($this->config['user']) || !isset($this->config['password']) || !isset($this->config['db'])) {
                throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS');
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $this->config['host'], $this->config['port'], $this->config['db']);
            $this->masterPdo = new \PDO($dsn, $this->config['user'], $this->config['password'], $options);
        } else {
            if (!is_array($this->config) || !isset($this->config['db'])) {
                throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS');
            }
            $dsn = sprintf('sqlite:%s', $this->config['db']);
            $this->masterPdo = new \PDO($dsn);
        }
        //$this->masterPdo->exec("SET @@GLOBAL.sql_mode=''");
    }

    protected function connectSlave()
    {
        if (!isset($this->config['slave'])) {
            return null;
        }
        
        $options = $this->config['options'] ?? $this->defaultOptions;
        $dsnId = array_rand($this->config['slave']);
        $dsn = $this->config['slave'][$dsnId];
        $this->slavePdo = new \PDO($dsn, $this->config['user'], $this->config['pass'], $options);
    }

    protected function prepare($query)
    {
        $ret = null;
        try {
            $ret = $this->getPdo()->prepare($query);
        } catch (\PDOException $e) {
            if ($e->getCode() == 'HY000') {
                $this->enableSlave ? $this->connectSlave() : $this->connectMaster();
                $ret = $this->getPdo()->prepare($query);
            } else {
                //语义错误
                if ($e->getCode() == 42000) {
                    $exceptSqlFile = ROOT_PATH. '/exceptSQL.log';
                    $log = sprintf("【%s】【%s】%s\n", date('Y-m-d H:i:s'), APP_NAME, $query);
                    file_put_contents($exceptSqlFile, $log, FILE_APPEND | LOCK_EX);
                }
                throw new \Woocan\Core\MyException('FRAME_DB_ERR', null, $e);
            }
        } catch (\Throwable $e){
            throw new \Woocan\Core\MyException('FRAME_DB_ERR', null, $e);
        }
        $this->enableSlave = false;
        return $ret;
    }

    public function table($tableName)
    {
        if (empty($tableName)) {
            return;
        }
        $this->tableName = $tableName;
        return $this;
    }

    public function getLibName()
    {
        return '`' . $this->tableName . '`';
    }

    public function tryUseSlave()
    {
        if ($this->slavePdo) {
            $this->enableSlave = true;
        } else {
            $this->enableSlave = false;
        }
        return $this;
    }

    protected function getPdo()
    {
        if ($this->isTransction || !$this->enableSlave) {
            return $this->masterPdo;
        }
        return $this->slavePdo;
    }

    public function beginTransaction()
    {
        if ($this->isTransction) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', "rebeginTransation");
        }
        if ($this->masterPdo->beginTransaction() == false) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', "beginTransation err:".$this->masterPdo->errno);
        }
        $this->isTransction = true;

        if (IS_ENABLE_CO) {
            defer(function(){
                if ($this->isTransction) {
                    $this->endTransaction(false);
                }
            });
        }

        return $this->masterPdo;
    }

    public function endTransaction($flag)
    {
        if ($flag) {
            $this->masterPdo->commit();
        } else {
            $this->masterPdo->rollBack();
        }
        $this->isTransction = false;
    }

    private function add($data, $onDuplicate = false, $returnType = 0)
    {
        $fields = array_keys($data);
        list($preparParam, $executeParam) = $this->array_prepare($data, $fields);

        $strFields = $this->_getFields($fields);
        $strValues = implode(',', $preparParam);

        $query = 'INSERT INTO ' . $this->getLibName() . '(' . $strFields . ') VALUES (' . $strValues . ')';

        if ($onDuplicate) {
            $updateArr = [];
            foreach ($data as $k => $val) {
                $updateArr[] = "`{$k}`=VALUES(`{$k}`)";
            }
            $query .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $updateArr);
        }

        $statement = $this->prepare($query);
        $statement->execute($executeParam);
        if ($returnType==0) {
            return $statement->rowCount();
        } else {
            return $this->getPdo()->lastInsertId();
        }
    }

    private function addMulti($data, $lastInsertId=false)
    {
        $fields = array_keys(reset($data));

        $lines = [];
        foreach ($data as $key => $item) {
            $row = $this->array_quote($item, $fields);
            $lines[] = '('. $row. ')';
        }

        $values = implode(',', $lines);
        $query = "INSERT INTO {$this->getLibName()} (" . $this->_getFields($fields) . ") VALUES " . $values;
        $this->lastSql = $query;
        $statement = $this->prepare($query);
        $statement->execute();

        return $lastInsertId ? $this->getPdo()->lastInsertId(): $statement->rowCount();
    }

    private function replace($data)
    {
        $fields = array_keys($data);
        list($preparParam, $executeParam) = $this->array_prepare($data, $fields);

        $strFields = '' . implode(',',$fields) . '';
        $strValues = implode(',', $preparParam);

        $query = "REPLACE INTO {$this->getLibName()} ({$strFields}) VALUES ({$strValues})";
        $statement = $this->prepare($query);
        $this->lastSql = $query;
        $params = array();

        foreach ($fields as $field) {
            $params[$field] = $data[$field];
        }
        $statement->execute($executeParam);
        return $this->getPdo()->lastInsertId();
    }

    private function update($data, $where)
    {
        $fields = array_keys($data);
        $strUpdateFields = $this->update_prepare_fields($fields);
        list($tmp, $executeParam) = $this->array_prepare($data, $fields);

        $where = $this->parse_where($where);

        $query = "UPDATE {$this->getLibName()} SET {$strUpdateFields} WHERE {$where}";
        $statement = $this->prepare($query);
        $this->lastSql = $query;
        $statement->execute($executeParam);
        return $statement->rowCount();
    }

    private function UpdateBulk($rows)
    {
        $fields = array();
        $data = array();
        $i = 0;
        foreach ($rows as $index => $row) {
            if ($i == 0) {
                $fields = array_keys($row);
            }
            $data[$index] = '('.$this->array_quote($row , $fields).')';
            $i++;
        }
        $sqlPlus = implode(',' , $data);
        $strFields = implode(',',$fields);
        $KEY_UPDATE = '';
        $fieldsCount = count($fields);
        for ($i=0; $i < $fieldsCount; $i++) {
            $f = $fields[$i];
            if ($i == $fieldsCount - 1) {
                $KEY_UPDATE .= sprintf("`%s`=VALUES(`%s`);", $f, $f);
            } else {
                $KEY_UPDATE .= sprintf("`%s`=VALUES(`%s`),", $f, $f);
            }
        }
        $query = 'INSERT INTO ' . $this->getLibName() . '(' . $strFields . ') VALUES '.$sqlPlus .' ON DUPLICATE KEY UPDATE '.$KEY_UPDATE;
        return $this->execute($query);
    }

    private function select($where = '1', $params = null, $fields = '*', $orderBy = null, $limit = null)
    {
        $query = "SELECT {$fields} FROM {$this->getLibName()}";

        if (empty($params)) {
            $where = $this->parse_where($where);
        }
        $query .= " WHERE {$where}";

        if ($orderBy) {
            $query .= " ORDER BY {$orderBy}";
        }
        if ($limit) {
            $query .= " limit {$limit}";
        }

        $this->lastSql = $query;
        $statement = $this->tryUseSlave()->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function count($where = '1', $params = null)
    {
        $query = "SELECT count(1) as cc FROM {$this->getLibName()}";

        if (empty($params)) {
            $where = $this->parse_where($where);
        }
        $query .= " WHERE {$where}";

        $this->lastSql = $query;
        $statement = $this->tryUseSlave()->prepare($query);
        $statement->execute($params);
        $list = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $list[0]['cc'];
    }

    private function find($where = 1, $params = null, $fields = '*', $orderBy = null)
    {
        $results = $this->tryUseSlave()->select($where, $params, $fields, $orderBy, 1);
        return empty($results) ? null : reset($results);
    }

    private function delete($where, $params = array())
    {
        if (empty($where)) {
            return false;
        }
        $where = $this->parse_where($where);

        $query = "DELETE FROM {$this->getLibName()} WHERE {$where}";
        $statement = $this->prepare($query);
        $statement->execute($params);
        $this->lastSql = $query;
        return $statement->rowCount();
    }

    private function flush()
    {
        $query = "TRUNCATE {$this->getLibName()}";
        $statement = $this->prepare($query);
        $this->lastSql = $query;
        return $statement->execute();
    }

    private function increase($increment, $where)
    {
        if (empty($where) || empty($increment)) {
            return false;
        }
        $where = $this->parse_where($where);

        $setArr = [];
        foreach ($increment as $field => $step) {
            if ($step < 0) {
                throw new MyException('FRAME_DB_ERR', json_encode($increment). 'where='.$where);
            }
            $setArr[] = "`{$field}`=`{$field}`+ {$step}";
        }
        $set = implode(',', $setArr);

        $query = "UPDATE {$this->getLibName()} SET {$set} WHERE {$where}";
        $statement = $this->prepare($query);
        $statement->execute([]);
        $this->lastSql = $query;
        return $statement->rowCount();
    }

    private function decrease($decrement, $where)
    {
        if (empty($where)) {
            return false;
        }
        $where = $this->parse_where($where);

        $setArr = [];
        foreach ($decrement as $field => $step) {
            if ($step < 0) {
                throw new MyException('FRAME_DB_ERR', json_encode($decrement). 'where='.$where);
            }
            $setArr[] = "`{$field}`=`{$field}`- {$step}";
        }
        $set = implode(',', $setArr);

        $query = "UPDATE {$this->getLibName()} SET {$set} WHERE {$where}";
        $statement = $this->prepare($query);
        $statement->execute([]);
        $this->lastSql = $query;
        return $statement->rowCount();
    }

    private function fetchBySql($sql, $params = array())
    {
        $statement = $this->tryUseSlave()->prepare($sql);
        $this->lastSql = $sql;
        $statement->execute($params);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function execute($query, $params = array(), $lastInsertId = false)
    {
        $this->lastSql = $query;
        $statement = $this->prepare($query);
        $ret = $statement->execute($params);

        if ($lastInsertId) {
            $ret = $this->getPdo()->lastInsertId();
        } else {
            $ret = $statement->rowCount();
        }
        return $ret;
    }

    protected function update_prepare_fields($fields)
    {
        $row = [];
        foreach ($fields as $field) {
            $row[] = "$field=?";
        }
        return implode(',', $row);
    }

    protected function array_prepare($row, $fields)
    {
        $preparParam = $executeParam = array();
        foreach ($fields as $key => $field) {
            $preparParam[] = '?';
            $executeParam[] = is_array($row[$field]) ? json_encode($row[$field]) : $row[$field];
        }
        return [$preparParam, $executeParam];
    }

    protected function array_quote($row, $fields)
    {
        $rowList = array();
        foreach ($fields as $key => $field) {
            $rowList[] = $this->_quote($row[$field]);
        }
        return implode(',', $rowList);
    }

    protected function parse_where($where)
    {
        if (!is_array($where)) {
            return $where;
        }
        if (is_array($where) && !empty($where)) {
            $tmp = [];
            foreach ($where as $field => $value) {
                if (is_array($value)) 
                {
                    foreach ($value as $i => $v) {
                        $value[$i] = "'". addslashes($v). "'";
                    }
                    $in = "(". implode(",", $value). ")";
                    $tmp[] = sprintf("`$field` in %s", $in);
                } else
                    $tmp[] = sprintf("`$field`='%s'", addslashes($value));
            }
            return implode(' AND ', $tmp);
        }
        return '1=0';
    }

    protected function _quote($value)
    {
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            $value = json_encode($value);
        }
        return is_string($value) ? "'".addslashes($value)."'" : $value;
    }

    private function _getFields($fields)
    {
        $all_field = [];
        foreach ($fields as $field) {
            $all_field[] = '`' . $field . '`';
        }

        return implode(',', $all_field);
    }

    public function getLastSql()
    {
        return $this->lastSql;
    }

    function __call($name, $args)
    {
        $result = null;

        $preTime = microtime(true) * 1000;
        if (in_array($name, self::Monitored_Methods)) {
            $result = call_user_func_array([$this, $name], $args);
        } else {
            throw new MyException('FRAME_SYSTEM_ERR', "undefined method ".$name);
        }
        $costTime = floor(microtime(true) *1000 - $preTime);

        $backTrace = debug_backtrace();
        $caller = isset($backTrace[1]) ? ($backTrace[1]['class'] ?? 'noClass'). '::'. $backTrace[1]['function'] : 'unknown';

        //收集pdo调用记录
        $pdoStats = Context::get('api_pdo_stats') ?? [];
        $pdoStats[] = ['caller'=>$caller, 'cost'=>$costTime];
        Context::set('api_pdo_stats', $pdoStats);

        //清理掉设置的table（第二次执行table()会污染前一个pop出的数据库连接对象，因为多次pop同一个对象）
        $this->tableName = null;
        
	    return $result;
    }

    function __destruct()
    {
        if ($this->isTransction) {
            $this->endTransaction(false);
        }
        $this->disconnect();
    }
}
