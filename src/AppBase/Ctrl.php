<?php
/**
 * @author lht
 *  controller 接口基类
 */
namespace Woocan\AppBase;

use \Woocan\Core\Context;
use \Woocan\Core\Factory;
use Woocan\Core\Request;
use \Woocan\Core\Response;
use \Woocan\Core\Pool;

trait Ctrl
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
        $className = sprintf("\\%s\\service\\%s", APP_FULL_NAME, $name);;
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
                if ($content_check && $this->isSensiveStr($value)) {
                    throw new \Woocan\Core\MyException('FRAME_PARAM_SENSIVE', $field);
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

    /* 是否包含emoji和注入字符 */
    protected function isSensiveStr($str)
    {
        //检查单引号
        if (strpos($str,"'") !== false) {
            return true;
        }

        //检查emoji符
        $mat = [];
        preg_match_all('/./u', $str, $mat);
        foreach ($mat[0] as $v){
            if(strlen($v) > 3) return true;
        }
        return false;
    }

    /**
     * 模板输出变量 
     * $field 为数组时表示批量输出
     **/
    protected function assign($field, $value=null)
    {
        $assigns = is_string($field) ? [$field=>$value] : $field;
        $bindData = Context::baseCoGet('_tplBindData') ?? [];

        foreach ($assigns as $k => $v) {
            $bindData[$k] = $v;
        }
        Context::baseCoSet('_tplBindData', $bindData);
    }

    /* 获取模板渲染所需数据 */
    protected function fetch($viewName)
    {
        $bindData = Context::baseCoGet('_tplBindData') ?? [];
        $viewModel = [
            '_view_mode' => 'Template',
            'bind_data' => $bindData,
            'view_name' => $viewName,
        ];
        return $viewModel;
    }

    /* 跳转 */
    protected function jump($url, $msg=null, $timeout=0, $binddata=[])
    {
        $binddata['url'] = $url;
        $binddata['msg'] = $msg;
        $binddata['timeout'] = $timeout;

        $headers = Request::getHttpInfo('header');
        if (isset($headers['x-requested-with']) && $headers['x-requested-with'] === 'XMLHttpRequest') { //ajax
            $binddata['_view_mode'] = 'Json';
            $viewModel = $binddata;
        } else {
            $preBindData = Context::baseCoGet('_tplBindData') ?? [];
            $viewModel = [
                '_view_mode' => 'Template',
                'bind_data' => array_merge($preBindData, $binddata),
                'view_dir' => WOOCAN_PATH. '/Lib/Template',
                'view_name' => 'jump.html',
            ];
        }
        return $viewModel;
    }

    /* 成功 */
    public function success($msg, $url=null)
    {
        //url默认为来源页面
        if (!$url) {
            $url = Request::getHttpInfo('server', 'http_referer');
        }
        return $this->jump($url, $msg, 3, ['code'=>1]);
    }

    /* 失败 */
    public function error($msg, $url=null)
    {
        //url默认为来源页面
        if (!$url) {
            $url = Request::getHttpInfo('server', 'http_referer');
        }
        return $this->jump($url, $msg, 3, ['code'=>2]);
    }

    private function _makeurl($url)
    {
        if (strpos($url, 'http:') !== false || strpos($url, 'https:') !== false) {
            return $url;
        }
        $url = trim($url, '/');
        $path = \Woocan\Core\Request::getHttpInfo('server', 'script_name'). '/'. $url;
        return $path;
    }
}