<?php
namespace Woocan\Router;

use \Woocan\Core\Request;
use \Woocan\Core\Response;
use \Woocan\Core\Factory;
use \Woocan\Core\Context;
use \Woocan\Core\Log;
use \Woocan\Core\MyException;
use Woocan\Core\Interfaces\Router as IRouter;

class Rpc extends Base implements IRouter
{
    //处理器所在名称空间
    protected $ctrlNamespace;

    function __construct()
    {
        $this->ctrlNamespace = sprintf("\\%s\\rpc\\%s\\", APP_FULL_NAME, MODULE_NAME);
    }

    /**
     * 路由解析
     * 
     * 参数，$httpQuery，格式['path'=>'class/method/', 'query'=>['a'=>1, 'b'=>2]]
     **/
    public function dispatch($httpQuery)
    {
        $caller = explode('/', trim($httpQuery['path'], '/'));
        $id = $httpQuery['query']['id'];
        $params = json_decode($httpQuery['query']['params'], true);
        
        if (count($caller) != 2) {
            throw new MyException('FRAME_ROUTER_PARSE'); //路由解析错误
        }

        //记录请求上下文
        Context::baseCoSet('_class_name', $caller[0]);
        Context::baseCoSet('_method_name', $caller[1]);
        Context::baseCoSet('_params', $params);

        $className = $this->ctrlNamespace. $caller[0];
        $class = Factory::getInstance($className);
        if (!$class || !method_exists($class, $caller[1])) {
            throw new MyException('FRAME_API_NONE');
        }

        //前置处理有返回表示请求不通过
        $checkRet = $class->before($params);
        if ($checkRet) {
            return Response::display($checkRet);
        }

        //参数绑定
        //$bindParams = $this->_bindParam($className, $caller[1], $params);
        //接口调用
        try {
            $apiRet = $class->{$caller[1]}(...$params);
        } catch (\Throwable $e) {
            if ($e->getCode() == MyException::E_Code['NORMAL_EXIT']) {
                throw $e;
            }
            $apiRet = Log::exception('exception', $e);
        }
        //后置处理
        $viewStr = $class->after($params, $apiRet);

        return $viewStr;
    }
}