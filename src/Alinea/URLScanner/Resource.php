<?php
namespace Alinea\URLScanner;

class Resource
{
    public $host_dependant;
    public $path_dependant;
    public $domain_dependant;
    public $depth;
    public $isLink;
    public $isDocument;
    public $isntAnalysable;
    public $analyzed;
    protected $url;
    protected $referers;
    protected $status;
    protected $links;

    public function __construct($url_str, $referer = null)
    {
        $this->url = new URL($url_str, isset($referer) ? $referer->getUrl("object") : null);
    }

    public function getUrl($type = "string")
    {
        switch ($type) {
            case "o":
            case "obj":
            case "object":
                return $this->url;

            case "string":
                return $this->url->long();

            default :
                // TODO: Implement it? There is not getRepresentation method in URL class.
                return $this->url->getRepresentation($type);
        }
    }

    public function getReferers()
    {
        return $this->referers;
    }

    public function getLinks()
    {
        return $this->links;
    }

    public function hasStatus()
    {
        return isset($this->status);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function addReferer($referer)
    {
        if (null == $referer)
        {
            throw new \Exception("Referer from ".$this->url." is null");
        }
        $this->referers[] = $referer;
        $referer->addLink($this);
    }

    public function setDependances($root)
    {
        LogDebug::add("setDependances", "dependances");
        LogDebug::add("- This URL : ".$this->url, "dependances");
        LogDebug::add("- Root : ".$root->url, "dependances");

        $this->domain_dependant =
        $this->host_dependant =
        $this->path_dependant =
            false;
        if ($root->url->domain == $this->url->domain) {
            $this->domain_dependant = true;
        }
        if ($root->url->host == $this->url->host) {
            $this->host_dependant = true;
        }
        if (preg_match("#".addcslashes($root->url->path, "()\\/?.*[]{}+$\'")."#", $this->url->path)) {
            $this->path_dependant = true;
        }

        LogDebug::add("Result", "dependances");
        LogDebug::add("    host   : ".$this->host_dependant, "dependances");
        LogDebug::add("    domain : ".$this->domain_dependant, "dependances");
        LogDebug::add("    path   : ".$this->path_dependant, "dependances");
        LogDebug::add("/setDependances", "dependances");
    }

    public function __toString()
    {
        return print_r($this, true);
    }

    private function addLink($link)
    {
        $this->links[$link->getUrl()] = $link;
    }
}
