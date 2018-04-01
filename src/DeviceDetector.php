<?php
namespace SapiStudio\Http;
use \Mobile_Detect;

class DeviceDetector extends Mobile_Detect
{
    
    /**
     * DeviceDetector::uAgent()
     * 
     * @return
     */
    public static function uAgent($userAgent)
    {
        $self= (new static);
        $self->setUserAgent($userAgent);
        return $self;
    }
    
    /**
     * DeviceDetector::__construct()
     * 
     * @return
     */
    public function __construct($userAgent = "")
    {
        if ($userAgent != "") {
            $this->setUserAgent($userAgent);
        }
    }

    /**
     * DeviceDetector::getBrowser()
     * 
     * @return
     */
    public function getBrowser()
    {
        foreach ($this::$browsers as $browser => $UA) {
            $is_browser = 'is'.$browser;
            if ($this->$is_browser()) {
                return $browser;
            }
        }
        return '';
    }
    
    /**
     * DeviceDetector::getOperatingSystem()
     * 
     * @return
     */
    public function getOperatingSystem()
    {
        foreach ($this::$operatingSystems as $os => $UA) {
            $is_os = 'is'.$os;
            if ($this->$is_os()) {
                return $os;
            }
        }
        return '';
    }
    
    /**
     * DeviceDetector::getDevice()
     * 
     * @return
     */
    public function getDevice()
    {
        $devices = $this->getDevices();
        foreach ($devices as $type => $UA) {
            $is_type = 'is'.$type;
            if ($this->$is_type()) {
                return $type;
            }
        }
        return '';
    }
    
    /**
     * DeviceDetector::getDevices()
     * 
     * @return
     */
    public function getDevices()
    {
        return \array_merge($this::$phoneDevices, $this::$tabletDevices);
    }
    
    /**
     * DeviceDetector::isPhone()
     * 
     * @return
     */
    public function isPhone()
    {
        return \array_key_exists($this->getDevice(), $this::$phoneDevices);
    }
    
    /**
     * DeviceDetector::isSmart()
     * 
     * @return
     */
    public function isSmart()
    {
        return (
            $this->isAndroidOS()
            || $this->isBlackBerryOS()
            || $this->isWindowsMobileOS()
            || $this->isWindowsPhoneOS()
            || $this->isiOS()
            || $this->iswebOS()
            || $this->isbadaOS()
        );
    }
}
