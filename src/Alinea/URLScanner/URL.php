<?php
/**
 * Please refer to uri-bnf.txt to see BNF for a grammar parsing URIs.
**/
namespace Alinea\URLScanner;

use ArrayObject;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class URL implements LoggerAwareInterface
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

    /**
     * Get an instance of logger to write logs to.
     *
     * Original logger wrote to 'url_creation' file.
    **/
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    // public : get as string //

    public function long()
    {
        if (! $this->is_booted) {
            $this->boot();
        }
        return implode('', [
            $this->scheme,
            '://',
            $this->host,
            $this->short(),
        ]);
    }

    public function short()
    {
        if (! $this->is_booted) {
            $this->boot();
        }
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
        if ($this->is_booted) {
            return;
        }

        $this->log($this->url_str);
        $url = new ArrayObject(parse_url($this->url_str), ArrayObject::ARRAY_AS_PROPS);
        $this->log($url);
        if (isset($url->scheme)) {
            $this->scheme = $url->scheme;
        } elseif ($this->referer) {
            $this->scheme = $this->referer->scheme;
        } else {
            throw new Exception(
                'The url "' . $this->url_str . '" should have a scheme, ' .
                'or a referer "' . $this->referer . '" with a scheme'
            );
        }

        if (isset($url->host)) {
            $this->host = $url->host;
        } elseif ($this->referer) {
            $this->host = $this->referer->host;
        } else {
            throw new Exception(
                'The url "' . $this->url_str . '" should have a host, ' .
                'or a referer "' . $this->referer . '" with a host'
            );
        }

        $this->domain = preg_replace('#.*\.([\w-_]*\.[\w-_]*)$#', '$1', $this->host);

        $this->query = isset($url->query) ? $url->query : null;
        $path = isset($url->path) ? $url->path : null;

        $this->log($this);

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

        $this->log('file: ' . $this->file);

        /* set path */
        $this->log('path: ' . $path);
        $path = str_replace($this->file, '', $path);
        $this->log('path: ' . $path);

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
        $this->log('path before clean_relative: ' . $path);
        $path = $this->clean_relative($path);
        $this->path = str_replace('//', '/', $path);
        $this->log('path after clean_relative: ' . $this->path);

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

    protected function log($message)
    {
        if (! $this->logger) {
            return;
        }
        $this->logger->info($message);
    }
}
