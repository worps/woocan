![](example/static/logo.png)

## <a id="description">框架介绍</a>

Woocan是一款兼容php-fpm和swoole的双栖php框架，它允许我们在windows桌面开发、调试，并在linux中启用swoole提升线上运行效率，因此它天生兼具易于开发、运行高效的特点。特别适用于api开发、游戏服务端项目，也可用于网站开发。本框架有以下特点：
- [x] 支持php-fpm、swoole、cli运行模式
- [x] 支持http、websocket、tcp、udp服务
- [x] 支持RPC服务
- [x] 支持接口性能统计分析，1秒找出性能瓶颈
- [x] 内置访问日志、api调用统计、幂等性控制、限流等中间件
- [x] 内置消息队列、锁、排行榜、连接池组件
- [x] 第三方组件支持
- [x] 支持参数注入
- [x] 灵活的路由形式，用户可自由定制

## <a id="qq">交流群</a>

> QQ 1群: 875109578

## <a id="env">环境要求</a>
- php >= 7.3  
- Swoole >= 4.0  

## <a id="struct">目录结构</a>
```
├─app_game              game项目源码目录
│      ├─ctrl           控制器入口
│      │   ├─api        api模块
│      │   └─cron       cron模块
│      ├─rpc            RPC入口
│      │   └─api        api模块的rpc
│      ├─config         配置目录
│      ├─service        service层目录
│      ├─dao            dao层目录
│      └─lib            库文件
├─config                配置目录
│   ├─game              game项目配置目录
│   │   ├─api.php       api模块配置
│   │   └─cron.php      cron模块配置
│   └─public.php        多项目公共配置
├─vendor                第三方库
├─Woocan                Woocan框架
├─tmp                   日志、缓存、临时目录
├─index.php             入口文件
└─cron.php              入口文件
```

## <a id="install">安装</a>
```
git clone https://gitee.com/chrisx/woocan.git
cd woocan/example
 .\start.bat
```