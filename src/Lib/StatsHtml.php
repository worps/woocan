<?php
namespace Woocan\Lib;

use \Woocan\Core\Config;
use \Woocan\Core\Pool;
use \Woocan\Core\Factory;
use \Woocan\Core\Request;
use \Woocan\Connector\Redis;

/**
 * @author LHT
 * 框架页面展示
 * 路由解析到该方法后，该方法返回html即可生成页面
 */
class StatsHtml
{
    use \Woocan\AppBase\Ctrl;

    private $tplEngine;

    function __construct()
    {
        $config = [
            'view_dir' => WOOCAN_PATH.'/Lib/Template',
            'cache_dir'=> C('project.log_path') .'/../compile'
        ];
        $this->tplEngine = new TemplateEngine($config);
    }

    function index()
    {
        $vars['config'] = Config::all();
        unset($vars['config']['pool']); //隐藏数据连接信息
        
        $vars['redis_stats'] = $this->_redisInfo();

        if (IS_SWOOLE) {
            $vars['online'] = $this->_getOnline();
            $vars['api_stats'] = Factory::getInstance('\\Woocan\\Midware\\ApiStats')->showAll();
            $vars['pdo_stats'] = Factory::getInstance('\\Woocan\\Midware\\PdoStats')->showAll();
            $vars['req_limit'] = Factory::getInstance('\\Woocan\\Midware\\RequestLimit')->showAll();
            $vars['tables_stats'] = \Woocan\Core\Table::stats();
            $vars['swoole_stats'] = array_merge(\Woocan\Boot::$serv->stats(), \co::stats());
            
            foreach (C('pool') as $name => $config) {
                $poolInstance = Pool::factory($name);
                $retain = $poolInstance->getRetainSize();
                $total = $poolInstance->getTotalSize();
                $totalUsed = $poolInstance->getTotalUsedNum();
                $capacity = $poolInstance->getCapacity();
                $vars['pool'][$name] = [
                    'connector' => shortName($config['connector']),
                    'retain_size' => $retain,
                    'total_size' => $total,
                    'total_used' => $totalUsed,
                    'capacity'   => $capacity,
                ];
            }

            // 排序
            $vars['api_order'] = $sk = Request::getParams('api_order');
            if ($sk) {
                usort($vars['api_stats'], function($a, $b) use($sk) {
                    return $b[$sk] - $a[$sk];
                });
            }
        }
        $html = $this->tplEngine->fetch('stats.html', $vars);
        return $html;
    }

    /* 限流统计 */
    public function reqLimitStats()
    {
        $fields = array(
            '_id' => '规则key',
            'time' => '最后请求时间',
            'limit_count' => '被禁止次数',
        );
        $connHandler = Factory::getInstance('\\Woocan\\Midware\\RequestLimit');
        $data = $connHandler->showAll();
        return [
            '_view_mode' => 'Str',
            '_content' => $this->_tableShow2($fields, $data),
        ];
    }

    private function _getOnline()
    {
        $ret = [];
        $list = ConnMapper::getAll();
        foreach ($list as $fd => $info) {
            $ret[] = [
                'fd' => $fd,
                'info' => $info,
            ];
        }
        return $ret;
    }
    
    /* redis—info */
    private function _redisInfo()
    {
        $ret = [];

        foreach (C('pool') as $name => $item) {
            if ($item['connector'] === Redis::class) 
            {
                $key = $item['host'].':'. $item['port'];

                if (isset($ret[$key])) {
                    $ret[$key]['names'][] = $name;
                } else {
                    $redis = Pool::factory($name)->pop();
                    $info = $redis->info();
                    $slow = $redis->slowlog('get', 10);
    
                    $slowHtml = '';
                    foreach($slow as $item){
                        $time = date('Y-m-d H:i:s', $item[1]);
                        $cost = round($item[2] / 1000, 1);
                        $cmd = is_array($item[3]) ? json_encode($item[3]) : $item[3];
                        
                        $slowHtml .= "【{$time}】【{$cost}ms】{$cmd}<br>";
                    }
                    $info['slow_log'] = $slowHtml;
                    $info['names'][] = $name;
                    
                    $ret[$key] = $info;
                }
                
            }
        }
        return $ret;
    }
}