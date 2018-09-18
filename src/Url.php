
<?php
namespace SapiStudio\Http;

/** fork after wa72/url*/

class Url
{
    const PATH_SEGMENT_SEPARATOR    = '/';
    const WRITE_FLAG_AS_IS          = 0;
    const WRITE_FLAG_OMIT_SCHEME    = 1;
    const WRITE_FLAG_OMIT_HOST      = 2;

    protected $original_url;
    protected $scheme;
    protected $user;
    protected $pass;
    protected $host;
    protected $port;
    protected $path;
    protected $query;
    protected $fragment;

    protected $query_array = [];

    /**
     * Url::__construct()
     * 
     * @return
     */
    public function __construct($url)
    {
        $this->original_url = trim($url);
        if ($this->is_protocol_relative()) {
            $url = 'http:'.$url;
        }
        $urlo = parse_url($url);
        if (isset($urlo['scheme']) && !$this->is_protocol_relative()) {
            $this->scheme = strtolower($urlo['scheme']);
        }
        if (isset($urlo['user'])) $this->user = $urlo['user'];
        if (isset($urlo['pass'])) $this->pass = $urlo['pass'];
        if (isset($urlo['host'])) $this->host = strtolower($urlo['host']);
        if (isset($urlo['port'])) $this->port = intval($urlo['port']);
        if (isset($urlo['path'])) $this->path = static::normalizePath($urlo['path']);
        if (isset($urlo['query'])) $this->query = $urlo['query'];
        if ($this->query != '') parse_str($this->query, $this->query_array);
        if (isset($urlo['fragment'])) $this->fragment = $urlo['fragment'];
    }

    /**
     * Url::is_url()
     * 
     * @return
     */
    public function is_url()
    {
        return ($this->scheme == '' || $this->scheme == 'http' || $this->scheme == 'https' || $this->scheme == 'ftp' || $this->scheme == 'ftps' || $this->scheme == 'file');
    }

    /**
     * Url::is_local()
     * 
     * @return
     */
    public function is_local()
    {
        return (substr($this->original_url, 0, 1) == '#');
    }

    /**
     * Url::is_relative()
     * 
     * @return
     */
    public function is_relative()
    {
        return ($this->scheme == '' && $this->host == '' && substr($this->path, 0, 1) != '/');
    }

    /**
     * Url::is_host_relative()
     * 
     * @return
     */
    public function is_host_relative()
    {
        return ($this->scheme == '' && $this->host == '' && substr($this->path, 0, 1) == '/');
    }

    /**
     * Url::is_absolute()
     * 
     * @return
     */
    public function is_absolute()
    {
        return ($this->scheme != '');
    }

    /**
     * Url::is_protocol_relative()
     * 
     * @return
     */
    public function is_protocol_relative()
    {
        return (substr($this->original_url, 0, 2) == '//');
    }

    /**
     * Url::__toString()
     * 
     * @return
     */
    public function __toString() {
        return $this->write();
    }

    /**
     * Url::write()
     * 
     * @return
     */
    public function write($write_flags = self::WRITE_FLAG_AS_IS)
    {
        $show_scheme = $this->scheme && (!($write_flags & self::WRITE_FLAG_OMIT_SCHEME));
        $show_authority = $this->host && (!($write_flags & self::WRITE_FLAG_OMIT_HOST));
        $url = ($show_scheme ? $this->scheme . ':' : '');
        if ($show_authority || $this->scheme == 'file') $url .= '//';
        if ($show_authority) {
            $url .= $this->getAuthority();
        }
        $url .= ($this->path ? $this->path : '');
        $url .= ($this->query ? '?' . $this->query : '');
        $url .= ($this->fragment ? '#' . $this->fragment : '');
        return $url;
    }

    /**
     * Url::setFragment()
     * 
     * @return
     */
    public function setFragment($fragment)
    {
        $this->fragment = $fragment;
        return $this;
    }

    /**
     * Url::getFragment()
     * 
     * @return
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * Url::setHost()
     * 
     * @return
     */
    public function setHost($host)
    {
        $this->host = strtolower($host);
        return $this;
    }

    /**
     * Url::getHost()
     * 
     * @return
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Url::setPass()
     * 
     * @return
     */
    public function setPass($pass)
    {
        $this->pass = $pass;
        return $this;
    }

    /**
     * Url::getPass()
     * 
     * @return
     */
    public function getPass()
    {
        return $this->pass;
    }

    /**
     * Url::setPath()
     * 
     * @return
     */
    public function setPath($path)
    {
        $this->path = static::normalizePath($path);
        return $this;
    }

    /**
     * Url::getPath()
     * 
     * @return
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Url::setPort()
     * 
     * @return
     */
    public function setPort($port)
    {
        $this->port = ($port) ? intval($port) : null;
    }

    /**
     * Url::getPort()
     * 
     * @return
     */
    public function getPort()
    {
        $port = $this->port;
        $default_ports = ['http' => 80,'https' => 443,'ftp' => 21];
        foreach ($default_ports as $scheme => $dp) {
            if ($this->scheme == $scheme && $port == $dp) {
                $port = null;
            }
        }
        return $port;
    }

