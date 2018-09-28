<?php
namespace SapiStudio\Http\Browser;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar as GuzzleCookieJar;
use Symfony\Component\DomCrawler\Crawler;

class StreamClient
{
    protected static $clientConfig =  [
        'method'        => 'curl',
        'defaultUserA'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36',
        'defaultConfig' => ['allow_redirects' => true,'debug' => false],
        'testConfig'    => ['headers' => ['User-Agent' => 'SapiHttpTestLinks'],'allow_redirects'=>false, 'debug' => false,'connect_timeout' => 3.14],
    ];
    protected $transportMethod = null;
    protected $httpClient;
    private $headers        = [];
    private $currentUrl     = null;
    private $defaultOptions = [];
    private $currentRequest = null;
    protected $historyUrl   = [];    
    
    /*
    |--------------------------------------------------------------------------
    | Init methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * StreamClient::make()
     * 
     * @return
     */
    public static function make($options = []){
        return new static($options);
    }

    /**
     * StreamClient::makeToTest()
     * 
     * @return
     */
    public static function makeToTest($options = []){
        return new static(self::array_merge_recursive_distinct(self::$clientConfig['testConfig'],$options));
    }
    
    /**
     * StreamClient::__construct()
     * 
     * @return
     */
    private function __construct($options = []){
        $this->defaultOptions               = self::array_merge_recursive_distinct(self::$clientConfig['defaultConfig'],$options);
        $this->defaultOptions['cookies']    = new GuzzleCookieJar();
        $this->defaultOptions['on_stats']   = function(\GuzzleHttp\TransferStats $stats){$this->currentUrl = $stats->getEffectiveUri()->__toString();$this->historyUrl[] = $stats->getEffectiveUri();};
        $this->defaultOptions['headers']['User-Agent'] = self::$clientConfig['defaultUserA'];
        return $this->setTransport(self::$clientConfig['method']);
    }

    /**
     * StreamClient::__call()
     * 
     * @return
     */
    public function __call($name,$arguments)
    {
        if(method_exists($this->getClient(),$name))
            return $this->getClient()->$name(...$arguments);
        $this->currentRequest   = null;
        $this->currentUrl       = $arguments[0];
        try {
            $this->currentRequest = $this->getClient()->$name($this->currentUrl,$options);
        }catch(RequestException $e){
            $this->currentRequest = $e->getResponse();
            if (null === $this->currentRequest) {
                throw $e;
            }
        }
        $this->defaultOptions = $this->getConfig();
        $this->crawler = $this->createCrawlerFromContent($this->currentUrl, $this->getBody(), $this->getRequestHeader('Content-Type'));
        return $this;
    }
    
    /**
     * StreamClient::cacheRequest()
     * 
     * @return
     */
    public function cacheRequest($token=null){
        return CacheRequest::init($this,$token);
    }
    
