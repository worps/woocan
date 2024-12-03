<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2019/12/14
 * Time: 15:49
 */

namespace Woocan\Midware;

use \Woocan\Core\Request;
use \Woocan\Core\Interfaces\Midware as IMidware;

/**
 * Class XhprofStats
 * @package \Woocan\Lib
 * 性能分析统计
 */
class XhprofStats implements IMidware
{
    private $enable = true;
    private $savePath = '/tmp/xhprof/';
    private $catchMicrotime = 0;

    function initial($config)
    {
        if (!function_exists('tideways_xhprof_enable')){
            $this->enable = false;
            return;
        }
        if (isset($config['save_path'])){
            $this->savePath = $config['save_path'];
        }
        if (isset($config['catch_microtime'])){
            $this->catchMicrotime = $config['catch_microtime'];
        }
        dirMake($this->savePath);
    }

    function start($params)
    {
        if ($this->enable){
            $ctrls = explode('\\', Request::getCtrl());
            $ctrl = end($ctrls);
            if ($ctrl == 'FrameHtml'){
                return;
            }
            //开启
            tideways_xhprof_enable();
        }
    }

    function end($response)
    {
        if ($this->enable) {
            $ctrls = explode('\\', Request::getCtrl());
            $ctrl = end($ctrls);
            if ($ctrl == 'FrameHtml'){
                return;
            }

            if ($logData = tideways_xhprof_disable()) {
                $costmicro = floor($logData['main()']['wt'] / 1000);
                if ($costmicro > $this->catchMicrotime) {
                    $filepath = sprintf('%s/%s-%d-%s-%s.xhprof', $this->savePath, date('mdHis'), $costmicro, $ctrl, Request::getMethod());
                    file_put_contents($filepath, serialize($logData));
                }
            }
        }
    }
}