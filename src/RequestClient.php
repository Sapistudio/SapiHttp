<?php
namespace SapiStudio\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;



/** this is for browser kit use*/
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar as GuzzleCookieJar;
use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * RequestClient
 * 
 * @package 
 * @author SapiStudio
 * @copyright 2017
 * @version $Id$
 * @access public
 */
class RequestClient
{
    protected $httpClient;
    private $headers        = [];
    private $defaultOptions = [];
    private $currentUrl     = null;
    
    /**
     * RequestClient::__call()
     * 
     * @param mixed $name
     * @param mixed $arguments
     * @return
     */
    public function __call($name, $arguments)
    {
        if (count($arguments) < 1) {
            return $this->getClient()->$name(...$arguments);
        }
        $this->currentUrl   = $arguments[0];
        $options = isset($arguments[1]) ? $arguments[1] : [];
        if($this->headers)
            $options = array_merge_recursive ($options,['headers'=>$this->headers]);
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
     * RequestClient::make()
     * 
     * @return
     */
    public static function make($options = []){
        return new static($options);
    }
    
    /**
     * RequestClient::__construct()
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
     * RequestClient::setClient()
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
     * RequestClient::getClient()
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
     * RequestClient::testUris()
     * 
     * @param mixed $content
     * @return
     */
    public static function testUris($content=null){
        if(!$content)
            return false;        
        $crawler    = getDomCrawler($content);
        $images     = $crawler->filterXpath('//img')->extract(['src','title','alt']);
        $links      = [];
        $links      = array_merge($links,$crawler->filterXpath('//a')->extract(['href','title','alt']));
        $links      = array_merge($links,$crawler->filterXpath('//area')->extract(['href','title','alt']));
        array_walk($links, function(&$val) use (&$extracted){$extracted[md5($val[0])] = array_combine(['href','title','alt'],$val);});
        array_walk($images, function(&$val) use (&$extracted){$extracted[md5($val[0])] = array_combine(['src','title','alt'],$val);});
        return array_merge_recursive((new static())->validateLinks(array_merge(array_column($extracted, 'href'),array_column($extracted, 'src'))),$extracted);
    }
    
    /**
     * RequestClient::isValidURL()
     * 
     * @param mixed $url
     * @return
     */
    public static function isValidURL($url){
        return preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url);
    }
    
    /**
     * RequestClient::urlIsImage()
     * 
     * @param mixed $contentType
     * @return
     */
    public function urlIsImage($contentType){
        switch ($contentType) {
            case 'image/png' :
            case 'image/jpg' :
            case 'image/jpeg':
            case 'image/gif' :
                $urlIsImage = true;
            break;
        default:
                $urlIsImage = false;
          break;
      }
      return $urlIsImage;
    }
    
   
    /**
     * RequestClient::validateLinks()
     * 
     * @param mixed $urlLinks
     * @return
     */
    public function validateLinks($urlLinks = []){
        if(!is_array($urlLinks))
            return false;
        foreach($urlLinks as $keyLink=>$Link){
            if(self::isValidURL($Link)){
                $promises[$keyLink] = $this->headAsync($Link,['headers' => ['User-Agent' => 'mailerTesting'],'curl' => [CURLOPT_FOLLOWLOCATION => false],'allow_redirects'=>false, 'debug' => false,'connect_timeout' => 3.14]);
            }
        }
        $results = Promise\settle($promises)->wait();
        if($results){
            foreach($results as $promiseIndex=>$promiseResponse){
                $response = $promiseResponse['value'];
                $linkHash = md5($urlLinks[$promiseIndex]);
                $this->validatedLinks[$linkHash]['isAlive']        = ($response) ? true : false;
                $this->validatedLinks[$linkHash]['isImage']        = ($response) ? self::urlIsImage($response->getHeaderLine('content-type')) : false;
                $this->validatedLinks[$linkHash]['contentType']    = ($response) ? $response->getHeaderLine('content-type') : false;
            }
        }
        return $this->validatedLinks;
    }
    
    
    
    
    
    /**
     * RequestClient::setHeader()
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
     * RequestClient::removeHeader()
     * 
     * @param mixed $name
     * @return
     */
    public function removeHeader($name)
    {
        unset($this->headers[strtolower($name)]);
    }
    
    /**
     * RequestClient::resetHeaders()
     * 
     * @return
     */
    public function resetHeaders()
    {
        $this->headers = [];
        return $this;
    }
    
    /**
     * RequestClient::setUserAgent()
     * 
     * @param string $user
     * @return
     */
    public function setUserAgent($user=''){
        return $this->setHeader('User-Agent',$user);
    }
}