    /**
     * StreamClient::validateLinks()
     * 
     * @return
     */
    public function validateLinks($urlLinks = []){
        if(!is_array($urlLinks))
            return false;
        foreach($urlLinks as $keyLink=>$Link){
            if(\SapiStudio\Http\Html::isValidURL($Link)){
                $promises[$keyLink] = $this->headAsync($Link,self::$validateTestHeaders);
            }
        }
        $results = Promise\settle($promises)->wait();
        if($results){
            foreach($results as $promiseIndex=>$promiseResponse){
                $response = $promiseResponse['value'];
                $linkHash = md5($urlLinks[$promiseIndex]);
                $this->validatedLinks[$linkHash]['isAlive']     = ($response) ? true : false;
                $this->validatedLinks[$linkHash]['url']         = $urlLinks[$promiseIndex];
                $this->validatedLinks[$linkHash]['isImage']     = ($response) ? \SapiStudio\Http\Html::urlIsImage($response->getHeaderLine('content-type')) : false;
                $this->validatedLinks[$linkHash]['contentType'] = ($response) ? $response->getHeaderLine('content-type') : false;
            }
        }
        return $this->validatedLinks;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Response methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * StreamClient::getConfig()
     * 
     * @return
     */
    public function getConfig(){
        return $this->getClient()->getConfig();
    }
    
    /**
     * StreamClient::getCookies()
     * 
     * @return
     */
    public function getCookies(){
        return $this->getClient()->getConfig('cookies');
    }

    /**
     * StreamClient::getResponse()
     * 
     * @return
     */
    public function getResponse($url,$arguments=null){
        $this->get($url,$arguments);
        return $this->getBody();
    }

    /**
     * StreamClient::getRequestStatusCode()
     * 
     * @return
     */
    public function getRequestStatusCode(){
        return $this->currentRequest->getStatusCode();
    }

    /**
     * StreamClient::getBody()
     * 
     * @return
     */
    public function getBody(){
        return $this->currentRequest->getBody()->getContents();
    }
    
    /**
     * StreamClient::getRequestHeader()
     * 
     * @return
     */
    public function getRequestHeader($headerName = null){
        return (is_null($headerName)) ? $this->currentRequest->getHeaders() : $this->currentRequest->getHeaderLine($headerName);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Configs methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * StreamClient::setCookiesFromArray()
     * 
     * @return
     */
    public  function setCookiesFromArray(array $cookies)
    {
        $cookieJar = new GuzzleCookieJar();
        foreach($cookies as $name => $value) {
            $cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                'Domain'    => $value['domain'],
                'Name'      => $value['name'],
                'Value'     => $value['value'],
                'Path'      => $value['path'],
                'Expires'   => $value['expiry'],
                'Secure'    => $value['secure'],
                'HttpOnly'  => $value['httpOnly'],
            ]));
        }
        $this->defaultOptions['cookies'] = $cookieJar;
        return $this->initClient();
    }

    /**
     * StreamClient::allowRedirects()
     * 
     * @return
     */
    public function allowRedirects($allow = null){
        $this->defaultOptions['allow_redirects'] = (bool)$allow;
        return $this->initClient();
    }
    
    /**
     * StreamClient::setDebug()
     * 
     * @return
     */
    public function setDebug($debug = null){
        $this->defaultOptions['debug'] = (bool)$debug;
        return $this->initClient();
    }
    /**
     * StreamClient::bindTo()
     * 
     * @return
     */
    public function bindTo($ipToBinp =null){
        switch($this->transportMethod){
            case "stream":
                $this->defaultOptions['stream_context']['socket'] = ['bindto' => $ipToBinp.':0'];
                break;
            case "curl":
                $this->defaultOptions['curl'] = [CURLOPT_INTERFACE => $ipToBinp];
            default:
                break;
        }
        return $this->initClient();
    }

    /**
     * StreamClient::setBaseUri()
     * 
     * @return
     */
    public function setBaseUri($url = null){
        $this->defaultOptions['base_uri'] = $url;
        return $this->initClient();
    }
    
    /**
     * StreamClient::setTransport()
     * 
     * @return
     */
    public function setTransport($method=null){
        switch($method){
            case "stream":
                $this->defaultOptions= array_merge_recursive($this->defaultOptions,[
                    'stream'        => true,
                    'stream_context' => [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false
                        ]
                    ]
                ]);
                break;
            case "curl":
            default:
                $method = 'curl';
                break;
        }
        $this->transportMethod = $method;
        return $this->initClient(); 
    }
    
    /**
     * StreamClient::initClient()
     * 
     * @return
     */
    protected function initClient()
    {
        $this->httpClient = new GuzzleClient($this->defaultOptions);
        return $this;
    }

    /**
     * StreamClient::getClient()
     * 
     * @return
     */
    public function getClient()
    {
        if (!$this->httpClient)
            throw new \Exception('Invalid client');
        return $this->httpClient;
    }
   
    /*
    |--------------------------------------------------------------------------
    | Headers methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * StreamClient::setHeader()
     * 
     * @return
     */
    public function setHeader($name, $value='')
    {
        $this->defaultOptions['headers'][$name] = $value;
        return $this->initClient(); 
    }

    /**
     * StreamClient::removeHeader()
     * 
     * @return
     */
    public function removeHeader($name)
    {
        unset($this->defaultOptions['headers'][$name]);
        return $this->initClient(); 
    }

    /**
     * StreamClient::setUserAgent()
     * 
     * @return
     */
    public function setUserAgent($userAgent=''){
        $this->defaultOptions['headers']['User-Agent'] = $userAgent;
        return $this->initClient();
    }
    
    /*
    |--------------------------------------------------------------------------
    | Helpers methods
    |--------------------------------------------------------------------------
    */
    
    public function sadsadsa(){
        $link = $this->crawler->filterXPath('//form')->form();$link['p']='dasdsa';print_R($link->getMethod());
        $this->currentRequest = $this->getClient()->request($link->getMethod(),$link->getUri(),['query' => $link->getValues()]);
        die($this->getBody());
    }
    
    /**
     * StreamClient::createCrawlerFromContent()
     * 
     * @return
     */
    protected function createCrawlerFromContent($uri, $content, $type)
    {
        $crawler = new Crawler(null, $uri);
        $crawler->addContent($content, $type);
        return $crawler;
    }
    
    /**
     * CurlClient::array_merge_recursive_distinct()
     * 
     * @return
     */
    protected static function array_merge_recursive_distinct(array &$array1, array &$array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => &$value)
            $merged[$key] = (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) ? self::array_merge_recursive_distinct($merged[$key], $value) : $value;
        return $merged;
    }
}
