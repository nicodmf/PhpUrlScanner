<?php
namespace Alinea\URLScanner;

class LogDebug
{
    private $output = true;
    private $log = false;
    private $debug = false;
    private $level = 1;
    private $dir = "logs/";
    private $domain_placement = 20;

    static $instance = null;

    private function __construct()
    {
        $this->dir = __DIR__ ."/".$this->dir. preg_replace("#\\\#", "/", __NAMESPACE__ ) . "/";
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    static public function silent()
    {
        self::get()->level = 0;
        self::get()->output = false;
        self::get()->log = false;
        self::get()->debug = false;
    }

    static public function add($var, $domain = "main", $level=1)
    {
        self::get()->make($var, $domain, $level);
    }

    static private function get(){
        if (self::$instance == null) {
            return self::$instance = new LogDebug();
        }
        return self::$instance;
    }

    private function make($var, $domain, $level)
    {
        if ($this->level < $level) {
            return;
        }
        if (!$this->debug && !$this->log && !$this->output) {
            return;
        }
        if (!is_string($var)) {
            $var = print_r($var, true);
        }
        $output = $stdout = "";

        if ($this->debug) {
            foreach (preg_split("/\n/", $var) as $line) {
                printf("[ %-{$this->domain_placement}s%s ] * %s\n", $domain, date("c"), $line);
            }
        } elseif (!$this->debug && $this->output && $domain === "main") {
            echo $var . "\n";
        }
        if ($this->log) {
            file_put_contents($this->dir.$domain.".log", $output, \FILE_APPEND);
        }
    }
}
