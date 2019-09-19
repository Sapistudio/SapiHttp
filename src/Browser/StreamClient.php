<?php
namespace SapiStudio\Http\Browser;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar as GuzzleCookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;

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
    protected $currentUrl   = null;
    private $defaultOptions = [];
    private $currentRequest = null;
    protected $historyUrl   = [];
    protected $crawler      = null;
    
    /**
    |--------------------------------------------------------------------------
    | Init methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::make() */
    public static function make($options = []){
        return new static($options);
    }

    /** StreamClient::makeToTest() */
    public static function makeToTest($options = []){
        return new static(self::array_merge_recursive_distinct(self::$clientConfig['testConfig'],$options));
    }
    
    /** StreamClient::__construct() */
    protected function __construct($options = []){
        $this->defaultOptions               = self::array_merge_recursive_distinct(self::$clientConfig['defaultConfig'],$options);
        $this->defaultOptions['cookies']    = new GuzzleCookieJar();
        $this->defaultOptions['on_stats']   = function(\GuzzleHttp\TransferStats $stats){$this->makeRequestStat($stats);};
        $this->defaultOptions['headers']['User-Agent'] = self::$clientConfig['defaultUserA'];
        return $this->setTransport(self::$clientConfig['method']);
    }
    
    /** StreamClient::makeRequestStat() */
    protected function makeRequestStat($stats){
        $statistics = $stats->getHandlerStats();
        $this->currentUrl = $stats->getEffectiveUri()->__toString();
        $this->historyUrl[] = $stats->getEffectiveUri();
        //echo $stats->getRequest()->getMethod().'   -   '.$statistics['url'].'   -   '.$statistics['http_code'].'   -   '.$statistics['total_time']."\n";
    }
    
    /** StreamClient::__call() */
    public function __call($name,$arguments)
    {
        if((method_exists($this->getClient(),$name) && !in_array($name,['requestAsync','request','send'])) || in_array($name,['headAsync']))
            return $this->getClient()->$name(...$arguments);
        $this->currentRequest   = null;
        try {
            $this->currentRequest = $this->getClient()->$name(...$arguments); 
        }catch(RequestException $e){
            $this->currentRequest = $e->getResponse();
            if (null === $this->currentRequest) {
                throw $e;
            }
        }
        if ($this->currentRequest instanceof \GuzzleHttp\Psr7\Response) {
            $this->crawler          = $this->createCrawlerFromContent($this->currentUrl, $this->getBody(), $this->getRequestHeader('Content-Type'));
            if(method_exists($this,'initOnRequest'))
                $this->initOnRequest();
        }
        return $this;
    }
    
    /** StreamClient::cacheRequest() */
    public function cacheRequest($token=null){
        return CacheRequest::init($this,$token);
    }
    
    /** StreamClient::validateLinks() */
    public function validateLinks($urlLinks = []){
        if(!is_array($urlLinks))
            return false;
        foreach($urlLinks as $keyLink=>$Link){
            if(\SapiStudio\Http\Html::isValidURL($Link)){
                $promises[$keyLink] = $this->headAsync($Link,self::$clientConfig['testConfig']);
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
    
    /**
    |--------------------------------------------------------------------------
    | Crawler methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::click()*/
    public function click(Link $link)
    {
        if ($link instanceof Form) {
            return $this->submit($link);
        }
        return $this->request($link->getMethod(), $link->getUri());
    }
    
    /** StreamClient::click()*/
    public function submit(Form $form, array $values = [])
    {
        $form->setValues($values);
        return $this->request($form->getMethod(), $form->getUri(), ['form_params' => $form->getValues()]);
    }
    
    
    /**
    |--------------------------------------------------------------------------
    | Response methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::getConfig() */
    public function getConfig(){
        return $this->getClient()->getConfig();
    }
    
    /** StreamClient::getCookies() */
    public function getCookies(){
        return $this->getClient()->getConfig('cookies');
    }

    /** StreamClient::getResponse()*/
    public function getResponse($url,$arguments=null){
        $this->get($url,$arguments);
        return $this->getBody();
    }
    
    public function getCurrentUri(){
        return $this->currentUrl;
    }
    
    /** StreamClient::getStatusCode()*/
    public function getStatusCode(){
        return $this->currentRequest->getStatusCode();
    }

    /** StreamClient::getBody() */
    public function getBody(){     
        $this->currentRequest->getBody()->rewind();
        return $this->currentRequest->getBody()->getContents();
    }
    
    /** StreamClient::getRequestHeader() */
    public function getRequestHeader($headerName = null){
        return (is_null($headerName)) ? $this->currentRequest->getHeaders() : $this->currentRequest->getHeaderLine($headerName);
    }
    
    /**
    |--------------------------------------------------------------------------
    | Configs methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::setCookiesFromArray()*/
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
    
    /** StreamClient::setRequestCookiesFromArray()*/
    public function setRequestCookiesFromArray($cookies = [],$domain = null){
        $this->defaultOptions['cookies'] = \GuzzleHttp\Cookie\CookieJar::fromArray($cookies,$domain);
        return $this->initClient();
    }
                
    /** StreamClient::allowRedirects()*/
    public function allowRedirects($allow = null){
        $this->defaultOptions['allow_redirects'] = (bool)$allow;
        return $this->initClient();
    }
    
    /** StreamClient::setDebug() */
    public function setDebug($debug = null){
        $this->defaultOptions['debug'] = (bool)$debug;
        return $this->initClient();
    }
    
    /** StreamClient::bindTo() */
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

    /** StreamClient::setBaseUri() */
    public function setBaseUri($url = null){
        $this->defaultOptions['base_uri'] = $url;
        return $this->initClient();
    }
    
    /** StreamClient::setTransport() */
    public function setTransport($method=null){
        switch($method){
            case "stream":
                $this->defaultOptions= array_merge_recursive($this->defaultOptions,[
                    'stream'        => true,
                    'stream_context' => [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
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
    
    /** StreamClient::initClient() */
    protected function initClient()
    {
        $this->httpClient = new GuzzleClient($this->defaultOptions);
        return $this;
    }

    /** StreamClient::getClient()  */
    public function getClient()
    {
        if (!$this->httpClient)
            throw new \Exception('Invalid client');
        return $this->httpClient;
    }
   
    /**
    |--------------------------------------------------------------------------
    | Headers methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::setHeader()  */
    public function setHeader($name, $value='')
    {
        $this->defaultOptions['headers'][$name] = $value;
        return $this->initClient(); 
    }

    /** StreamClient::removeHeader() */
    public function removeHeader($name)
    {
        unset($this->defaultOptions['headers'][$name]);
        return $this->initClient(); 
    }

    /** StreamClient::setUserAgent() */
    public function setUserAgent($userAgent=''){
        if(trim($userAgent)!='')
            $this->defaultOptions['headers']['User-Agent'] = $userAgent;
        return $this->initClient();
    }
    
    /**
    |--------------------------------------------------------------------------
    | Helpers methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::createCrawlerFromContent() */
    protected function createCrawlerFromContent($uri, $content, $type)
    {
        $crawler = new Crawler(null, $uri);
        $crawler->addContent($content, $type);
        return $crawler;
    }
    
    /**  StreamClient::array_merge_recursive_distinct() */
    protected static function array_merge_recursive_distinct(array &$array1, array &$array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => &$value)
            $merged[$key] = (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) ? self::array_merge_recursive_distinct($merged[$key], $value) : $value;
        return $merged;
    }
}
