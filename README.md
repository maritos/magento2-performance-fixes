# magento2-performance-fixes
Performance fixes for magento 2 core.

# Problem and solution's concept - briefly

PHP / Magento doesn't support concurency requests. When Magento is handling numbers of requests at the same time (e.g. some headless frontend sent 10 requests at the same time on page load to ask graphql api for data) then each request got information to start building a cache. If cache exists - everything works perfectly but if it doesn't then first request starts building the cache and each next request do the same too.

It's not hard to imagine situation that when some process is slow and takes for instance 2s then every request will try build a cache until building process has been completed by one of requests. It is leading to situation when N requests are doing the same thing and every new request make process slower and slower the process which started before. Finally when we are talking about high traffic websites it's cause redis crash / high and long disk/cpu usage etc.

IMPORTANT: **Testing just one request (without high concurrency) you likly won't achive any speed improvments.**

# Requirements

One dependancy: `cweagans/composer-patches`

# Compatibility

This solution provides 3 file changes in core:

* #1 - magento/framework/Config/Data.patch - *2.3.0+* 
* #2 - magento/framework/App/ObjectManager/ConfigLoader.patch - *2.2.0+* 
* #3 - magento/framework/Interception/Config/Config.patch - *2.2.0+* 

# Instalation

For this fixes I have chosen fix by vendor's patch. According Magento 2 documentation here: https://devdocs.magento.com/guides/v2.4/comp-mgr/patching/composer.html

1. Step one

install package by composer:

`composer require maritos/magento2-performance-fixes`

2. Extend composer.json

```
    "extra": {
        "enable-patching": true,
        "magento-force": "override",
        "composer-exit-on-patch-failure": true,
        "patches": {
            "magento/framework": {
                "performance fix #1 - vendor/magento/framework/Config/Data.php": "vendor/maritos/magento2-performance-fixes/vendorPatch/magento/framework/Config/Data.patch",
                "performance fix #2 - vendor/magento/framework/App/ObjectManager/ConfigLoader.patch": "vendor/maritos/magento2-performance-fixes/vendorPatch/magento/framework/App/ObjectManager/ConfigLoader.patch",
                "performance fix #3 - vendor/magento/framework/Interception/Config/Config.patch": "vendorPatch/magento/framework/Interception/Config/Config-2.4.x.patch"
            }
        }
    }
```

3. composer install

After `composer install` make sure patches has been applied. Composer install output should similar to:

```
  - Applying patches for magento/framework
    vendor/maritos/magento2-performance-fixes/vendorPatch/magento/framework/Config/Data.patch (performance fix #1 - vendor/magento/framework/Config/Data.php)
    vendor/maritos/magento2-performance-fixes/vendorPatch/magento/framework/App/ObjectManager/ConfigLoader.patch (performance fix #2 - vendor/magento/framework/App/ObjectManager/ConfigLoader.patch)
    vendorPatch/magento/framework/Interception/Config/Config-2.4.x.patch (performance fix #3 - vendor/magento/framework/Interception/Config/Config.patch)
```

# Contributing

Happy to hear your suggestions :) 

# Testing this solution

Please give me feedback via linkedin https://www.linkedin.com/in/mariusz-lopuch/ what improvment you achived. I will really appriciate it!

Thanks!
