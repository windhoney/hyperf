# Redis

## 安装

```
composer require hyperf/redis
```

## 配置

|     配置项     |  类型   |   默认值    |              备注              |
|:--------------:|:-------:|:-----------:|:------------------------------:|
|      host      | string  | 'localhost' |           Redis地址            |
|      auth      | string  |     无      |              密码              |
|      port      | integer |    6379     |              端口              |
|       db       | integer |      0      |               DB               |
| cluster.enable | boolean |    false    |          是否集群模式          |
|  cluster.name  | string  |    null     |             集群名             |
| cluster.seeds  |  array  |     []      | 集群连接地址数组 ['host:port'] |

```php
<?php
return [
    'default' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', ''),
        'port' => (int) env('REDIS_PORT', 6379),
        'db' => (int) env('REDIS_DB', 0),
        'cluster' => [
            'enable' => (bool) env('REDIS_CLUSTER_ENABLE', false),
            'name' => null,
            'seeds' => [],
        ],
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
];

```

## 使用

`hyperf/redis` 实现了 `ext-redis` 代理和连接池，用户可以直接通过依赖注入容器注入 `\Redis` 来使用 Redis 客户端，实际获得的是 `Hyperf\Redis\Redis` 的一个代理对象。

```php
<?php
use Hyperf\Utils\ApplicationContext;

$container = ApplicationContext::getContainer();

$redis = $container->get(\Redis::class);
$result = $redis->keys('*');
```

## 多库配置

有时候在实际使用中，一个 `Redis` 库并不满足需求，一个项目往往需要配置多个库，这个时候，我们就需要修改一下配置文件 `redis.php`，如下：

```php
<?php

return [
    'default' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', ''),
        'port' => (int) env('REDIS_PORT', 6379),
        'db' => (int) env('REDIS_DB', 0),
        'cluster' => [
            'enable' => (bool) env('REDIS_CLUSTER_ENABLE', false),
            'name' => null,
            'seeds' => [],
        ],
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    // 增加一个名为 foo 的 Redis 连接池
    'foo' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', ''),
        'port' => (int) env('REDIS_PORT', 6379),
        'db' => 1,
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
];

```

### 通过代理类使用

我们可以重写一个 `FooRedis` 类并继承 `Hyperf\Redis\Redis` 类，修改 `poolName` 为上述的 `foo`，即可完成对连接池的切换，示例：

```php
<?php
use Hyperf\Redis\Redis;

class FooRedis extends Redis
{
    // 对应的 Pool 的 key 值
    protected $poolName = 'foo';
}

// 通过 DI 容器获取或直接注入当前类
$redis = $this->container->get(FooRedis::class);

$result = $redis->keys('*');

```

### 使用工厂类

在每个库对应一个固定的使用场景时，通过代理类是一种很好的区分的方法，但有时候需求可能会更加的动态，这时候我们可以通过 `Hyperf\Redis\RedisFactory` 工厂类来动态的传递 `poolName` 来获得对应的连接池的客户端，而无需为每个库创建代理类，示例如下：

```php
<?php
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;

$container = ApplicationContext::getContainer();

// 通过 DI 容器获取或直接注入 RedisFactory 类
$redis = $container->get(RedisFactory::class)->get('foo');
$result = $redis->keys('*');
```

## 集群模式

### 使用 `name`
 
配置 `cluster`，修改修改 `redis.ini`，也可以修改 `Dockerfile` 如下

```
    # - config PHP
    && { \
        echo "upload_max_filesize=100M"; \
        echo "post_max_size=108M"; \
        echo "memory_limit=1024M"; \
        echo "date.timezone=${TIMEZONE}"; \
        echo "redis.clusters.seeds = \"mycluster[]=localhost:7000&mycluster[]=localhost:7001\""; \
        echo "redis.clusters.timeout = \"mycluster=5\""; \
        echo "redis.clusters.read_timeout = \"mycluster=10\""; \
        echo "redis.clusters.auth = \"mycluster=password\"";
    } | tee conf.d/99-overrides.ini \
```

对应 PHP 配置如下

```php
<?php
// 省略其他配置
return [
    'default' => [
        'cluster' => [
            'enable' => true,
            'name' => 'mycluster',
            'seeds' => [],
        ],
    ],
];
```

### 使用 seeds

当然不配置 name 直接使用 seeds 也是可以的。如下

```php
<?php
// 省略其他配置
return [
    'default' => [
        'cluster' => [
            'enable' => true,
            'name' => null,
            'seeds' => [
                '192.168.1.110:6379',
                '192.168.1.111:6379',
            ],
        ],
    ],
];
```