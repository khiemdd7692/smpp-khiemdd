# MSProGateWay(SMPP Server+Client)

[![MSProGateWay License](https://poser.pugx.org/simple-swoole/simps/license)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E=7.1-brightgreen.svg)](https://www.php.net)
[![Swoole Version](https://img.shields.io/badge/swoole-%3E=4.4.0-brightgreen.svg)](https://github.com/swoole/swoole-src)

🚀MSProGateWay 是一个基于PHP + Swoole + Hyperf编写的轻量级，超高性能短信网关，支持SMPP
V3.4协议，用于搭建基于SMPP协议的短信服务平台，亦可做为客户端使用。

### 🛠️环境依赖

* Swoole 4.4+
* PHP 7.1+

### ⚙️安装

``` shell
composer require jenawant/smpp
```

### ⚡️独立启动SMPP模拟器（服务端/客户端）

* 安装`Swoole4.4+`扩展和`PHP7.1+` 并`clone`本项目
* `cd demo`
* 参考`config-sample.ini`生成`config.ini`配置文件，按需修改配置文件
* `php server.php`启动服务端
* `php client.php 1 2`启动客户端模拟发送短信，测试性能。第一个参数代表启动多少个连接 第二个参数代表发送多少条短信

### ⚡️Hyperf框架内使用

* 参考 [Tcp Server配置](https://hyperf.wiki/3.1/#/zh-cn/tcp-server) 章节创建业务类，并参考`demo/server.php`中的逻辑完善业务类
* ~~执行命令发布配置`php bin/hyperf.php vendor:publish jenawant/smpp`，修改配置文件config/autoload/smpp.php中`callbacks`业务类路径~~
* 参考 [Tcp Server配置](https://hyperf.wiki/3.1/#/zh-cn/tcp-server) 章节编辑服务器配置，指定端口及回调控制器路径
* 重启服务

> demo基于配置文件.ini，实际项目可从config/autoload/smpp.php获取配置。

### 😇鸣谢

* [Swoole PHP协程框架](https://www.swoole.com)
* [Hyperf 一款高性能企业级协程框架](https://hyperf.io/)
* [Smpp simulate](https://gitee.com/wolian-message/simulater)