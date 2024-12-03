## example使用说明

### windows环境

> 直接运行start.bat即启动

访问：  
// site首页  
http://127.0.0.1:8005/   
// xhprof面板  
http://127.0.0.1:8005/index.php/stats/xhprof   
// api  
http://127.0.0.1:8005/api.php/index/main  

### swoole环境

> `php 模块文件.php` 运行

访问：  
// site首页  
http://127.0.0.1:8005/   
// xhprof面板  
http://127.0.0.1:8005/stats/xhprof   
// api  
http://127.0.0.1:8005/index/main  

测websocket：
1. 进入http://www.websocket-test.com/  
2. 连接并发送`/index/main`