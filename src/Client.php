<?php

namespace SapiStudio\Http;
use Goutte\Client as BrowserClient;
use GuzzleHttp\Client as GuzzleHttpClient; 
use GuzzleHttp\Cookie\CookieJar as GuzzleCookieJar;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\Response;

/**
 * StreamClient
 * 
 * @package 
 * @author SapiStudio
 * @copyright 2017
 * @version $Id$
 * @access public
 */
class StreamClient extends BrowserClient
{
    public function __call($name, $arguments)
    {
        $client = $this->getClient();
        if (method_exists($client, $name))
            return $client->$name();
    }
    
    /**
     * StreamClient::__construct()
     * 
     * @param mixed $options
     * @return
     */
    public function __construct($options = []){
        parent::__construct();
        $this->setClient(new GuzzleHttpClient($options));
        return $this;
    }
    
    /**
     * StreamClient::setUserAgent()
     * 
     * @param string $user
     * @return
     */
    public function setUserAgent($user=''){
        return $this->setServerParameter('HTTP_USER_AGENT',$user);
    }
    
    /**
     * StreamClient::getCookies()
     * 
     * @return
     */
    public function getCookies(){
        return $this->getCookieJar()->all();
    }
    
    /**
     * StreamClient::setCookies()
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
     * StreamClient::hasCookie()
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
     * StreamClient::createResponse()
     * 
     * @param mixed $response
     * @return
     */
    protected function createResponse(ResponseInterface $response)
    {
        return new Response((string) $response->getBody()->getContents(), $response->getStatusCode(), $response->getHeaders());
    }
}
