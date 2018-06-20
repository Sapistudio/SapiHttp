<?php
namespace SapiStudio\Http\Browser;

class CacheRequest
{
    const CACHE_VALIDITY = 3600;
    private static $tokenName;
    private static $requestClient;
    private static $cacheClient;
    private static $instance;
    
    /**
     * CacheRequest::init()
     * 
     * @return
     */
    public static function init($client = null,$token = null)
    {
        static::$instance = new static($client,$token);
        return static::$instance;
    }
    
    /**
     * CacheRequest::__construct()
     * 
     * @return
     */
    private function __construct($client = null,$token = null)
    {
        self::$tokenName        = $token;
        self::$requestClient    = $client;
        self::$cacheClient      = new \Symfony\Component\Cache\Simple\FilesystemCache();
    }
    
    /**
     * CacheRequest::__call()
     * 
     * @param mixed $name
     * @param mixed $arguments
     * @return
     */
    public function __call($name,$arguments)
    {
        $response = self::$cacheClient->get(self::$tokenName);
        if($response)
            return $response;
        $response = self::$requestClient->$name(...$arguments);
        if(method_exists(self::$requestClient,'toCache'))
            $response = self::$requestClient->toCache($response);
        self::$cacheClient->set(self::$tokenName,$response,SELF::CACHE_VALIDITY);
        return $response;
    }
}
