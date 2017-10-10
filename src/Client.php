<?php

namespace SapiStudio\SapiBrowser;
use Goutte\Client as BrowserClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\Response;

/**
 * Client
 * 
 * @package 
 * @author SapiStudio
 * @copyright 2017
 * @version $Id$
 * @access public
 */
class Client extends BrowserClient
{
    public function __call($name, $arguments)
    {
        print_R($arguments);
        $client = $this->->getClient();
        if (method_exists($client, $name))
            return $client->$name();
    }
    
    /**
     * Client::setUserAgent()
     * 
     * @param string $user
     * @return
     */
    public function setUserAgent($user=''){
        return $this->setServerParameter('HTTP_USER_AGENT',$user);
    }
    
    /**
     * Client::getCookies()
     * 
     * @return
     */
    public function getCookies(){
        return $this->getCookieJar()->all();
    }
    
    /**
     * Client::setCookies()
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
     * Client::hasCookie()
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
     * Client::createResponse()
     * 
     * @param mixed $response
     * @return
     */
    protected function createResponse(ResponseInterface $response)
    {
        return new Response((string) $response->getBody()->getContents(), $response->getStatusCode(), $response->getHeaders());
    }
}
