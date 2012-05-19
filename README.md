A set of class which create a command line utility which scans a entire site, a sub folder of a site or set of given url.

Use
---

The system require goutte in the same folder (https://github.com/fabpot/Goutte.git)

The class can be simply use be type in the command line :

```
php scanner 'url' [ [url2] [url2] ... [url3] ]
```

Use with php code
-----------------

Three method are statically given :
 Scanner::collect_and_return($url, $test_externals, $with_subpath, $with_sub_domain, $max_depth)
 Scanner::collect_and_save($url, $file, $test_externals, $with_subpath, $with_sub_domain, $max_depth)
 Scanner::get_status($url)

As the scan process take time, the simpliest way is to collect and save the result in a serialized file. This file could be simply unserialized later to permit functionnals analysis.

The serialized file contains the Resources object created in the scan process.

Futur
-----
If the libs interessed peoples :

*Integration of symfony command component
*Separation in file for each class
*Compilation as a phar
*Utilisation of child class for the storage class resources/resource/url
