<?php
/**
 * Please refer to uri-bnf.txt to see BNF for a grammar parsing URIs.
**/
namespace Alinea\URLScanner;

require_once __DIR__ . '/goutte.phar';

use Goutte\Client;

class Resources implements \Iterator, \ArrayAccess{

	protected $root;
	protected $resources;
	public $documents;
	public $links;

	public function addUrl($url){
		LogDebug::add("URL      : ".$url, "addUrl");
		$resource = new Resource($url);
		LogDebug::add("Resource : ".$resource, "addUrl");
		$this->add( $resource );
	}

	public function add(Resource $resource, $referer=null){
		LogDebug::add("add : ".$resource->getUrl(), "add");
		$url = $resource->getUrl();
		if(isset($this->resources[$url])){
			if(null!==$referer){
				LogDebug::add("- exists", "add");
				LogDebug::add("- referer added : ".$referer->getUrl(), "add");
				$this->resources[$url]->addReferer($referer);
				/* TODO : Problem with updated resources,
				          the tree should be updated too : investigation
						  and tests needed
				if($this->resources[$url]->depth > $referer->depth+1){
					$this->resources[$url]->depth;

				}*/
			}else{
				throw new \Exception("Adding an exiting resource without referer isn't allowed");
			}
		}else{
			LogDebug::add("- is added", "add");
			if($referer==null){
				if(!isset($this->root)){
					$this->root = $resource;
				}
				$resource->depth = 0;
			}else{
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
	public function setRoot($root){
		LogDebug::add("set root : ".$root->getUrl(), "init");
		$this->root = $root;
	}


	public function first(){reset($this->resources); return $this->current();}
	/* ArrayAccess Implementation */
	public function offsetExists($key){return isset($this->resources[$key]);}
	public function offsetGet($key){return $this->resources[$key];}
	public function offsetSet($key, $value){throw new \Exception("Documents souldn't be write");}
	public function offsetUnset($key){throw new \Exception("Documents souldn't be write");}

	/* Iterator Implementations */
	public function current(){return current($this->resources);}
	public function key(){return key($this->resources);}
	public function next(){next($this->resources);}
	public function rewind(){reset($this->resources);}
	public function valid(){return isset($this->resources[$this->key()]);}
}
class Scanner{

	const CURL_LIMIT = 100;

	public $resources;

	protected $should_be_host_dependant;
	protected $should_be_domain_dependant;
	protected $should_be_path_dependant;
	protected $should_test_externals;
	protected $should_follow_depth;
	protected $max_depth;

	private $toTouch = array();

	public function __construct($str_url, $test_externals=true, $with_subpath=false, $with_sub_domain=false, $max_depth=false){
		if($with_sub_domain){
			$this->should_be_host_dependant = false;
			$this->should_be_domain_dependant = true;
		}else{
			$this->should_be_host_dependant = true;
			$this->should_be_domain_dependant = false;
		}
		if(is_numeric($max_depth)){
			$this->should_follow_depth = true;
			$this->max_depth = $max_depth;
		}
		$this->should_be_path_dependant = ! $with_subpath;
		$this->should_test_externals = $test_externals;

		$this->resources = new Resources();
		$this->resources->add(new Resource($str_url));
	}

	public function save_resources_as_serialized_string($file){
		file_put_contents($file, serialize($this->resources));
		LogDebug::add("# File saved to $file\n".
			"#  - This is a serialize object Alinea\URLScanner\Resources\n".
			"#  - If you want use this object in another class\n".
			"#	 you could use a Promote wich cast an entire object\n".
			"#	 or make an preg_replace on the serialized string");
	}
	public function collect(){
		$this->crawle_all();
		$this->display_result();
	}
	static public function collect_and_return($url, $test_externals=true, $with_subpath=false, $with_sub_domain=false, $max_depth=false){
		$scanner = new Scanner($url, $test_externals, $with_subpath, $with_sub_domain);
		$scanner->collect();
		return $scanner;
	}
	static public function collect_and_save($url, $file, $test_externals=true, $with_subpath=false, $with_sub_domain=false, $max_depth=false){
		$scanner = new Scanner($url, $test_externals, $with_subpath, $with_sub_domain);
		$scanner->collect();
		$scanner->save_resources_as_serialized_string($file);
	}

	static public function get_status($url){
		LogDebug::silent();
		$scanner = new Scanner($url, true, false, false, -1);
		$scanner->crawle_all();
		return $scanner->resources->first()->getStatus();
	}

	public function display_result(){
		$documents_counts = $links_counts = array();
		$count_links = $count_documents = null;
		foreach($this->resources as $resource){
			LogDebug::add($resource->getUrl(), "display", 1);
			$status = $resource->getStatus();
			if($resource->isLink){
				$count_links = isset($count_links) ? $count_links+1 : 1;
				$counts[$status]['links'] = isset($counts[$status]['links'])
					? $counts[$status]['links']+1
					: 1;
			}else{
				$count_documents=isset($count_documents) ? $count_documents+1 : 1;
				$counts[$status]['documents'] = isset($counts[$status]['documents'])
					?$counts[$status]['documents']+1
					:1;
			}
		}

		ksort($documents_counts);
		ksort($links_counts);

		$res = "#\n";
		$res.= "# $count_documents documents et $count_links links\n";
		$res.= "#\n";
		$res.= "#  ----------------------------------------- \n";
		$res.= "# |       Code      | Documents |     Links |\n";
		$res.= "#  ----------------------------------------- \n";
		foreach($counts as $status=>$count){
			$res.= sprintf("# | %-15s | %9s | %9s |\n",
				$status,
				isset($count['documents']) ? $count['documents'] : "",
				isset($count['links']) ? $count['links'] : "");
			$res.= "#  -----------------------------------------\n";
		}
		LogDebug::add("$res#");
		return $res;

	}

	private function should_be_crawled($resource){
		LogDebug::add("URL analysed: ".$resource->getUrl(), "should_be_crawled");
		if($this->is_external($resource)){
			LogDebug::add(" - will not crawled, it is not internal", "should_be_crawled");
			return false;
		}
		if( ! $this->is_curlable($resource)){
			LogDebug::add(" - will not crawled, it not curlable", "should_be_crawled");
			return false;
		}
		if( ! $this->is_in_depth($resource)){
			LogDebug::add(" - will not crawled, it not curlable", "should_be_crawled");
			return false;
		}
		LogDebug::add(" + will crawled, it is internal and curlable", "should_be_crawled");
		return true;
	}

	private function is_curlable($resource){
		if( ! in_array($resource->getUrl("o")->scheme, array("http","https")))
			return false;
		return true;
	}

	private function is_external($resource){
		if($this->should_be_host_dependant)
			if( ! $resource->host_dependant) return true;
		if($this->should_be_path_dependant)
			if( ! $resource->path_dependant) return true;
		if($this->should_be_domain_dependant)
			if( ! $resource->domain_depandant) return true;
		return false;
	}

	private function is_in_depth($resource){
		if($this->should_follow_depth)
			if( $resource->depth > $this->max_depth ) return false;
		return true;
	}

	private function crawle_all(){
		LogDebug::add("Begin collect of {$this->resources->first()->getUrl()}");
		$this->externals = array();
		foreach($this->resources as $resource){
			if( ! $resource->hasStatus() ){
				if($this->should_be_crawled($resource)){
					$this->secure_crawle($resource);
					$resource->isDocument = true;
				}elseif($this->is_curlable($resource)){
					$resource->isLink = true;
					$this->externals[] = $resource;
					$resource->setStatus("external");
				}else{
					$resource->isntAnalysable = true;
					$resource->analysed = false;
					$resource->setStatus("not analyzed");
				}
			}
			count($this->externals) > self::CURL_LIMIT ? $this->touch_externals() : true;
		}
		$this->touch_externals();
	}
	private function secure_crawle($resource){
		LogDebug::add("Crawl ".$resource->getUrl(), 'engine');
		try{
			try{
				try{
					$this->crawle($resource);
				}catch(Zend\Http\Exception\InvalidArgumentException $e){
					throw new \Exception($e->getMessage());
				}
			}catch(Zend\Http\Header\Exception\RuntimeException $e){
				throw new \Exception($e->getMessage());
			}
		}catch(\Exception $e){
			//print_r($e);
			$resource->setStatus('GoutteException');//$e->getMessage());
			LogDebug::add("GoutteException Request ".$resource->getUrl());
		}
	}

	private function is_valid_url($url){
		LogDebug::add($url, "is_valid");
		if($url=="" or $url==null or $url==false) return false;
		if(preg_match("/^javascript:/", $url)) return false;
		if(preg_match("/^#/", $url)) return false;
		return true;
	}
	private function crawle($referer){
		$client = new Client();
		$client->followRedirects();
		$crawler = $client->request('GET', $referer->getUrl());
		$statut = $client->getResponse()->getStatus();
		$referer->setStatus($statut);
		$referer->analysed = true;
		LogDebug::add($statut." Request ".$referer->getUrl());
		$referer->isDocument = true;
		foreach($crawler->filter('a')->extract(array('href')) as $link){
			LogDebug::add("Href: $link", "is_valid");
			if(!$this->is_valid_url($link)) { LogDebug::add("-", "is_valid"); continue;}
			LogDebug::add("+", "is_valid");
			$this->resources->add(new Resource($link, $referer), $referer);
		}
	}
	private function touch_externals(){
		if(count($this->externals)==0 || !$this->should_test_externals)
			return;
		$mh = curl_multi_init();
		foreach($this->externals as $k=>$resource){
			LogDebug::add(" - Before curl Link ".$resource->getUrl(), "curl");
			$ch[$k] = curl_init();
			curl_setopt_array($ch[$k], array(
					CURLOPT_URL => $resource->getUrl(),
					CURLOPT_HEADER => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS => 2,
					CURLOPT_HTTPHEADER => array(
						'Referer: http://www.alinea.im or another',
						'Origin: http://www.alinea.im or another',
						'Content-Type: application/php',
						'User-Agent: Alinea\URLScanner check status of page',
					),
					CURLOPT_RETURNTRANSFER => 1,
				)
			);
			curl_multi_add_handle($mh, $ch[$k]);
		}

		$active = null;
		do {
			$status = curl_multi_exec($mh, $active);
		} while ($status === CURLM_CALL_MULTI_PERFORM || $active);

		foreach($this->externals as $k=>$resource){
			$status = curl_getinfo($ch[$k], CURLINFO_HTTP_CODE);
			LogDebug::add($status . " ---Link ".$resource->getUrl());
			if($status!=200){
				LogDebug::add(curl_getinfo($ch[$k]), "curl");
			}
			LogDebug::add(curl_getinfo($ch[$k]), "curl");
			$resource->setStatus($status);
			$resource->analysed = true;
			curl_multi_remove_handle($mh, $ch[$k]);
		}
		curl_multi_close($mh);
		$this->externals = array();
	}
}

if(isset($argc) && getcwd()==__DIR__){
	if($argc==2){
		Scanner::collect_and_return($argv[1]);
	}
	elseif($argc==3)
		switch($argv[1]){
			case "status": echo Scanner::get_status($argv[2])."\n"; break;
			default : Scanner::collect_and_save($argv[1], $argv[2]);
		}
	elseif($argc>3){
		#Scanner::collect_many_and_save($argv[1]);
		$url = $argv[1];
		$file = $argv[2];
		unset($argv[0], $argv[1], $argv[2]);
		$scanner = new Scanner($url);
		foreach($argv as $other_url){
			$scanner->resources->addUrl($other_url);
		}
		$scanner->collect();
		$scanner->save_resources_as_serialized_string($file);
	}
}
