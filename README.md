A set of class which create a command line utility which scans a entire site, a sub folder of a site or set of given urls.
The library is design to be simple and speed. Analysed of silex take by example 4 seconds for 150 urls (68 crawled resources and 82 curled links).

Use
---

The system require goutte in the same folder (https://github.com/fabpot/Goutte.git)

The class can be simply use be type in the command line :

```
php scanner 'url' [ [url2] [url2] ... [url3] ]
```

Simple tests
------------

```
wget https://raw.github.com/fabpot/Goutte/master/goutte.phar && wget https://raw.github.com/nicodmf/PhpUrlScanner/master/scanner.php && php scanner.php http://getcomposer.org
```

In this example, the 404 are normal as the pages demand an identification and refuse simple connection without following another url.

You can test too another url : 

```
php scanner.php http://silex.sensiolabs.org/
```

Use with php code
-----------------

Three statics methods provides the scan process :
```php
<?php
 Scanner::collect_and_return($url, $test_externals, $with_subpath, $with_sub_domain, $max_depth)
 Scanner::collect_and_save($url, $file, $test_externals, $with_subpath, $with_sub_domain, $max_depth)
 Scanner::get_status($url)
```

As the scan take time, the simpliest way is to collect and save the result in a serialized file. This file could be simply unserialized later to permit functionnals analysis.

The serialized file contains the "Resources" object created in the scan process.

Internal
--------
The scan is a loop which crawle html files, identify links (for now just links in an anchor tag), analysed and sorted those: external links will analized by simple curls request, internals resources crawle by Goutte.

Futur
-----
If the libs interessed some peoples :

 - Integration of symfony command component
 - Separation in file for each class
 - Better composer/packagist integration
 - Compilation as a phar
 - Utilisation of child class for the storage class resources/resource/url
 - Add link in script, header, css files
 - Add other controls (w3c compliance for css/html)
