<?php
/* URI BNF Rules

RFC 1738            Uniform Resource Locators (URL)        December 1994
Berners-Lee, Masinter & McCahill                               [Page 17-19]

genericurl     = scheme ":" schemepart
; Specific predefined schemes are defined here; new schemes
; may be registered with IANA
url            = httpurl | ftpurl | newsurl |
                 nntpurl | telneturl | gopherurl |
                 waisurl | mailtourl | fileurl |
                 prosperourl | otherurl

; new schemes follow the general syntax
otherurl       = genericurl

; the scheme is in lower case; interpreters should use case-ignore
scheme         = 1*[ lowalpha | digit | "+" | "-" | "." ]
schemepart     = *xchar | ip-schemepart

; URL schemeparts for ip based protocols:
ip-schemepart  = "//" login [ "/" urlpath ]
login          = [ user [ ":" password ] "@" ] hostport
hostport       = host [ ":" port ]
host           = hostname | hostnumber
hostname       = *[ domainlabel "." ] toplabel
domainlabel    = alphadigit | alphadigit *[ alphadigit | "-" ] alphadigit
toplabel       = alpha | alpha *[ alphadigit | "-" ] alphadigit
alphadigit     = alpha | digit
hostnumber     = digits "." digits "." digits "." digits
port           = digits
user           = *[ uchar | ";" | "?" | "&" | "=" ]
password       = *[ uchar | ";" | "?" | "&" | "=" ]
urlpath        = *xchar    ; depends on protocol see section 3.1

; The predefined schemes:
; FTP (see also RFC959)
ftpurl         = "ftp://" login [ "/" fpath [ ";type=" ftptype ]]
fpath          = fsegment *[ "/" fsegment ]
fsegment       = *[ uchar | "?" | ":" | "@" | "&" | "=" ]
ftptype        = "A" | "I" | "D" | "a" | "i" | "d"

; FILE
fileurl        = "file://" [ host | "localhost" ] "/" fpath

; HTTP
httpurl        = "http://" hostport [ "/" hpath [ "?" search ]]
hpath          = hsegment *[ "/" hsegment ]
hsegment       = *[ uchar | ";" | ":" | "@" | "&" | "=" ]
search         = *[ uchar | ";" | ":" | "@" | "&" | "=" ]

; GOPHER (see also RFC1436)
gopherurl      = "gopher://" hostport [ / [ gtype [ selector
                 [ "%09" search [ "%09" gopher+_string ] ] ] ] ]
gtype          = xchar
selector       = *xchar
gopher+_string = *xchar

; MAILTO (see also RFC822)
mailtourl      = "mailto:" encoded822addr
encoded822addr = 1*xchar               ; further defined in RFC822

; NEWS (see also RFC1036)
newsurl        = "news:" grouppart
grouppart      = "*" | group | article
group          = alpha *[ alpha | digit | "-" | "." | "+" | "_" ]
article        = 1*[ uchar | ";" | "/" | "?" | ":" | "&" | "=" ] "@" host

; NNTP (see also RFC977)
nntpurl        = "nntp://" hostport "/" group [ "/" digits ]

; TELNET
telneturl      = "telnet://" login [ "/" ]

; WAIS (see also RFC1625)
waisurl        = waisdatabase | waisindex | waisdoc
waisdatabase   = "wais://" hostport "/" database
waisindex      = "wais://" hostport "/" database "?" search
waisdoc        = "wais://" hostport "/" database "/" wtype "/" wpath
database       = *uchar
wtype          = *uchar
wpath          = *uchar

; PROSPERO
prosperourl    = "prospero://" hostport "/" ppath *[ fieldspec ]
ppath          = psegment *[ "/" psegment ]
psegment       = *[ uchar | "?" | ":" | "@" | "&" | "=" ]
fieldspec      = ";" fieldname "=" fieldvalue
fieldname      = *[ uchar | "?" | ":" | "@" | "&" ]
fieldvalue     = *[ uchar | "?" | ":" | "@" | "&" ]

; Miscellaneous definitions
lowalpha       = "a" | "b" | "c" | "d" | "e" | "f" | "g" | "h" |
                 "i" | "j" | "k" | "l" | "m" | "n" | "o" | "p" |
                 "q" | "r" | "s" | "t" | "u" | "v" | "w" | "x" |
                 "y" | "z"
hialpha        = "A" | "B" | "C" | "D" | "E" | "F" | "G" | "H" | "I" |
                 "J" | "K" | "L" | "M" | "N" | "O" | "P" | "Q" | "R" |
                 "S" | "T" | "U" | "V" | "W" | "X" | "Y" | "Z"

alpha          = lowalpha | hialpha
digit          = "0" | "1" | "2" | "3" | "4" | "5" | "6" | "7" |
                 "8" | "9"
safe           = "$" | "-" | "_" | "." | "+"
extra          = "!" | "*" | "'" | "(" | ")" | ","
national       = "{" | "}" | "|" | "\" | "^" | "~" | "[" | "]" | "`"
punctuation    = "<" | ">" | "#" | "%" | <">


reserved       = ";" | "/" | "?" | ":" | "@" | "&" | "="
hex            = digit | "A" | "B" | "C" | "D" | "E" | "F" |
                 "a" | "b" | "c" | "d" | "e" | "f"
escape         = "%" hex hex

unreserved     = alpha | digit | safe | extra
uchar          = unreserved | escape
xchar          = unreserved | reserved | escape
digits         = 1*digit

*/

