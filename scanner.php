<?php
require_once __DIR__ . '/vendor/autoload.php';

use Alinea\URLScanner\Scanner;

if (isset($argc) && getcwd() == __DIR__) {
    if ($argc == 2) {
        Scanner::collect_and_return($argv[1]);
    } elseif ($argc==3) {
        switch ($argv[1]) {
            case "status":
                echo Scanner::get_status($argv[2])."\n";
                break;

            default :
                Scanner::collect_and_save($argv[1], $argv[2]);
        }
    } elseif($argc > 3) {
        #Scanner::collect_many_and_save($argv[1]);
        $url = $argv[1];
        $file = $argv[2];
        unset($argv[0], $argv[1], $argv[2]);
        $scanner = new Scanner($url);
        foreach ($argv as $other_url) {
            $scanner->resources->addUrl($other_url);
        }
        $scanner->collect();
        $scanner->save_resources_as_serialized_string($file);
    }
}
