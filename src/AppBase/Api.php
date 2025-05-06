<?php
/**
 * @author lht
 *  controller 接口基类
 */
namespace Woocan\AppBase;

use \Woocan\Core\Context;
use \Woocan\Core\Factory;
use \Woocan\Core\Response;
use \Woocan\Core\Pool;

trait Api
{
    /* 调用前置检查 */
    public function before($queryData)
    {
        //前置中间件
        $apiRet = Factory::getInstance('\\Woocan\\Core\\Midware')->before($queryData);
        return $apiRet;
    }

    /* 调用后 */
    public function after($queryData, $viewModel)
    {
        //转输出字符串
        $viewStr = Response::display($viewModel);
        //后置中间件
        Factory::getInstance('\\Woocan\\Core\\Midware')->after($viewStr);

        return $viewStr;
    }

    /* 获取service层对象 */
    protected static function S($name)
    {
        $className = sprintf("\\%s\\service\\%s", APP_FULL_NAME, $name);
        $obj = Factory::getInstance($className);
        return $obj;
    }

    /* 获取dao层对象 */
    protected static function D($name)
    {
        $className = sprintf("\\%s\\dao\\%s", APP_FULL_NAME, $name);
        return Factory::getInstance($className);
    }

    /* 获取数据库服务 */
    public function db($db_name)
    {
        $connectionPool = Pool::factory($db_name);
        $db = $connectionPool->pop();
        return $db;
    }

    /* 获取参数 */
    protected function getParam($field, $type_func, $necessary=true, $content_check=true)
    {
        $params = Context::baseCoGet('_params');

        if (!isset($params[$field]) || $params[$field] === '') {
            if ($necessary) {
                throw new \Woocan\Core\MyException('FRAME_PARAM_LESS', $field);
            } else {
                return null;
            }
        } else {
            $value = $params[$field];
            if ($type_func == 'strval') {
                $value = trim($value);
                if ($content_check) {
                    // 包含单引号
                    if (strpos($value,"'") !== false) {
                        throw new \Woocan\Core\MyException('FRAME_PARAM_SENSIVE', $field);
                    }
                    // 包含emoji表情
                    $regex = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
                    if (preg_match($regex, $value)) {
                        throw new \Woocan\Core\MyException('FRAME_PARAM_SENSIVE', $field);
                    }
                }
                return $value;
            } elseif ($type_func == 'intval') {
                if (!is_numeric($value) && !is_bool($value)) {
                    throw new \Woocan\Core\MyException('FRAME_PARAM_ERR', $field);
                }
                $value = (int)$value;
                if ($content_check && $value < 0) {
                    throw new \Woocan\Core\MyException('FRAME_PARAM_ERR', $field);
                }
                return $value;
            } elseif ($type_func == 'array') {
                if (!is_array($value)) {
                    throw new \Woocan\Core\MyException('FRAME_PARAM_ERR', $field);
                }
                return $value;
            } else {
                return call_user_func($type_func, $value);
            }
        }
    }
}