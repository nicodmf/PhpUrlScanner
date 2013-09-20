<?php
/**
 * Please refer to uri-bnf.txt to see BNF for a grammar parsing URIs.
**/
namespace Alinea\URLScanner;

use ArrayObject;
use Exception;

class URL
{
    // var : initial state //

    /**
     * @var string
    **/
    protected $url_str;

    /**
     * @var self
    **/
    protected $referer;

    /**
     * @var boolean
    **/
    protected $is_booted = false;

    // var : helper //

    /**
     * @var LoggerInterface
    **/
    protected $logger;

    // var : parsed and sanitized URL parts //

    protected $scheme;
    protected $domain;
    protected $host;
    protected $query;
    protected $path;
    protected $file;

    // public //

    public function __construct($url_str, self $referer = null)
    {
        $this->url_str = $url_str;
        $this->referer = $referer;
    }

    /**
     * Allow read-only access to fields.
    **/
    public function __get($name)
    {
        if (! $this->is_booted) {
            $this->boot();
        }
        if (isset($this->{$name})) {
            return $this->{$name};
        }
        throw new Exception('Unknown property "' . $name . '" in ' . get_called_class() . ' class');
    }

    public function __toString()
    {
        return print_r($this, true);
    }

    // public : get as string //

    public function long()
    {
        return implode('', [
            $this->scheme,
            '://',
            $this->host,
            $this->short(),
        ]);
    }

    public function short()
    {
        return implode('', [
            $this->path,
            substr($this->path, -1) !== '/' && isset($this->file)
                ? '/'
                : '',
            $this->file,
            isset($this->query)
                ? '?' . $this->query
                : '',
        ]);
    }

    // protected //

    protected function boot()
    {
        LogDebug::add($this->url_str, 'url_creation');
        $url = new ArrayObject(parse_url($this->url_str), ArrayObject::ARRAY_AS_PROPS);
        LogDebug::add($url, 'url_creation');
        if (isset($url->scheme)) {
            $this->scheme = $url->scheme;
        } elseif ($this->referer) {
            $this->scheme = $this->referer->scheme;
        } else {
            throw new Exception('The url "' . $this->url_str . '" should have a scheme, or a referer "' . $this->referer . '" with a scheme');
        }

        if (isset($url->host)) {
            $this->host = $url->host;
        } elseif ($this->referer) {
            $this->host = $this->referer->host;
        } else {
            throw new Exception('The url >"' . $this->url_str . '" should have a host, or a referer "' . $this->referer . '" with a host');
        }

        $this->domain = preg_replace('#.*\.([\w-_]*\.[\w-_]*)$#', '$1', $this->host);

        $this->query = isset($url->query) ? $url->query : null;
        $path = isset($url->path) ? $url->path : null;

        LogDebug::add($this, 'url_creation');

        /* set file */
        $matches = null;
        preg_match_all('#[$|/]([^/]*\.([\w]{2,5}))$#', $path, $matches, PREG_PATTERN_ORDER);
        if (isset($matches[1][0])) {
            $this->file = $matches[1][0];
        } elseif (preg_match('#^[^/]*\.[\w]{2,5}$#', $path)) {
            $this->file = $path;
        } else {
            $this->file = null;
        }

        LogDebug::add('file: ' . $this->file, 'url_creation');

        /* set path */
        LogDebug::add('path: ' . $path, 'url_creation');
        $path = str_replace($this->file, '', $path);
        LogDebug::add('path: ' . $path, 'url_creation');

        if ($this->referer != null && $this->referer->host != $this->host) {
            $this->referer = null;
        }

        if ($this->referer == null) {
            if (strlen($path) != 0 && $path[0] !== '/') {
                $path = '/' . $path;
            } elseif (strlen($path)==0) {
                $path = '/';
            }
        } else {
            if (strlen($path) != 0 && $path[0] !== '/') {
                $path = $this->referer->path . '/' . $path;
            } elseif (strlen($path) == 0) {
                $path = $this->referer->path;
            }
        }
        LogDebug::add('path before clean_relative: ' . $path, 'url_creation');
        $path = $this->clean_relative($path);
        $this->path = str_replace('//', '/', $path);
        LogDebug::add('path after clean_relative: ' . $this->path, 'url_creation');

        $this->is_booted = true;
    }

    protected function clean_relative($path)
    {
        $a = explode('/', $path);
        $la = count($a);
        for ($i = 0; $i < $la; $i++) {
            switch ($a[$i]) {
                case '.':
                    unset($a[$i]);
                    break;

                case '..':
                    $find = false;
                    unset($a[$i]);
                    for ($j = $i - 1; !$find; $j--) {
                        if (isset($a[$j])) {
                            unset($a[$j]);
                            $find = true;
                        }
                    }
                    break;
            }
        }
        $path = '';
        foreach ($a as $d) {
            if ($d == '') {
                continue;
            }
            $path .= '/' . $d;
        }
        $path = $path == '' ? '/' : $path;
        return $path;
    }
}
