<?php
namespace Alinea\URLScanner;

use ArrayAccess;
use Exception;
use Iterator;

class Resources implements Iterator, ArrayAccess
{
    protected $root;
    protected $resources;
    public $documents;
    public $links;

    public function addUrl($url)
    {
        LogDebug::add("URL      : ".$url, "addUrl");
        $resource = new Resource($url);
        LogDebug::add("Resource : ".$resource, "addUrl");
        $this->add( $resource );
    }

    public function add(Resource $resource, $referer = null)
    {
        LogDebug::add("add : ".$resource->getUrl(), "add");
        $url = $resource->getUrl();
        if (isset($this->resources[$url])) {
            if (null !== $referer) {
                LogDebug::add("- exists", "add");
                LogDebug::add("- referer added : ".$referer->getUrl(), "add");
                $this->resources[$url]->addReferer($referer);
                /* TODO : Problem with updated resources,
                          the tree should be updated too : investigation
                          and tests needed
                if($this->resources[$url]->depth > $referer->depth+1){
                    $this->resources[$url]->depth;

                }*/
            } else {
                throw new Exception("Adding an exiting resource without referer isn't allowed");
            }
        } else {
            LogDebug::add("- is added", "add");
            if ($referer == null) {
                if (!isset($this->root)) {
                    $this->root = $resource;
                }
                $resource->depth = 0;
            } else {
                $resource->addReferer($referer);
                $resource->depth = $referer->depth+1;
            }
            $resource->setDependances($this->root);
            $this->resources[$url] = $resource;
        }
        LogDebug::add( "/add", "add");
        LogDebug::add( $this->resources, "add", 100);
        return $this->resources[$url];
    }

    public function setRoot($root)
    {
        LogDebug::add("set root : ".$root->getUrl(), "init");
        $this->root = $root;
    }

    public function first()
    {
        reset($this->resources);
        return $this->current();
    }

    /* ArrayAccess Implementation */

    public function offsetExists($key)
    {
        return isset($this->resources[$key]);
    }

    public function offsetGet($key)
    {
        return $this->resources[$key];
    }

    public function offsetSet($key, $value)
    {
        throw new Exception("Documents souldn't be write");
    }

    public function offsetUnset($key)
    {
        throw new Exception("Documents souldn't be write");
    }

    /* Iterator Implementations */

    public function current()
    {
        return current($this->resources);
    }

    public function key()
    {
        return key($this->resources);
    }

    public function next()
    {
        next($this->resources);
    }

    public function rewind()
    {
        reset($this->resources);
    }

    public function valid()
    {
        return isset($this->resources[$this->key()]);
    }
}
