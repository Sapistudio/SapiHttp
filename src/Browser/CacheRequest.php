<?php
namespace SapiStudio\Http\Browser;

class CacheRequest
{
    private static $cacheValidity   = 3600;
    private static $cacheNameSpace  = null;
    private static $cacheDirectory  = null;
    public static $cacheStatusCodes = [];
    private static $tokenName;
    private static $requestClient;
    private static $cacheClient;
    private static $cacheInstance;
    
    /** CacheRequest::setCacheValidity() */
    public static function setCacheValidity($validitySeconds = 1){
        self::$cacheValidity = $validitySeconds;
        self::getCacheClient();
    }
    
    /** CacheRequest::setCacheDirectory() */
    public static function setCacheDirectory($dirName = null){
        self::$cacheDirectory = $dirName;
        self::getCacheClient();
    }
        
    /** CacheRequest::init() */
    public static function init(StreamClient $client,$token = null)
    {
        static::$cacheInstance = new static($client,$token);
        return static::$cacheInstance;
    }
    
    /** CacheRequest::deleteTokens() */
    public static function deleteTokens($tokens = null){
        $tokens = (is_string($tokens)) ? [$tokens] : $tokens;
        return (is_array($tokens)) ? self::getCacheClient()->deleteMultiple(array_values($tokens)) : false;
    }
    
    /** CacheRequest::__construct()*/
    private function __construct($client = null,$token = null)
    {
        /** only cache response with 2.x.x or 3.x.x status codes*/
        self::$cacheStatusCodes = range(200,399);
        self::$tokenName        = $token;
        self::$requestClient    = $client;
    }
    
    /** CacheRequest::__call()*/
    public function __call($name,$arguments)
    {
        if (filter_var(self::$tokenName, FILTER_VALIDATE_URL)) {
            die(self::$tokenName);
        }
        $makeNewRequest = true;
        $cachedResponse = self::getCacheClient()->get(self::$tokenName);
        if($cachedResponse){
            $makeNewRequest = false;
            $cachedResponse = json_decode($cachedResponse,true);
            if(is_array($cachedResponse) && !array_diff(array_keys($cachedResponse), ['statusCode','headers','body']))
                self::$requestClient->setCacheResponse(new \GuzzleHttp\Psr7\Response($cachedResponse['statusCode'],$cachedResponse['headers'],$cachedResponse['body']));
            else
                $makeNewRequest = true;
        }
        if($makeNewRequest){
            $response = self::$requestClient->$name(...$arguments)->getBody();
            if($response && in_array(self::$requestClient->getStatusCode(),self::$cacheStatusCodes)){
                self::getCacheClient()->set(self::$tokenName,json_encode(['statusCode' => self::$requestClient->getStatusCode(),'body' => self::$requestClient->getBody(),'headers' => self::$requestClient->getRequestHeader()]));
            }
        }
        return self::$requestClient;
    }
    
    /** CacheRequest::getCacheClient()*/
    private static function getCacheClient($forceInit = false){
        if(!self::$cacheClient || $forceInit)
            self::$cacheClient = new \Symfony\Component\Cache\Simple\FilesystemCache(self::$cacheNameSpace,self::$cacheValidity,self::$cacheDirectory);
        return self::$cacheClient;
    }
}
