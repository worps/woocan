<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/3/5
 * Time: 18:31
 */

namespace app_test\config;
/*
 * 路由配置
 */
class router
{
    const Cmd_Enum = [
        ['path'=>'/', 'class'=>'index', 'method'=>'main'],
        ['path'=>'/stats', 'class'=>'stats', 'method'=>'index'],            /* 服务器状态监测 */
        ['path'=>'/xhprof', 'class'=>'stats', 'method'=>'xhprof'],          /* 性能分析文件 */
    ];
}