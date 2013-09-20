
Url Scanner
===========

A command line utility which scans an entire site, a subfolder of a site, or set of given urls.

The library is designed to be simple and fast. For example, it takes 4 seconds to analyse silex site which contains 150 urls (68 crawled resources and 82 `curl`ed links).

Installation
------------
To install scanner as a stand-alone utility, clone the repo and run [composer](http://getcomposer.org) to install dependencies.

```
$ git clone https://github.com/nicodmf/PhpUrlScanner.git PhpUrlScanner
$ cd PhpUrlScanner
$ composer install
```

The package can be also installed as a set of classes in case you would like to call them from your project. Add repo info to your `composer.json` and `require` it:

```
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/nicodmf/PhpUrlScanner"
        }
    ],
    "require": {
        "alinea/scanner" : "dev-master"
    }
}
```

Usage
-----
Call the

The class can be simply use by typing in the command line :

```
$ php scanner 'url' [ [url2] [url2] ... [url3] ]
```

Simple tests
------------

```
wget https://github.com/fabpot/Goutte/blob/b966bcbd7220bc5cbfe0d323e22499aa022a6c75/goutte.phar && wget https://raw.github.com/nicodmf/PhpUrlScanner/master/scanner.php && php scanner.php http://getcomposer.org
```

In this example, the 404 are normal as the pages demand an identification and refuse simple connection without following another url.

You can test too another url :

```
php scanner.php http://silex.sensiolabs.org/
```

Use with php code
-----------------

Three statics methods provide the scan process :
```php
<?php
use Alinea\UrlScanner\Scanner;

Scanner::collect_and_return($url, $test_externals, $with_subpath, $with_sub_domain, $max_depth);

Scanner::collect_and_save($url, $file, $test_externals, $with_subpath, $with_sub_domain, $max_depth);

Scanner::get_status($url);
```

As the scan take time, the simpliest way is to collect and save the result in a serialized file. This file could be simply unserialized later to permit functionnals analysis.

The serialized file contains the "Resources" object created in the scan process.

Internal
--------
The scan is a loop which crawl html files, identify links (for now just links in an anchor tag), analyses and sorts those: external links will analyzed by simple curls request, internals resources are crawled by Goutte.

Future plans
------------
If the libs interessed some peoples :
 - Integration of symfony command component
 - Better composer/packagist integration
 - Compilation as a phar
 - Utilisation of child class for the storage class resources/resource/url
 - Add link in script, header, css files
 - Add other controls (w3c compliance for css/html)

Acknowledgments
---------------
This package makes a heavy use of [Goutte](https://github.com/fabpot/Goutte.git) library by the allmighty Fabien Potencier.
