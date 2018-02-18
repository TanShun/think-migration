# thinkphp5.1 数据库迁移工具

基于`top-think/think-migration` v2.0.3，将依赖的Phinx升级至0.9.2。

## 安装

修改composer.json文件，添加如下内容：
```
{
    "require": {
        "topthink/think-migration": "~2"
    },
    "repositories":[
       {
           "type":"git",
           "url":"https://github.com/TanShun/think-migration.git"
       },
    ]
}
```

然后更新依赖：
```
composer update
```
