<?php
namespace Woocan\Lib;

use \Woocan\Core\Config;
use \Woocan\Core\Pool;
use \Woocan\Core\Factory as cFactory;

/**
 * @author LHT
 * 框架页面展示
 * 路由解析到该方法后，该方法返回html即可生成页面
 */
class FrameHtml
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
            $vars['api_stats'] = cFactory::getInstance('\\Woocan\\Midware\\ApiStats')->showAll();
            $vars['req_limit'] = cFactory::getInstance('\\Woocan\\Midware\\RequestLimit')->showAll();
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
        $connHandler = \Woocan\Core\Factory::getInstance('\\Woocan\\Midware\\RequestLimit');
        $data = $connHandler->showAll();
        return [
            '_view_mode' => 'Str',
            '_content' => $this->_tableShow2($fields, $data),
        ];
    }

    private function _getOnline()
    {
        $ret = [];
        $config = C('conn');
        if (!$config) {
            return $ret;
        }

        $connHandler = cFactory::getInstance('\\Woocan\\Conn\\', $config);
        $list = $connHandler->getAll();
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
            if ($item['connector'] === \Woocan\Connector\Redis::class) 
            {
                $key = $item['host'].':'. $item['port'];

                if (isset($ret[$key])) {
                    $ret[$key]['names'][] = $name;
                } else {
                    $redis = \Woocan\Core\Pool::factory($name)->pop();
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