    /**
     * Url::setQuery()
     * 
     * @return
     */
    public function setQuery($query)
    {
        $this->query = $query;
        parse_str($this->query, $this->query_array);
        return $this;
    }

    /**
     * Url::getQuery()
     * 
     * @return
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Url::setScheme()
     * 
     * @return
     */
    public function setScheme($scheme)
    {
        $this->scheme = strtolower($scheme);
        return $this;
    }

    /**
     * Url::getScheme()
     * 
     * @return
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Url::setUser()
     * 
     * @return
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Url::getUser()
     * 
     * @return
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Url::getFilename()
     * 
     * @return
     */
    public function getFilename()
    {
        return static::filename($this->path);
    }

    /**
     * Url::getDirname()
     * 
     * @return
     */
    public function getDirname()
    {
        return static::dirname($this->path);
    }

    /**
     * Url::appendPathSegment()
     * 
     * @return
     */
    public function appendPathSegment($segment)
    {
        if (substr($this->path, -1) != static::PATH_SEGMENT_SEPARATOR) $this->path .= static::PATH_SEGMENT_SEPARATOR;
        if (substr($segment, 0, 1) == static::PATH_SEGMENT_SEPARATOR) $segment = substr($segment, 1);
        $this->path .= $segment;
        return $this;
    }

    /**
     * Url::hasQueryParameter()
     * 
     * @return
     */
    public function hasQueryParameter($name)
    {
        return isset($this->query_array[$name]);
    }

    /**
     * Url::getQueryParameter()
     * 
     * @return
     */
    public function getQueryParameter($name)
    {
        return (isset($this->query_array[$name])) ? $this->query_array[$name] : null;
    }

    /**
     * Url::setQueryParameter()
     * 
     * @return
     */
    public function setQueryParameter($name, $value)
    {
        $this->query_array[$name] = $value;
        $this->query = http_build_query($this->query_array);
        return $this;
    }

    /**
     * Url::setQueryFromArray()
     * 
     * @return
     */
    public function setQueryFromArray(array $query_array)
    {
        $this->query_array = $query_array;
        $this->query = http_build_query($this->query_array);
        return $this;
    }

    /**
     * Url::getQueryArray()
     * 
     * @return
     */
    public function getQueryArray()
    {
        return $this->query_array;
    }

    /**
     * Url::makeAbsolute()
     * 
     * @return
     */
    public function makeAbsolute($baseurl = null) {
        if (!$baseurl) return $this;
        if (!$baseurl instanceof Url) $baseurl = new static($baseurl);
        if ($this->is_url() && ($this->is_relative() || $this->is_host_relative() || $this->is_protocol_relative()) && $baseurl instanceof Url) {
            if (!$this->host) $this->host = $baseurl->getHost();
            $this->scheme = $baseurl->getScheme();
            $this->user = $baseurl->getUser();
            $this->pass = $baseurl->getPass();
            $this->port = $baseurl->getPort();
            $this->path = static::buildAbsolutePath($this->path, $baseurl->getPath());
        }
        return $this;
    }

    /**
     * Url::buildAbsolutePath()
     * 
     * @return
     */
    static public function buildAbsolutePath($relative_path, $basepath) {
        if (strpos($relative_path, static::PATH_SEGMENT_SEPARATOR) === 0) {
            return static::normalizePath($relative_path);
        }
        $basedir = static::dirname($basepath);
        if ($basedir == '.' || $basedir == static::PATH_SEGMENT_SEPARATOR || $basedir == '\\' || $basedir == DIRECTORY_SEPARATOR) $basedir = '';
        return static::normalizePath($basedir . self::PATH_SEGMENT_SEPARATOR . $relative_path);
    }

    /**
     * Url::normalizePath()
     * 
     * @return
     */
    static public function normalizePath($path)
    {
        $path = preg_replace('|/\./|', '/', $path);   // entferne /./
        $path = preg_replace('|^\./|', '', $path);    // entferne ./ am Anfang
        $i = 0;
        while (preg_match('|[^/]+/\.{2}/|', $path) && $i < 10) {
            $path = preg_replace_callback('|([^/]+)(/\.{2}/)|', function($matches){
                return ($matches[1] == '..' ? $matches[0] : '');
            }, $path);
            $i++;
        }
        return $path;
    }

    /**
     * Url::filename()
     * 
     * @return
     */
    static public function filename($path)
    {
        return (substr($path, -1) == self::PATH_SEGMENT_SEPARATOR) ? '' : basename($path);
    }

    /**
     * Url::dirname()
     * 
     * @return
     */
    static public function dirname($path)
    {
        if (substr($path, -1) == self::PATH_SEGMENT_SEPARATOR) return substr($path, 0, -1);
        else {
            $d = dirname($path);
            if ($d == DIRECTORY_SEPARATOR) $d = self::PATH_SEGMENT_SEPARATOR;
            return $d;
        }
    }

    /**
     * Url::parse()
     * 
     * @return
     */
    static public function parse($url)
    {
        return new static($url);
    }
}
