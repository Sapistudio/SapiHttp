<?php
namespace SapiStudio\Http\Browser;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar as GuzzleCookieJar;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * CurlClient
 * 
 * @package 
 * @author SapiStudio
 * @copyright 2017
 * @version $Id$
 * @access public
 */
class CurlClient
{
    protected $httpClient;
    private $headers        = [];
    private $currentUrl     = null;
    private $clientOptions  = [];
    private $currentRequest = null;
    protected static $validateTestHeaders = ['headers' => ['User-Agent' => 'SapiHttpTestLinks'],'curl' => [CURLOPT_FOLLOWLOCATION => false],'allow_redirects'=>false, 'debug' => false,'connect_timeout' => 3.14];
    
    /**
     * CurlClient::make()
     * 
     * @return
     */
    public static function make($options = []){
        return new static($options);
    }
    
    /**
     * CurlClient::makeToTest()
     * 
     * @return
     */
    public static function makeToTest($options = []){
        return new static(array_merge_recursive(self::$validateTestHeaders,$options));
    }
    
    /**
     * CurlClient::getDefaultClient()
     * 
     * @return
     */
    public static function getDefaultClient($options = []){
        return new GuzzleClient($options);
    }
    
    /**
     * CurlClient::__call()
     * 
     * @param mixed $name
     * @param mixed $arguments
     * @return
     */
    public function __call($name,$arguments)
    {
        $this->currentRequest = null;
        /** just url passed,no options*/
        if (count($arguments) < 1){
            $this->currentRequest = $this->getClient()->$name(...$arguments);
            return $this->currentRequest;
        }
        $this->currentUrl   = $arguments[0];
        $options = isset($arguments[1]) ? $arguments[1] : [];
        $options = array_merge_recursive($this->clientOptions,$options);
        if($this->headers)
            $options = array_merge_recursive($options,['headers'=>$this->headers]);
        $this->resetHeaders();
        try {
            if(isset($options['cookies']) && !$options['cookies'] instanceof GuzzleCookieJar)
                unset($options['cookies']);
            $this->currentRequest = $this->getClient()->$name($this->currentUrl,$options);
        } catch(RequestException $e){
            $this->currentRequest = $e->getResponse();
            if (null === $this->currentRequest) {
                throw $e;
            }
        }
        return $this->currentRequest;
    }
    
    /**
     * CurlClient::getResponse()
     * 
     * @param mixed $url
     * @param mixed $arguments
     * @return
     */
    public function getResponse($url,$arguments=null){
        return $this->get($url,$arguments)->getBody()->getContents();
    }
    
    /**
     * CurlClient::getRequestStatusCode()
     * 
     * @return
     */
    public function getRequestStatusCode(){
        return $this->currentRequest->getStatusCode();
    }
    
    /**
     * CurlClient::getRequestHeader()
     * 
     * @param mixed $headerName
     * @return
     */
    public function getRequestHeader($headerName = null){
        return $this->currentRequest->getHeaderLine($headerName);
    }
    
    /**
     * CurlClient::cacheRequest()
     * 
     * @param mixed $token
     * @return
     */
    public function cacheRequest($token=null){
        return CacheRequest::init($this,$token);
    }
    
    /**
     * CurlClient::toCache()
     * 
     * @param mixed $response
     * @return
     */
    public function toCache($response = null){
        return ($response) ? $response->getBody()->getContents() : false;
    }
        
    /**
     * CurlClient::__construct()
     * 
     * @param mixed $options
     * @return
     */
    private function __construct($options = []){
        $this->clientOptions = $options;
        $this->allowRedirects();
        $this->setCookies();
        $this->setClient(new GuzzleClient());
        return $this;
    }
    
    /**
     * CurlClient::setCookies()
     * 
     * @return
     */
    public function setCookies(){
        $this->clientOptions['cookies'] = new GuzzleCookieJar();
        return $this;
    }
    
    /**
     * CurlClient::allowRedirects()
     * 
     * @return
     */
    public function allowRedirects(){
        $this->clientOptions['allow_redirects'] = true;
        return $this;
    }
    
    /**
     * CurlClient::setDebug()
     * 
     * @return
     */
    public function setDebug(){
        $this->clientOptions['debug'] = true;
        return $this;
    }
    
    /**
     * CurlClient::setClient()
     * 
     * @param mixed $client
     * @return
     */
    public function setClient(GuzzleClientInterface $client)
    {
        $this->httpClient = $client;
        return $this;
    }
    
    /**
     * CurlClient::getClient()
     * 
     * @return
     */
    public function getClient()
    {
        if (!$this->httpClient)
            throw new \Exception('Invalid client');
        return $this->httpClient;
    }
   
    /**
     * CurlClient::validateLinks()
     * 
     * @param mixed $urlLinks
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
    
    /**
     * CurlClient::setBaseUri()
     * 
     * @param mixed $url
     * @return
     */
    public function setBaseUri($url = null){
        $this->clientOptions['base_uri'] = $url;
        return $this;
    }
    
    /**
     * CurlClient::setRequestCookiesFromArray()
     * 
     * @param mixed $cookies
     * @param mixed $domain
     * @return
     */
    public function setRequestCookiesFromArray($cookies = [],$domain = null){
        $this->clientOptions['cookies'] = \GuzzleHttp\Cookie\CookieJar::fromArray($cookies,$domain);
        return $this;
    }
    
    /**
     * CurlClient::setHeader()
     * 
     * @param mixed $name
     * @param mixed $value
     * @return
     */
    public function setHeader($name, $value='')
    {
        if(is_array($name))
            array_merge($this->headers,$name);
        else
            $this->headers[strtolower($name)] = $value;
        return $this;
    }
    
    /**
     * CurlClient::removeHeader()
     * 
     * @param mixed $name
     * @return
     */
    public function removeHeader($name)
    {
        unset($this->headers[strtolower($name)]);
    }
    
    /**
     * CurlClient::resetHeaders()
     * 
     * @return
     */
    public function resetHeaders()
    {
        $this->headers = [];
        return $this;
    }
    
    /**
     * CurlClient::setUserAgent()
     * 
     * @param string $user
     * @return
     */
    public function setUserAgent($user=''){
        return $this->setHeader('User-Agent',$user);
    }
}
