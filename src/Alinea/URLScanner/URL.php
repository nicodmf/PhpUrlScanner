<?php
namespace Alinea\URLScanner;

use ArrayObject;

class URL
{
    public $scheme;
    public $domain;
    public $host;
    public $query;
    public $path;
    public $file;

    public function __toString()
    {
        return print_r($this, true);
    }

    public function long()
    {
        return
            $this->scheme . "://".
            $this->host .
            $this->path. (substr($this->path,-1) !== "/" && isset($this->file) ? "/" : "") .
            $this->file .
            (isset($this->query) ? "?" . $this->query : "");
    }

    public function short()
    {
        return
            $this->path. (substr($this->path,-1) !== "/" && isset($this->file) ? "/" : "") .
            $this->file .
            (isset($this->query) ? "?" . $this->query : "");
    }

    public function __construct($url_str, URL $referer = null)
    {
        LogDebug::add($url_str, "url_creation");
        $url = new ArrayObject(parse_url($url_str), ArrayObject::ARRAY_AS_PROPS);
        LogDebug::add($url, "url_creation");
        if (isset($url->scheme)) {
            $this->scheme = $url->scheme;
        } elseif (isset($referer)) {
            $this->scheme = $referer->scheme;
        } else {
            throw new \Exception("The url >$url_str< should have a scheme, or a referer >$referer< with a scheme");
        }

        if (isset($url->host)) {
            $this->host = $url->host;
        } elseif (isset($referer)) {
            $this->host = $referer->host;
        } else {
            throw new \Exception("The url >$url_str< should have a host, or a referer >$referer< with a host");
        }

        $this->domain = preg_replace("#.*\.([[:alnum:]-_]*\.[[:alnum:]-_]*)$#", "$1", $this->host);

        $this->query = isset($url->query) ? $url->query : null;
        $path = isset($url->path) ? $url->path : null;

        LogDebug::add($this, "url_creation");

        /* set file */
        $matches = null;
        preg_match_all("#[$|/]([^/]*\.([[:alnum:]]{2,5}))$#", $path, $matches, PREG_PATTERN_ORDER);
        if (isset($matches[1][0])) {
            $this->file = $matches[1][0];
        } elseif (preg_match("#^[^/]*\.[[:alnum:]]{2,5}$#", $path)) {
            $this->file = $path;
        } else {
            $this->file = null;
        }

        LogDebug::add("file: {$this->file}", "url_creation");

        /* set path */
        LogDebug::add("path: {$path}", "url_creation");
        $path = preg_replace("#".$this->file."#", "", $path);
        LogDebug::add("path: {$path}", "url_creation");

        if ($referer != null && $referer->host != $this->host) {
            $referer = null;
        }

        if ($referer == null) {
            if (strlen($path) != 0 && $path[0] !== "/") {
                $path = "/" . $path;
            } elseif (strlen($path)==0) {
                $path = "/";
            }
        } else {
            if (strlen($path) != 0 && $path[0] !== "/") {
                $path = $referer->path."/".$path;
            } elseif (strlen($path) == 0) {
                $path = $referer->path;
            }
        }
        LogDebug::add("path before clean_relative: {$path}", "url_creation");
        $path = $this->clean_relative($path);
        $this->path = preg_replace("#//#", "/", $path);
        LogDebug::add("path after clean_relative: {$this->path}", "url_creation");
    }

    private function clean_relative($path)
    {
        $a = explode("/", $path);
        $la = count($a);
        for ($i = 0; $i < $la; $i++){
            switch ($a[$i]) {
                case ".":
                    unset($a[$i]);
                    break;

                case "..":
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
        $path = "";
        foreach ($a as $d) {
            if ($d == "") {
                continue;
            }
            $path .= "/$d";
        }
        $path = $path == "" ? "/" : $path;
        return $path;
    }
}
