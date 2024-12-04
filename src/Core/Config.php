<?php
/**
 * @author lht
 * Date: 13-6-17
 * config配置处理
 */

namespace Woocan\Core;

class Config
{
    private static $config = [];

    //默认配置
    const Default_Config = [
        'server_mode'           => 'Fastcgi',
        'project' => [
            'backtrace_full'    => false,           //打印错误的调用栈
            'log_level'         => 2,               //0上线模式，1错误显示+release，2错误显示+debug+release
            'view_mode'         => 'Json',          //输出模型
            'preload_files'     => [],              //预加载文件
            'socket_stream_timeout' => 10,          //socket流（mysql、redis）读取/写入超时时间
            'time_zone'         => 'Asia/Shanghai', //时区
            'unexcept_codes'    => [],              //忽略的异常code
            'cache_prefix'      => '',              //缓存键名前缀
            'auto_route'        => true,            //自动路由
            'route_cmd'         => [],              //路由表
            'msg_decode'        => null,            //收到消息的解码、拆包函数
            'msg_encode'        => null,            //发送前的编码、封包函数
            'cookie_cors'       => false,           //是否允许cookie跨域（https模式）            

            /***** 路径需要根据项目和模块名生成 *****/
            'log_path'          => '%s/tmp/%s/log/',            //日志路径
        ],
        'lock' => array(
            '_adapter' => 'File',                               //锁类型
            'filelock_path' => '%s/tmp/%s/filelock',           //文件锁路径
        ),
        'template' => array(
            'view_dir' => '%s/%s/template',                     //模板路径
            'cache_dir'=> '%s/tmp/%s/compile',                  //模板缓存路径
        ),
        'swoole_main' => [
            'host' => '0.0.0.0',
            'ws_binary'                 => true,                //websocket二进制传输
            'setting' => [
                'open_length_check'     => true,
                'package_length_type'   => 'N',
                'package_body_offset'   => 4,
            ],
        ],
        'swoole_rpc' => [
            'type' => 1,                                        //SWOOLE_SOCK_TCP，也可为SWOOLE_SOCK_UDP
            'setting' => [
                'open_eof_check' => true,
                'package_eof' => "\n",
            ],
        ],
    ];

    /** 获取默认配置 */
    public static function getDefaultConfig()
    {
        $default = self::Default_Config;

        $default['project']['log_path'] = sprintf($default['project']['log_path'], ROOT_PATH, APP_NAME);
        $default['lock']['filelock_path'] = sprintf($default['lock']['filelock_path'], ROOT_PATH, APP_NAME);
        $default['template']['view_dir'] = sprintf($default['template']['view_dir'], ROOT_PATH, APP_FULL_NAME);
        $default['template']['cache_dir'] = sprintf($default['template']['cache_dir'], ROOT_PATH, APP_NAME);
        return $default;
    }

    /* 载入配置 */
    public static function load()
    {
        $moduleCfgFile = sprintf('%s/config/%s/%s.php', ROOT_PATH, APP_NAME, MODULE_NAME);
        $publicCfgFile = sprintf('%s/config/public.php', ROOT_PATH);

        if (! \is_file($moduleCfgFile)) {
            throw new MyException('FRAME_SYSTEM_ERR', "cannot find config file !");
        }

        $cfg = include $moduleCfgFile;
        $defaultCfg = self::getDefaultConfig();
        $publicCfg = \is_file($publicCfgFile) ? include $publicCfgFile : [];
        
        $cfg = array_replace_recursive($defaultCfg, $cfg, $publicCfg);
    	self::$config = self::_mergeKey($cfg);
        return $cfg;
    }

    private static function _mergeKey($config)
    {
        foreach ($config as $name => &$configItem) {
            if (is_array($configItem)) {
                foreach ($configItem as $key => &$item) {
                    if (is_array($item) && ($name === 'pool' || isset($item['pool_key']))) {
                        $item['_key'] = $name. '.'. $key;
                    }
                }
            }
        }
        return $config;
    }

    public static function get($key, $default = null)
    {
        $result = isset(self::$config[$key]) ? self::$config[$key] : $default;
        return $result;
    }

    public static function set($key, $value, $set = true)
    {
        if ($set) {
            self::$config[$key] = $value;
        } else {
            if (empty(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }

        return true;
    }

    public static function getField($key, $field, $default = null)
    {
        $result = isset(self::$config[$key][$field]) ? self::$config[$key][$field] : $default;
        return $result;
    }

    public static function setField($key, $field, $value, $set = true)
    {
        if ($set) {
            self::$config[$key][$field] = $value;
        } else {
            if (empty(self::$config[$key][$field])) {
                self::$config[$key][$field] = $value;
            }
        }

        return true;
    }

    public static function all()
    {
        return self::$config;
    }
}
