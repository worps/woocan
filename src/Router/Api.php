<?php
namespace Woocan\Router;

use \Woocan\Core\Request;
use \Woocan\Core\Response;
use \Woocan\Core\Factory;
use \Woocan\Core\Context;
use \Woocan\Core\MyException;
use Woocan\Core\Interfaces\Router as IRouter;

class Api extends Base implements IRouter
{
    //路由配置
    protected $routeRules = [];
    //开启自动路由
    //开启后不需要路由配置而根据/class/method?来确定api位置，类似rpc的路由模式
    protected $autoRoute = false;
    //处理器所在名称空间
    protected $ctrlNamespace;
    //每个api的参数绑定设置
    protected $apiParamBindSet = [];

    function __construct()
    {
        $this->ctrlNamespace = sprintf("\\%s\\ctrl\\%s\\", APP_FULL_NAME, MODULE_NAME);

        $this->autoRoute = C('project.auto_route');
        $this->routeRules = $this->_initRouterMap();
    }

    /**
     * 路由解析
     * 
     * 参数，$httpQuery，格式['path'=>null, 'query'=>['a'=>1, 'b'=>2]]
     **/
    public function dispatch($httpQuery)
    {
        $rule = self::_ruleMatch ($httpQuery);
        if (!$rule) {
            throw new MyException('FRAME_ROUTER_PARSE', $httpQuery['path']); //路由解析错误
        }

        //记录请求上下文
        Context::baseCoSet('_class_name', $rule['class']);
        Context::baseCoSet('_method_name', $rule['method']);
        Context::baseCoSet('_params', $httpQuery['query']);

        $className = $this->ctrlNamespace. $rule['class'];
        $class = Factory::getInstance($className);
        if (!$class || !method_exists($class, $rule['method'])) {
            throw new MyException('FRAME_API_NONE', $httpQuery['path']);
        }

        //前置处理有返回表示请求不通过
        $checkRet = $class->before($httpQuery['query']);
        if ($checkRet) {
            $checkRet = $class->after($httpQuery['query'], $checkRet);
            return Response::display($checkRet);
        }
        //参数绑定
        $bindParams = $this->_bindParam($className, $rule['method'], $httpQuery['query']);
        //调用接口
        $apiRet = $class->{$rule['method']}(...$bindParams);
        //后置处理
        $viewStr = $class->after($httpQuery['query'], $apiRet);
        
        return $viewStr;
    }

    /**
     * 作用：初始化路由配置表
     * 返回：path_preg，数组，path正则配置表
     *       path_full，数组，path全路径配置表
     *       cmd，数组，接口id配置表
     */
    private function _initRouterMap()
    {
        $cmdList = C('project.route_cmd');

        $routeMap = ['path_preg'=>[], 'path_full'=>[], 'cmd'=>[]];
        foreach ($cmdList as $item) {
            if (isset($item['path']) && stripos($item['path'], '@') > 0) { //路径+参数匹配
                $pregItem = [
                    'class' => $item['class'],
                    'method' => $item['method'],
                    'args' => [],
                ];
                $pregItem['path_preg'] = preg_replace_callback("/@([a-zA-Z0-9_]+)/", function($matches) use (&$pregItem){
                    $pregItem['args'][] = $matches[1];
                    return '(.*?)';
                }, $item['path']);
                $pregItem['path_preg'] = '/'.str_replace('/', '\/', $pregItem['path_preg']). '/U';
                $routeMap['path_preg'][] = $pregItem;
            } else if (isset($item['path'])) {
                $routeMap['path_full'][ $item['path'] ] = $item; //全路径匹配（无参数）
            } else if (isset($item['cmd'])) {
                $routeMap['cmd'][ $item['cmd'] ] = $item; //根据接口id匹配
            }
        }
        return $routeMap;
    }

    /* 路由规则匹配 */
    private function _ruleMatch (&$httpQuery)
    {
        if ($httpQuery['path'] !== null) {
            $path = $httpQuery['path'];

            //自动路由
            if ($this->autoRoute) {
                $caller = explode('/', trim($path, '/'));
                if (isset($caller[1])) {
                    return ['class'=>$caller[0], 'method'=>$caller[1]];
                }
            }
            //全路径匹配
            if (isset($this->routeRules['path_full'][ $path ])) {
                return $this->routeRules['path_full'][ $path ];
            }
            //路径+参数匹配
            if (isset($this->routeRules['path_preg'])) {
                foreach ($this->routeRules['path_preg'] as $item) {
                    if (preg_match($item['path_preg'], $path, $matches)) {
                        for ($i=0; $i<count($matches)-1; $i++) {
                            $argName = $item['args'][$i];
                            $httpQuery['query'][$argName] = $matches[$i + 1];
                        }
                        return [
                            'class' => $item['class'],
                            'method' => $item['method'],
                        ];
                    }
                }
            }
        }
        //接口id匹配
        if (isset($httpQuery['query']['cmd']) && isset($this->routeRules['cmd'][ $httpQuery['query']['cmd'] ])) {
            return $this->routeRules['cmd'][ $httpQuery['query']['cmd'] ];
        }
        //默认页
        if ($httpQuery['path'] == null) {
            $path = '/';
            if (isset($this->routeRules['path_full'][ $path ])) {
                return $this->routeRules['path_full'][ $path ];
            }
        }

        return null;
    }    
}