<?php
namespace app_test\ctrl\site;

use \Woocan\Core\Factory;

/** 服务器状态统计 */
class stats
{
    use \Woocan\AppBase\Ctrl;

    /* 总览 */
    function index()
    {
        $handler = Factory::getInstance('\\Woocan\\Lib\\FrameHtml');
        return $handler->index();
    }

    /* xhprof性能 */
    function xhprof()
    {
        $obj = Factory::getInstance('\\Woocan\\Lib\\XhprofHtml');
        return $obj->index();
    }
};