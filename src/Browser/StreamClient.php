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
    const ASYNC_CONNECTIONS     = 10;
    const ASYNC_URILINK_MAP     = 'uriLink';
    const ASYNC_URIPATH_MAP     = 'savePath';
    const ASYNC_URIFILE_MAP     = 'filename';
    const ASYNC_SUCCESSFUL      = 'downloadSuccesful';
    const ASYNC_ERROR_DOWNLOAD  = 'downloadError';
    const ASYNC_RETRIEVED       = 'dateRetrieved';
    const ASYNC_TIMESTAMP       = 'timestampGetter';
    const ASYNC_PROGRESS_BYTES  = 'totalDownloaded';
    const ASYNC_TOTAL_SIZE      = 'toDownload';
    const ASYNC_LOCK_TO_START   = 'lockUntilStart';
    const PROGRESS_TO_JSON      = 'outputPgBtoJson';
    const PROGRESS_BAR_FORMAT   = "\n%message%\n%current% [%bar%] %percent:3s%%\n%elapsed:-21s% %memory:21s%\n";
    
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
    protected $historyStats = [];
    protected $crawler      = null;
    private $promises       = [];
    private $pgBarPromises  = null;
    private $streamConsoleOutput = null;
    
    /**
    |--------------------------------------------------------------------------
    | Init methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::getPageContent() */
    public static function getPageContent($url){
        return (new static())->getPageResponse($url);
    }
    
    /** StreamClient::make() */
    public static function make($options = []){
        return new static($options);
    }
    
    /** StreamClient::makeGuzzle() */
    public static function makeGuzzle($options = []){
        return (new static($options))->getClient();
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
        if(!$this->defaultOptions['headers']['User-Agent'])
            $this->defaultOptions['headers']['User-Agent'] = self::$clientConfig['defaultUserA'];
        return $this->setTransport(self::$clientConfig['method']);
    }
    
    /** StreamClient::makeRequestStat() */
    protected function makeRequestStat($stats){
        $statistics             = $stats->getHandlerStats();
        $this->currentUrl       = $stats->getEffectiveUri()->__toString();
        $this->historyUrl[]     = $stats->getEffectiveUri();
        $this->historyStats[]   = $stats->getRequest()->getMethod().'   -   '.$statistics['url'].'   -   '.$statistics['http_code'].'   -   '.$statistics['total_time'];
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
                //$linkHash = md5($urlLinks[$promiseIndex]);
                $this->validatedLinks[$promiseIndex]['isAlive']     = ($response) ? true : false;
                $this->validatedLinks[$promiseIndex]['url']         = $urlLinks[$promiseIndex];
                $this->validatedLinks[$promiseIndex]['isImage']     = ($response) ? \SapiStudio\Http\Html::urlIsImage($response->getHeaderLine('content-type')) : false;
                $this->validatedLinks[$promiseIndex]['contentType'] = ($response) ? $response->getHeaderLine('content-type') : false;
            }
        }
        return $this->validatedLinks;
    }
    
    /** StreamClient::downloadFilesAsync() */
    public function downloadFilesAsync($uriFiles = []){
        if(!$uriFiles)
            return false;
        $promiseRequests    = [];
        $this->promises     = $uriFiles;
        foreach ($this->promises as $uriIndex => $uriData) {
            /** this is a redue pass var*/
            if(isset($uriData[self::ASYNC_SUCCESSFUL]) && $uriData[self::ASYNC_SUCCESSFUL])
                continue;
            if (filter_var($uriData[self::ASYNC_URILINK_MAP], FILTER_VALIDATE_URL) === FALSE) {
                $this->promises[$uriIndex][self::ASYNC_ERROR_DOWNLOAD]  = $uriData[self::ASYNC_URILINK_MAP].' not a valid link';
                $this->promises[$uriIndex][self::ASYNC_SUCCESSFUL]      = false;
                continue;
            }
            if(!is_dir($uriData[self::ASYNC_URIPATH_MAP])){
                $this->promises[$uriIndex][self::ASYNC_ERROR_DOWNLOAD]  = 'Folder path '.$uriData[self::ASYNC_URIPATH_MAP].' doesnt exists';
                $this->promises[$uriIndex][self::ASYNC_SUCCESSFUL]      = false;
                continue;
            }
            $this->getFilenameFromDisposition($uriIndex);
            $this->promises[$uriIndex][self::ASYNC_LOCK_TO_START] = true;
            $promiseRequests[$uriIndex] = $this->getClient()->getAsync($uriData[self::ASYNC_URILINK_MAP],
                [
                    'save_to'           => $this->promises[$uriIndex][self::ASYNC_URIPATH_MAP].$this->promises[$uriIndex][self::ASYNC_URIFILE_MAP],
                    'allow_redirects'   => ['track_redirects' => true],
                    'progress'          => function($downloadTotal,$downloadedBytes,$uploadTotal,$uploadedBytes) use ($uriIndex) {
                        $this->promises[$uriIndex][self::ASYNC_TOTAL_SIZE]      = $downloadTotal;
                        $this->promises[$uriIndex][self::ASYNC_PROGRESS_BYTES]  = $downloadedBytes;
                        if(isset($this->promises[$uriIndex][self::ASYNC_LOCK_TO_START]) && $downloadTotal > 0)
                            unset($this->promises[$uriIndex][self::ASYNC_LOCK_TO_START]);
                        if($this->promises[$uriIndex][self::PROGRESS_TO_JSON]) 
                            echo json_encode([$downloadTotal,$downloadedBytes]);
                        else
                            $this->checkProgressBar($uriIndex);
                    },
                    'on_headers'        => function (\GuzzleHttp\Psr7\Response $response)  use ($uriIndex){
                        if(!$this->promises[$uriIndex][self::ASYNC_URIFILE_MAP])
                            throw new \Exception('Invalid filename save');
                    }
                ]
            );
        }
        $results = Promise\settle($promiseRequests)->wait();
        if($results){
            foreach($results as $promiseIndex=>$promiseResponse){
                $this->promises[$promiseIndex][self::ASYNC_SUCCESSFUL]  = ($promiseResponse['value']) ? true : false;
                $this->promises[$promiseIndex][self::ASYNC_RETRIEVED]   = date('Y-m-d H:i:s');
                $this->promises[$promiseIndex][self::ASYNC_TIMESTAMP]   = strtotime("now");
                if($promiseResponse['reason'])
                    $this->promises[$promiseIndex][self::ASYNC_ERROR_DOWNLOAD] = $promiseResponse['reason']->getMessage();
            }
        }
        return $this->promises;
    }
    
    /** Supression::getFilenameFromDisposition()*/
    protected function getFilenameFromDisposition($promisIndex = null){
        if(!$this->promises[$promisIndex])
            return false;
        $getHead            = $this->head($this->promises[$promisIndex][self::ASYNC_URILINK_MAP],['allow_redirects'=>['strict' => true]]);
        $dispositionValue   = $getHead->getRequestHeader('Content-Disposition');
        $contentType        = $getHead->getRequestHeader('Content-Type');
        $filename = null;
        if($dispositionValue){
            if(preg_match('/.*filename=[\'\"]([^\'\"]+)/', $dispositionValue, $matches))
                $filename = $matches[1];
            elseif(preg_match("/.*filename=([^ ]+)/", $dispositionValue, $matches))
                $filename = $matches[1];
            $filename = preg_replace("/[^a-zA-Z0-9_#\(\)\[\]\.+-=]/", "",$filename);
        }else{
            $mimetype = self::mime2ext(trim(\GuzzleHttp\Psr7\parse_header($contentType)[0][0]));
            if($mimetype != 'html')
                $filename = basename($this->promises[$promisIndex][self::ASYNC_URILINK_MAP]).'.'.$mimetype;
        }
        if($filename)
            $this->promises[$promisIndex][self::ASYNC_URIFILE_MAP] = $filename;
        return $this;
    }
    
    /**
    |--------------------------------------------------------------------------
    | cache methods
    |--------------------------------------------------------------------------
    */
    
    /** StreamClient::setCacheParams() */
    public function setCacheParams($params = []){
        if($params['cacheDir'])
            CacheRequest::setCacheDirectory($params['cacheDir']);
        if($params['cacheVal'])
            CacheRequest::setCacheValidity($params['validity']);
        return $this;
    }
    
    /** StreamClient::setCacheResponse() */
    public function setCacheResponse(\GuzzleHttp\Psr7\Response $response){
        $this->currentRequest = $response;
    }
        
    /** StreamClient::cacheRequest() */
    public function cacheRequest($token = null){
        return CacheRequest::init($this,$token);
    }
    
    /** StreamClient::cacheDelete() */
    public function cacheDelete($tokens = null){
        return CacheRequest::deleteTokens($tokens);
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

    /** StreamClient::getPageResponse()*/
    public function getPageResponse($url,$arguments=null){
        $this->get($url,$arguments);
        return $this->getBody();
    }
    
    /** StreamClient::getCurrentUri()*/
    public function getCurrentUri(){
        return $this->currentUrl;
    }
    
    /** StreamClient::gethistoryStats()*/
    public function gethistoryStats(){
        return $this->historyStats;
    }
    
    /** StreamClient::gethistoryUrls()*/
    public function gethistoryUrls(){
        return $this->historyUrl;
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
    
    /** StreamClient::setDefaultOption()  */
    public function setDefaultOption($name, $value='')
    {
        $this->defaultOptions[$name] = $value;
        return $this->initClient(); 
    }
    
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
    
    /** StreamClient::useConsoleOutput() */
    public function useConsoleOutput(\Symfony\Component\Console\Style\SymfonyStyle $interfaceConsoleOutput){
        $this->streamConsoleOutput = $interfaceConsoleOutput;
        return $this;
    }
    
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
    
    /**  StreamClient::checkProgressBar() */
    protected function checkProgressBar($promiseIndex = 0){
        if(!isset($this->promises[$promiseIndex]) || is_null($this->streamConsoleOutput))
            return false;
        $promisesLocked = array_column($this->promises,self::ASYNC_LOCK_TO_START);
        if(!empty($promisesLocked))
            return false;
        $total      = array_sum(array_column($this->promises,self::ASYNC_TOTAL_SIZE));
        $downloaded = array_sum(array_column($this->promises,self::ASYNC_PROGRESS_BYTES));
        if (!is_null($this->streamConsoleOutput)){
            if(!$this->pgBarPromises){
                $this->pgBarPromises = $this->streamConsoleOutput->createProgressBar($total);
                $this->pgBarPromises->setFormat(self::PROGRESS_BAR_FORMAT);
                $this->pgBarPromises->setBarCharacter("<fg=green>=</>");
                $this->pgBarPromises->setEmptyBarCharacter("<fg=red>=</>");
                $this->pgBarPromises->setProgressCharacter("\xF0\x9F\x9A\x80");
                $this->pgBarPromises->setMessage('Starting download(s).Total files:'.count($this->promises).'. Total size:'.self::formatBytes($total));
                $this->pgBarPromises->start();
            }
            if ($total === $downloaded) {
                $this->pgBarPromises->finish();
                    return;
            }
            $this->pgBarPromises->setProgress($downloaded);
        }
        return $this;
    }
    
    /**  StreamClient::mime2ext() */
    protected static function mime2ext($mime){
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];
        return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
    }
    
    /**  StreamClient::formatBytes() */
    protected static function formatBytes($bytes, $precision = 2) {
        $unit = ["B", "KB", "MB", "GB"];
        $exp = floor(log($bytes, 1024)) | 0;
        return round($bytes / (pow(1024, $exp)), $precision).$unit[$exp];
    }
}
