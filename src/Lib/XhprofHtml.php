<?php
namespace Woocan\Lib;

use \Woocan\Core\Context;
use \Woocan\Core\Request;

/**
 * @author LHT
 * xhprof文件分析，不依赖其他任何ui库
 */
class XhprofHtml
{
    use \Woocan\AppBase\Ctrl;

    const Read_File_limit = 300;
    
    private $dir = '';
    private $suffix = 'xhprof';
    private $htmlCharset = 'UTF-8';
    private $htmlTitle = '';
    private $tableFields = array(
            'ct'=>'调用次数',
            'wt'=>'总耗时(微秒)',
            'wt_rt'=>'总耗时占比',
            'pt'=>'净耗时(微秒)',
            'pt_rt'=>'净耗时占比',
        
            'create_time'=>'创建时间',
            'run_time'=>'执行耗时(ms)',
        );

    public function __construct()
    {
        $this->dir = C('midware.XhprofStats.save_path');
    }
    
    public function index()
    {
        if (!$this->dir) {
            return 'need configure of midware.XhprofStats.save_path!';
        }
        $params = Request::getParams();
        $run = isset($params['run']) ? $params['run'] : '';
        $symbol = isset($params['symbol']) ? $params['symbol'] : null;
        
        if ($run) {
            $sort = isset($params['sort']) ? $params['sort'] : 'wt';
            $xhprof_data = $this->get_run($run);
            if ($symbol) { //函数被调用列表
                $this->htmlTitle = "Function <b>{$symbol}</b>'s calls of <b>{$run}.xhprof</b>";
                return $this->get_onecall_html($xhprof_data, $symbol, $sort);
            } else { //日志详情
                $this->htmlTitle = "All calls of <b>{$run}.xhprof</b>";
                return $this->get_onerun_html($xhprof_data, $sort);
            }
        } else { //日志列表
            $sort = isset($params['sort']) ? $params['sort'] : 'create_time';
            return $this->get_runs_html($sort);
        }
    }

    private function file_name($run_id) {
        return $this->dir . "/$run_id." . $this->suffix;
    }

    private function array_sort_by_column(&$need_arr, $sort_key)
    {
        $sort_pool = array();
        foreach ($need_arr as $key => $val) {
            $sort_pool[$key] = $val[$sort_key];
        }
        array_multisort($sort_pool, SORT_DESC, $need_arr);
    }
    
