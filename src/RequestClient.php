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
    private $headers = [];
    
    /**
     * RequestClient::__call()
     * 
     * @param mixed $name
     * @param mixed $arguments
     * @return
     */
    public function __call($name, $arguments)
    {
        return $this->getClient()->$name(...$arguments);
    }
    
    /**
     * RequestClient::__construct()
     * 
     * @param mixed $options
     * @return
     */
    private function __construct($options = []){
        $this->setClient(new GuzzleClient($options));
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
            $this->httpClient = new GuzzleClient(['allow_redirects' => true, 'cookies' => true]);
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
        $crawler    = getDom($content);
        $images     = $crawler->filterXpath('//img')->extract(['src','title','alt']);
        $links      = $crawler->filterXpath('//a')->extract(['href','title','alt']);
        array_walk($links, function(&$val) use (&$extracted){$extracted[md5($val[0])] = array_combine(['href','title','alt'],$val);});
        array_walk($images, function(&$val) use (&$extracted){$extracted[md5($val[0])] = array_combine(['src','title','alt'],$val);});
        return array_merge_recursive((new static())->validateLinks(array_merge(array_column($extracted, 'href'),array_column($extracted, 'src'))),$extracted);
    }
    
    public static function make(){
        return new static();
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
                $promises[$keyLink] = $this->headAsync($Link);
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
    
    
    /** this is for browser kit*/
    /**
     * RequestClient::setHeader()
     * 
     * @param mixed $name
     * @param mixed $value
     * @return
     */
    public function setHeader($name, $value)
    {
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
     * RequestClient::restart()
     * 
     * @return
     */
    public function restart()
    {
        parent::restart();
        $this->resetHeaders();
    }
   
    /**
     * RequestClient::setUserAgent()
     * 
     * @param string $user
     * @return
     */
    public function setUserAgent($user=''){
        return $this->setServerParameter('HTTP_USER_AGENT',$user);
    }
    
    /**
     * RequestClient::getCookies()
     * 
     * @return
     */
    public function getCookies(){
        return $this->getCookieJar()->all();
    }
    
    /**
     * RequestClient::setCookies()
     * 
     * @param mixed $cookies
     * @return
     */
    public function setCookies($cookies){
        if($cookies){
            foreach($cookies as $a=>$cookie){
                $this->getCookieJar()->set($cookie);
            }
        }
        return $this;
    }
    
    /**
     * RequestClient::hasCookie()
     * 
     * @param mixed $cookieName
     * @return
     */
    public function hasCookie($cookieName=null){
        $cookies = $this->getCookieJar()->all();
        if($cookies){
            foreach ($cookies as $a=>$cookie)
                if ($cookie->getName() == $cookieName){
                    return true;
            }
        }
        return false;
    }
    
    /**
     * RequestClient::doRequest()
     * 
     * @param mixed $request
     * @return
     */
    protected function doRequest($request)
    {
        $headers = [];
        foreach ($request->getServer() as $key => $val) {
            $key = strtolower(str_replace('_', '-', $key));
            $contentHeaders = ['content-length' => true, 'content-md5' => true, 'content-type' => true];
            if (0 === strpos($key, 'http-')) {
                $headers[substr($key, 5)] = $val;
            }elseif (isset($contentHeaders[$key])) {
                $headers[$key] = $val;
            }
        }
        $cookies = GuzzleCookieJar::fromArray($this->getCookieJar()->allRawValues($request->getUri()),parse_url($request->getUri(), PHP_URL_HOST));
        $requestOptions = ['cookies' => $cookies,'allow_redirects' => true];
        if (!in_array($request->getMethod(), ['GET', 'HEAD'])) {
            if (null !== $content = $request->getContent()) {
                $requestOptions['body'] = $content;
            }else{
                if ($files = $request->getFiles()) {
                    $requestOptions['multipart'] = [];
                } else {
                    $requestOptions['form_params'] = $request->getParameters();
                }
            }
        }
        if (!empty($headers)) {
            $requestOptions['headers'] = $headers;
        }
        $method = $request->getMethod();
        $uri = $request->getUri();
        foreach ($this->headers as $name => $value) {
            $requestOptions['headers'][$name] = $value;
        }
      
        try {
            $response = $this->getClient()->request($method, $uri, $requestOptions);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if (null === $response) {
                throw $e;
            }
        }
        return $this->createResponse($response);
    }
    
    /**
     * RequestClient::createResponse()
     * 
     * @param mixed $response
     * @return
     */
    protected function createResponse(ResponseInterface $response)
    {
        return new Response((string)$response->getBody()->getContents(), $response->getStatusCode(), $response->getHeaders());
    }
}