namespace Alinea\URLScanner;

require_once __DIR__ . '/goutte.phar';


use Goutte\Client;
use ArrayObject;

class LogDebug{
	private $output = true;
	private $log = false;
	private $debug = false;
	private $level = 1;
	private $dir = "logs/";
	private $domain_placement = 20;
	static $instance = null;
	
	
	private function __construct(){
		//throw new Exception( "preg".preg_replace("#\\\#", "/", __NAMESPACE__ ));
		$this->dir = __DIR__ ."/".$this->dir. preg_replace("#\\\#", "/", __NAMESPACE__ ) . "/";
		if(!file_exists($this->dir))mkdir($this->dir, 0777, true);
	}
	static public function silent(){
		self::get()->level = 0;
		self::get()->output = false;
		self::get()->log = false;
		self::get()->debug = false;		
	}
	static public function add($var, $domain="main", $level=1){
		self::get()->make($var, $domain, $level);		
	}	
	static private function get(){
		if(self::$instance==null)return self::$instance = new LogDebug();
		return self::$instance;
	}
	private function make($var, $domain, $level){		
		if($this->level<$level) return;
		if( !$this->debug && !$this->log && !$this->output)return;	
		//$head = sprint_f($this->model, $domain, 0, strlen($domain) );
		if(!is_string($var))$var = print_r($var, true);
		$output = $stdout = "";
		
		if($this->debug){
			foreach(preg_split("/\n/", $var) as $line){
				printf("[ %-{$this->domain_placement}s%s ] * %s\n", $domain, date("c"), $line);
			}
		}elseif(!$this->debug && $this->output && $domain==="main") echo $var."\n";
		if($this->log) file_put_contents($this->dir.$domain.".log", $output, \FILE_APPEND);
	}
}

class URL{
	public $scheme;
	public $domain;
	public $host;
	public $query;
	public $path;
	public $file;
	
	public function __toString(){
		return 	print_r($this, true);
	}	
	public function long(){
		return 	
			$this->scheme . "://".
			$this->host .
			$this->path. (substr($this->path,-1)!=="/" && isset($this->file) ? "/" : "") .
			$this->file .
			(isset($this->query) ? "?" .$this->query : "" );
	}
	
	public function short(){
		return 	
			$this->path. (substr($this->path,-1)!=="/" && isset($this->file) ? "/" : "") .
			$this->file .
			(isset($this->query) ? "?" .$this->query : "" );
	}
	
