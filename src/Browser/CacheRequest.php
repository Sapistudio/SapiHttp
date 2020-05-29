<?php
namespace SapiStudio\Http\Browser;

class CacheRequest
{
    private static  $cacheValidity = 3600;
    private static $tokenName;
    private static $requestClient;
    private static $cacheClient;
    private static $instance;
    
    /** CacheRequest::setCacheValidity() */
    public static function setCacheValidity($validitySeconds = 1){
        self::$cacheValidity = $validitySeconds;
    }
    
    /** CacheRequest::init() */
    public static function init(StreamClient $client,$token = null)
    {
        static::$instance = new static($client,$token);
        return static::$instance;
    }
    
    /** CacheRequest::deleteToken() */
    public static function deleteToken($tokenName = null){
        return (self::$cacheClient) ? self::$cacheClient->deleteMultiple([$tokenName]) : false;
    }
    
    /** CacheRequest::__construct()*/
    private function __construct($client = null,$token = null)
    {
        self::$tokenName        = $token;
        self::$requestClient    = $client;
        self::$cacheClient      = new \Symfony\Component\Cache\Simple\FilesystemCache();
    }
    
    /** CacheRequest::__call()*/
    public function __call($name,$arguments)
    {
        $response = self::$cacheClient->get(self::$tokenName);
        if($response)
            return $response;
        $response = self::$requestClient->$name(...$arguments)->getBody();
        if($response)
            self::$cacheClient->set(self::$tokenName,$response,self::$cacheValidity);
        return $response;
    }
}
