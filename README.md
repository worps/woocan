![](./logo.png)

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

## <a id="install">快速安装和使用</a>
```
composer create-project worps/wooapp myproject1
cd myproject1
php -S 0.0.0.0:8005
```