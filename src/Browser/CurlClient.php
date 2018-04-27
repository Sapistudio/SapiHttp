<?php
namespace SapiStudio\Http\Browser;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar as GuzzleCookieJar;

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
    private $defaultOptions = [];
    private $currentUrl     = null;
    
    /**
     * CurlClient::__call()
     * 
     * @param mixed $name
     * @param mixed $arguments
     * @return
     */
    public function __call($name,$arguments)
    {
        if (count($arguments) < 1)
            return $this->getClient()->$name(...$arguments);
        $this->currentUrl   = $arguments[0];
        $options = isset($arguments[1]) ? $arguments[1] : [];
        if($this->headers)
            $options = array_merge_recursive($options,['headers'=>$this->headers]);
        try {
            return $this->getClient()->$name($this->currentUrl,$options);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if (null === $response) {
                throw $e;
            }
        }
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
     * CurlClient::make()
     * 
     * @return
     */
    public static function make($options = []){
        return new static($options);
    }
    
    /**
     * CurlClient::__construct()
     * 
     * @param mixed $options
     * @return
     */
    private function __construct($options = []){
        $this->defaultOptions = ['allow_redirects' => true, 'cookies' => new GuzzleCookieJar()];
        $this->setClient(new GuzzleClient(array_merge($this->defaultOptions,$options)));
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
        if (!$this->httpClient) {
            throw new \Exception('Invalid client');
        }
        return $this->httpClient;
    }
    
    /**
     * CurlClient::testUris()
     * 
     * @param mixed $content
     * @return
     */
    public static function testUris($content=null){
        if(!$content)
            return false;
        if(is_array($content))
            return (new static())->validateLinks($content);
        $crawler    = \SapiStudio\Http\Html::loadHtml($content);
        $images     = $crawler->getDomImages();
        $links      = $crawler->getAllLinks();
        $toCheck    = array_merge($images,$links);
        return array_merge_recursive((new static())->validateLinks(array_merge(array_column($toCheck, 'href'),array_column($toCheck, 'src'))),$toCheck);
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
                $promises[$keyLink] = $this->headAsync($Link,['headers' => ['User-Agent' => 'SapiHttpTestLinks'],'curl' => [CURLOPT_FOLLOWLOCATION => false],'allow_redirects'=>false, 'debug' => false,'connect_timeout' => 3.14]);
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
