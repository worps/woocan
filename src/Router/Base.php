<?php
namespace Woocan\Router;

use \Woocan\Core\MyException;

abstract class Base 
{
    /**
     * 参数绑定的设置
     */
    protected $apiParamBindSet = [];

    /** 路由分发 */ 
    public abstract function dispatch($httpQuery);

    /* 获取参数类型 */
    protected function _getReflectParamsType($className, $methodName)
    {
        $reflectKey = $className . '::' . $methodName;
        if (!isset($this->apiParamBindSet[$reflectKey])) {
            $method = new \ReflectionMethod($className, $methodName);
            $params = $method->getParameters();
            if (empty($params)) {
                $this->apiParamBindSet[$reflectKey] = [];
            } else {
                foreach ($params as $par) {
                    $this->apiParamBindSet[$reflectKey][$par->getName()] = [
                        'type' => $par->hasType() ? $par->getType() : null, //参数类型
                        'necessary' => !$par->isDefaultValueAvailable(),     //是否必须
                    ];
                }
            }
        }
        return $this->apiParamBindSet[$reflectKey];
    }

    /* 参数绑定 */
    protected function _bindParam($className, $methodName, $params)
    {
        $bindParams = [];
        $needParams = $this->_getReflectParamsType($className, $methodName);
        if (!empty($needParams)) {
            foreach ($needParams as $parName => $parInfo) {
                if (isset($params[$parName])) {
                    //参数类型错误
                    $checkFunc = 'is_'. $parInfo['type'];
                    if ($parInfo['type'] && $checkFunc($params[$parName]) == false) {
                        throw new MyException('FRAME_PARAM_TYPE', $parName);
                    }

                    $bindParams[] = $params[$parName];
                } else {
                    //缺少参数
                    if ($parInfo['necessary']) {
                        throw new MyException('FRAME_PARAM_LESS', $parName);
                    }
                    $bindParams[] = null;
                }
            }
        }
        return $bindParams;
    }
}