<h1 align="left"><a href="">opensdk-wechat</a></h1>

📦 一个 PHP 微信开发 SDK包。

> 📣 **前言**
>
>   EasyWechat6版本的sdk做的很灵活，但是对于php版本要求太高，所以准备做一个支持php7.1以上的的SDK，本SDK大量参考[EasyWechat](https://www.easywechat.com/)，真的很感谢大神们的肩膀。由于时间仓促，暂时不支持文件上传，不内置缓存类，需要自己实现一个缓存类。
> - 很多代码和架构，我也在学习当中，后面将逐步完善SDK。也非常期待能得到各位大神的指点。
> - 最后，感谢[@overtrue](https://github.com/overtrue)，感谢[EasyWechat](https://github.com/w7corp/easywechat)，感谢全体EasyWechat贡献者。

## Requirement

1. PHP >= 7.1

## Installation

```shell
$ composer require "pgyf/opensdk-wechat:*@dev" -vvv
```

## Usage

使用文档请参考[EasyWechat](https://easywechat.com/6.x/)

```php
<?php

use Pgyf\Opensdk\Wechat\OpenPlatform\Application\Application;

$config = [
    'app_id' => 'wx3cf0f39249eb0exxx',
    'secret' => 'f1c242f4f28f735d4687abb469072xxx',
    'aes_key' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
    'token' => '',
];

$app = new Application($config);
$app->setCache(new TestCache()); //自己实现一个\Psr\SimpleCache\CacheInterface接口的缓存类

```

## License

MIT