	public function __construct($url_str, URL $referer=null){
		LogDebug::add($url_str, "url_creation");
		$url = new ArrayObject(parse_url($url_str), ArrayObject::ARRAY_AS_PROPS);
		LogDebug::add($url, "url_creation");
		if(isset($url->scheme))
			$this->scheme = $url->scheme;
		elseif(isset($referer))
			$this->scheme = $referer->scheme;
		else
			throw new \Exception("The url >$url_str< should have a scheme, or a referer >$referer< with a scheme");

		if(isset($url->host))
			$this->host = $url->host;
		elseif(isset($referer))
			$this->host = $referer->host;
		else
			throw new \Exception("The url >$url_str< should have a host, or a referer >$referer< with a host");
		
		$this->domain = preg_replace("#.*\.([[:alnum:]-_]*\.[[:alnum:]-_]*)$#", "$1", $this->host);
			
		$this->query = isset($url->query) ? $url->query : null;		
		$path = isset($url->path) ? $url->path : null;
		
		LogDebug::add($this, "url_creation");
		/* set file */
		$matches = null;
		preg_match_all("#[$|/]([^/]*\.([[:alnum:]]{2,5}))$#", $path, $matches, PREG_PATTERN_ORDER);
		if(isset($matches[1][0])){
			$this->file = $matches[1][0];
		}elseif(preg_match("#^[^/]*\.[[:alnum:]]{2,5}$#", $path)){
			$this->file = $path;
		}else{
			$this->file = null;
		}
		
		LogDebug::add("file: {$this->file}", "url_creation");
		/* set path */
		LogDebug::add("path: {$path}", "url_creation");
		$path = preg_replace("#".$this->file."#", "", $path);	
		LogDebug::add("path: {$path}", "url_creation");
		
		if($referer!=null && $referer->host!=$this->host)
			$referer=null;
	
		if($referer==null){
			if(strlen($path)!=0 && $path[0]!=="/" )
				$path="/".$path;
			elseif(strlen($path)==0)
				$path="/";
		}else{
			if(strlen($path)!=0 && $path[0]!=="/" )
				$path = $referer->path."/".$path;
			elseif(strlen($path)==0 )
				$path = $referer->path;
		}
		LogDebug::add("path before clean_relative: {$path}", "url_creation");
		$path = $this->clean_relative($path);
		$this->path = preg_replace("#//#", "/", $path);
		LogDebug::add("path after clean_relative: {$this->path}", "url_creation");
	}
	private function clean_relative($path){
		$a = explode("/", $path);
		$la = count($a);
		for($i=0; $i<$la; $i++){
			switch($a[$i]){
				case ".": unset($a[$i]); break;
				case "..": $find = false;	unset($a[$i]);
					for($j=$i-1; !$find; $j--){
						if(isset($a[$j])){ unset($a[$j]); $find=true; }						
					}
					break;
			}
		}
		$path="";
		foreach($a as $d){
			if($d=="") continue;
			$path.="/$d";
		}
		$path = $path==""?"/":$path;
		return $path;
	}
}
class Resource{
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
	function __construct($url_str, $referer=null){
//		$this->origin = $url_str;
		$this->url = new URL($url_str, isset($referer) ? $referer->getUrl("object") : null);
	}
	public function getUrl($type="string"){
		switch($type){
			case "o":
			case "obj": 
			case "object": return $this->url;
			case "string": return $this->url->long();
			default : $this->url->getRepresentation($type);
		}
	}
	
	public function getReferers(){return $this->referers;}
	public function getLinks(){return $this->links;}
	public function hasStatus(){return isset($this->status);}
	public function getStatus(){return $this->status;}
	public function setStatus($status){$this->status=$status;}

	public function addReferer($referer){
		if(null==$referer) throw new \Exception("Referer from ".$this->url." is null");
		$this->referers[] = $referer;
		$referer->addLink($this);
	}
	
	public function setDependances($root){
		LogDebug::add("setDependances", "dependances");
		LogDebug::add("- This URL : ".$this->url, "dependances");
		LogDebug::add("- Root : ".$root->url, "dependances");
		$this->domain_dependant = $this->host_dependant = $this->path_dependant = false;		
		if($root->url->domain == $this->url->domain) $this->domain_dependant = true;
		if($root->url->host == $this->url->host) $this->host_dependant = true;
		if(preg_match("#".addcslashes($root->url->path, "()\\/?.*[]{}+$\'")."#", $this->url->path)) $this->path_dependant 
= true;		
		LogDebug::add("Result", "dependances");
		LogDebug::add("    host   : ".$this->host_dependant, "dependances");
		LogDebug::add("    domain : ".$this->domain_dependant, "dependances");
		LogDebug::add("    path   : ".$this->path_dependant, "dependances");
		LogDebug::add("/setDependances", "dependances");		
	}

	public function __toString(){
		return print_r($this, true);
	}
	
	private function addLink($link){
		$this->links[$link->getUrl()] = $link;
	}
}

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
		$res.= "#  --------------------------------------- \n";
		$res.= "# |      Code     | Documents |     Links |\n";
		$res.= "#  --------------------------------------- \n";
		foreach($counts as $status=>$count){
			$res.= sprintf("# | %-13s | %9s | %9s |\n",
				$status,
				isset($count['documents']) ? $count['documents'] : "",
				isset($count['links']) ? $count['links'] : "");
			$res.= "#  ---------------------------------------\n";
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
			$resource->setStatus($e->getMessage());		
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
		$resource->analysed = true;
		LogDebug::add("Request ".$referer->getUrl()." status: $statut");
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
			LogDebug::add("  -Link ".$resource->getUrl().":  $status");
			if($status!=200){
				LogDebug::add(curl_getinfo($ch[$k]));
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

if(isset($argc) && $_SERVER['SCRIPT_FILENAME']==__FILE__ && getcwd()==__DIR__){
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