    private function array2table($array, $keyName, $sort_key)
    {
        if (!$array) return 'No parent calls';
        $this->array_sort_by_column($array, $sort_key);
        
        $thead = array_intersect_key($this->tableFields, reset($array));
        
        $html = '<table><thead><tr><th>'. $keyName. '</th>';
        foreach ($thead as $field => $fieldName) {
            if ($sort_key == $field) {
                $html .= "<th>{$fieldName}(↓)</th>";
            } else if (in_array($field, ['ct','wt','pt','create_time','run_time'])) {
                $href = $this->create_link(array('sort'=>$field));
                $html .= "<th><a href='{$href}'>{$fieldName}</a></th>";
            } else {
                $html .= "<th>{$fieldName}</th>";
            }
        }
        $html .= "</tr></thead>";
        
        $html .= "<tbody>";
        foreach ($array as $key => $item) {
            $href = isset($item['_href_']) ? $item['_href_'] : '#';
            $html .= "<tr><td><a href='{$href}'>{$key}</a></td>";
            foreach ($thead as $field => $fieldName) {
                $html .= "<td>{$item[$field]}</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }
    
    private function create_html($html)
    {
        $params = Request::getParams();
        
        $home_link = $this->create_link(['run'=>null, 'symbol'=>null,'sort'=>null]);
        $sitemap = "<a href='{$home_link}'>首页</a> ";
        if (isset($params['run']) && !empty($params['run'])) {
            $run_link = $this->create_link(['run'=>$params['run'], 'symbol'=>null]);
            $sitemap .= "&gt;&gt; <a href='{$run_link}'>{$params['run']}.xhprof</a>";
        }
        
        $html = $sitemap. '<br><h3>'. $this->htmlTitle. '</h3><hr>' . $html;
        
        return '<!DOCTYPE html>
            <html lang="zh-cn">
            <head>
                <meta charset="'. $this->htmlCharset .'" />
                <style>b{color: #c00;} a{color: #0000BB;} td{padding-right:15px;}</style>
            </head>
            <body>'. $html .'</body>
            </html>';
    }
    
    /**
     * 统计耗时占比
     * $is_pt，是否统计净耗时占比
     */
    private function add_field_rate(&$array)
    {
        $sum = $array['main()']['wt'];
        
        foreach ($array as &$item) {
            //wt
            $item['wt_rt'] = round($item['wt'] *100 / $sum, 1). '%';
            //pt
            if (isset($item['pt'])) {
                $item['pt_rt'] = round($item['pt'] *100 / $sum, 1). '%';
            }
        }
    }
    
    /* 生成连接url */
    private function create_link($cover_params = array())
    {
        $params = Request::getParams();
        
        foreach ($cover_params as $k => $v) {
            $params[$k] = $v;
        }
        return '?'. http_build_query($params);
    }

    /* 获取单个文件的数据 */
    public function get_run($run_id) {
        $file_name = $this->file_name($run_id);

        if (!file_exists($file_name)) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', "Could not find file $file_name");
        }
        return unserialize(file_get_contents($file_name));
    }
    
    /* 
     * 展示单文件内容 
     * ct=count, wt=wall_time, pt=pure_time
     * */
    public function get_onerun_html($xhprof_data, $sort)
    {
        $list = [];
        foreach ($xhprof_data as $fn => $item) {
            $fnArr = explode('==>', $fn);
            
            if (count($fnArr) == 1) { //入口函数，没有父函数
                $method = $fnArr[0];
                if (!isset($list[$method])) {
                    $list[$method] = array('ct'=>0, 'wt'=>0, 'pt'=>0);
                }
                $list[$method]['ct'] += $item['ct'];
                $list[$method]['wt'] += $item['wt'];
                $list[$method]['pt'] += $item['wt'];
            } else {
                $parent = $fnArr[0];
                $method = $fnArr[1];
                if (!isset($list[$method])) {
                    $href = $this->create_link(array('symbol'=>$method, 'sort'=>null));
                    $list[$method] = array('ct'=>0, 'wt'=>0, 'pt'=>0, '_href_'=>$href);
                }
                if (!isset($list[$parent])) {
                    $href = $this->create_link(array('symbol'=>$parent, 'sort'=>null));
                    $list[$parent] = array('ct'=>0, 'wt'=>0, 'pt'=>0, '_href_'=>$href);
                }
                $list[$method]['ct'] += $item['ct'];
                $list[$method]['wt'] += $item['wt'];
                $list[$method]['pt'] += $item['wt'];
                $list[$parent]['pt'] -= $item['wt'];
            }
        }
        
        $this->add_field_rate($list, true);
        $html = $this->array2table($list, 'Functions', $sort);
        return $this->create_html($html);
    }
    
    /* 展示单文件中函数被调用信息 */
    public function get_onecall_html($xhprof_data, $symbol, $sort)
    {
        $symbol = str_replace('\\', '\\\\', $symbol);
        
        $list = [];
        foreach ($xhprof_data as $fn => $item) {
            if (preg_match("#(.*?)==>{$symbol}$#", $fn, $matches)) {
                $item['_href_'] = $this->create_link(array('symbol'=>$matches[1], 'sort'=>null));
                $list[$matches[1]] = $item;
            }
        }
        
        $html = $this->array2table($list, 'Functions', $sort);
        return $this->create_html($html);
    }

    /* 获取文件列表 */
    function get_runs_html($sort) {
        $list = [];
        if (is_dir($this->dir)) {
            $files = glob("{$this->dir}/*.{$this->suffix}");
            foreach ($files as $file) {
                if (count($list) >= self::Read_File_limit) {
                    break;
                }
                list($run,$source) = explode('.', basename($file));
                $name = htmlentities(basename($file));
                preg_match("#-(\d+)-#", $name, $macth);
                $list[$name] = array(
                    '_href_'=>$this->create_link(['run'=>$run,'sort'=>null]),
                    'run_time'=> $macth[1],
                    'create_time' =>date("Y-m-d H:i:s", filemtime($file)),
                );
            }
            //$this->array_sort_by_column($list, 'create_time');
        }
        
        if (empty($list)) {
            $this->htmlTitle = "No runs in dir (<b>". htmlspecialchars($this->dir) ."</b>)";
            return $this->create_html('');
        } else {
            $this->htmlTitle = "All runs in dir (<b>". htmlspecialchars($this->dir) ."</b>)";
            /*$body = '';
            foreach ($list as $item){
                $body .= "<a href='{$item['href']}'>{$item['name']}</a>&nbsp;&nbsp;{$item['create_time']}<br>";
            }
            return $this->create_html($body);*/
            
            $html = $this->array2table($list, 'Runs', $sort);
            return $this->create_html($html);
        }
    }
}
