<?php
namespace SapiStudio\Http;

use SessionHandlerInterface;
use Illuminate\Session\Store;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Filesystem\Filesystem;

class Session implements SessionHandlerInterface
{
    protected $sessionHandler;
    protected $config;
    private static $instance;
    private $sessionId = null;

    /**
     * Session::__construct()
     * 
     * @return
     */
    public function __construct($argument = null)
    {
        $this->config = require __dir__.DIRECTORY_SEPARATOR.'configs'.DIRECTORY_SEPARATOR.'config.php';
        if (is_array($argument))
            $this->setConfig($argument);
        return $this->dispatch();
        
    }

    /**
     * Session::destroy()
     * 
     * @return
     */
    public static function clearSession(){
        session_destroy();
        self::$instance->invalidate();
        setcookie(self::$instance->getName(), self::$instance->getId(), time() - 3600 ,'/');
        return true;
    }
    
    /**
     * Session::globalise()
     * 
     * @return
     */
    public function globalise($alias = null)
    {
        if (!is_null($alias)){
            if (substr($alias, 0, 1) != '\\')
                $alias = '\\' . $alias;
            if (class_exists($alias))
                throw new \Exception('Class already exists!');
            class_alias(get_class($this), $alias);
        }
        self::$instance = $this;
    }

    /**
     * Session::__call()
     * 
     * @return
     */
    public function __call($name, $arguments)
    {
        $this->checkIntegrity();
        $return = call_user_func_array([$this->sessionHandler, $name], $arguments);
        $_SESSION = array_replace_recursive($_SESSION, $this->sessionHandler->all());
        return $return;
    }

    /**
     * Session::__callStatic()
     * 
     * @return
     */
    public static function __callStatic($name, $args)
    {
        if (empty(self::$instance))
            throw new \Exception('You need to run globalise first!');
        return call_user_func_array([self::$instance,$name], $args);
    }

    /**
     * Session::save()
     * 
     * @return
     */
    public function save()
    {
        $this->checkIntegrity();
        $this->sessionHandler->save();
    }

    /**
     * Session::checkIntegrity()
     * 
     * @return
     */
    public function checkIntegrity()
    {
        $combine = array_merge(['put' => array_diff_assoc($_SESSION, $this->sessionHandler->all())],['forget' => array_keys(array_diff_assoc($this->sessionHandler->all(), $_SESSION))]);
        if ($combine) {
            foreach($combine as $method => $dataArray) {
                if (!empty($dataArray)) {
                    $this->sessionHandler->$method($dataArray);
                }
            }
        }
        return true;
    }

    /**
     * Session::dispatch()
     * 
     * @return
     */
    protected function dispatch()
    {
        switch ($this->config['driver']) {
            case 'file':
            default:
                $this->sessionHandler = new Store($this->config['cookie'], $this->fileHandler());
                break;
        }
        $name = $this->sessionHandler->getName();
        if (isset($_COOKIE[$name]) && $sessionId = $_COOKIE[$name]) {
            $this->sessionHandler->setId($sessionId);
        } elseif (!isset($_COOKIE[$name])) {
            setcookie($name, $this->getId(), time() + 60 * $this->config['lifetime'], $this->config['path'], $this->config['domain'], $this->config['secure'], $this->config['http_only']);
        }
        $this->sessionHandler->start();
        register_shutdown_function([$this, 'save']);
        $this->initiatePhpSession();
        $this->globalise();
        return $this;
    }

    /**
     * Session::initiatePhpSession()
     * 
     * @return
     */
    protected function initiatePhpSession()
    {
        ini_set('session.serialize_handler','php_serialize');
        session_id($this->getId());
        session_set_save_handler($this, true);
        session_start();
    }
    /**
     * Session::fileHandler()
     * 
     * @return
     */
    protected function fileHandler()
    {
        return new FileSessionHandler(new Filesystem, $this->config['files'], $this->config['lifetime']);
    }

    /**
     * Session::setConfig()
     * 
     * @return
     */
    public function setConfig(array $config)
    {
        $this->config = array_replace($this->config, $config);
        return $this;
    }

    /**
     * Session::getConfig()
     * 
     * @return
     */
    public function getConfig()
    {
        return $this->config;
    }


    /**
     * Session::open()
     * 
     * @return
     */
    public function open($savePath, $sessionName)
    {
        return $this->sessionHandler->getHandler()->open($savePath, $sessionName);
    }

    /**
     * Session::close()
     * 
     * @return
     */
    public function close()
    {
        return $this->sessionHandler->getHandler()->close();
    }

    /**
     * Session::read()
     * 
     * @return
     */
    public function read($id)
    {
        return $this->sessionHandler->getHandler()->read($id);
    }

    /**
     * Session::write()
     * 
     * @return
     */
    public function write($id, $data)
    {
        return $this->sessionHandler->save();
    }

    /**
     * Session::destroy()
     * 
     * @return
     */
    public function destroy($id)
    {
        return $this->sessionHandler->getHandler()->destroy($id);
    }

    /**
     * Session::gc()
     * 
     * @return
     */
    public function gc($maxlifetime)
    {
        return $this->sessionHandler->getHandler()->gc($maxlifetime);
    }
}
