<?php
/**
 * @author lht
 * php模板输出
 */


namespace Woocan\View;

use \Woocan\Core\Factory;
use \Woocan\Core\Context;
use \Woocan\Core\Response;

class Template extends Base
{
    /** 错误信息展示 */
    public function onError($errorArr)
    {
        $errStr = '<pre>Error: '. print_r($errorArr, true). '</pre>';
        Response::output($errStr);
    }

    /** 异常信息展示 */
    public function onException($errorArr)
    {
        $viewName = '500.html';

        //404
        if (in_array($errorArr['code'], [101, 102])) {
            $viewName = '404.html';
        }
        
        if ($viewName && is_file(C('template.view_dir'). '/'. $viewName)) {
            $handler = Factory::getInstance(\Woocan\Lib\TemplateEngine::class, C('template'));
            $html = $handler->fetch($viewName, $errorArr);
        } else {
            $html = '<pre>Exception: '. print_r($errorArr, true). '</pre>';
        }

        Response::output($html);
    }

    public function display($data)
    {
        if (is_string($data) || is_null($data)) {
            return $data;
        }

        $html = '缺少view_name，查无模板文件';

        if (isset($data['view_name'])) {
            $viewName = $data['view_name'];
            $bindData = $data['bind_data'] ?? null;
            $viewDir = $data['view_dir'] ?? null;
    
            $tplConfig = C('template');
            $handler = Factory::getInstance(\Woocan\Lib\TemplateEngine::class, $tplConfig);
            $html = $handler->fetch($viewName, $bindData, $viewDir);
        }
        
        return $html;
    }
}
