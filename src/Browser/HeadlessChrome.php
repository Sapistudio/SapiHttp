<?php
/** fork from https://github.com/spatie/browsershot*/
namespace SapiStudio\Http\Browser;

use Symfony\Component\Process\Process;
use SapiStudio\Http\Exceptions\CouldNotTakeBrowsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;


class HeadlessChrome
{
    protected $nodeBinary           = 'node';
    protected $npmBinary            = 'npm';
    protected $includePath          = '$PATH:/usr/local/bin';
    protected $networkIdleTimeout   = 0;
    protected $clip                 = null;
    protected $deviceScaleFactor    = 1;
    protected $format               = null;
    protected $fullPage             = false;
    protected $html                 = '';
    protected $landscape            = false;
    protected $margins              = null;
    protected $noSandbox            = true;
    protected $pages                = '';
    protected $paperHeight          = 0;
    protected $paperWidth           = 0;
    protected $showBackground       = false;
    protected $showBrowserHeaderAndFooter = false;
    protected $temporaryHtmlDirectory;
    protected $timeout              = 60;
    protected $url                  = '';
    protected $userAgent            = '';
    protected $windowHeight         = 1200;
    protected $windowWidth          = 1920;

    /**
     * ChromeClient::url()
     * 
     * @param mixed $url
     * @return
     */
    public static function url($url)
    {
        return (new static)->setUrl($url);
    }
    
    /**
     * ChromeClient::file()
     * 
     * @param mixed $filePath
     * @return
     */
    public static function file($filePath)
    {
        return (new static)->setFile($filePath);
    }

    /**
     * ChromeClient::html()
     * 
     * @param mixed $html
     * @return
     */
    public static function html($html)
    {
        return (new static)->setHtml($html);
    }

    /**
     * ChromeClient::__construct()
     * 
     * @param mixed $url
     * @return
     */
    public function __construct($url = NULL)
    {
        $this->url = $url;
    }

    /**
     * ChromeClient::setNodeBinary()
     * 
     * @param mixed $nodeBinary
     * @return
     */
    public function setNodeBinary($nodeBinary = NULL)
    {
        $this->nodeBinary = $nodeBinary;
        return $this;
    }

    /**
     * ChromeClient::setNpmBinary()
     * 
     * @param mixed $npmBinary
     * @return
     */
    public function setNpmBinary($npmBinary = NULL)
    {
        $this->npmBinary = $npmBinary;
        return $this;
    }

    /**
     * ChromeClient::setIncludePath()
     * 
     * @param mixed $includePath
     * @return
     */
    public function setIncludePath($includePath = NULL)
    {
        $this->includePath = $includePath;
        return $this;
    }

    /**
     * ChromeClient::setNetworkIdleTimeout()
     * 
     * @param mixed $networkIdleTimeout
     * @return
     */
    public function setNetworkIdleTimeout(int $networkIdleTimeout = NULL)
    {
        $this->networkIdleTimeout = $networkIdleTimeout;
        return $this;
    }

    /**
     * ChromeClient::setUrl()
     * 
     * @param mixed $url
     * @return
     */
    public function setUrl($url = NULL)
    {
        $this->url = $url;
        $this->html = '';
        return $this;
    }
    
    /**
     * ChromeClient::setFile()
     * 
     * @param mixed $file
     * @return
     */
    public function setFile($file = NULL)
    {
        $this->url = "file://{$file}";;
        $this->html = '';
        return $this;
    }

    /**
     * ChromeClient::setHtml()
     * 
     * @param mixed $html
     * @return
     */
    public function setHtml($html = NULL)
    {
        $this->html = $html;
        $this->url = '';
        $this->hideBrowserHeaderAndFooter();
        return $this;
    }

    /**
     * ChromeClient::clip()
     * 
     * @param mixed $x
     * @param mixed $y
     * @param mixed $width
     * @param mixed $height
     * @return
     */
    public function clip(int $x, int $y, int $width, int $height)
    {
        $this->clip = compact('x', 'y', 'width', 'height');
        return $this;
    }

    /**
     * ChromeClient::showBrowserHeaderAndFooter()
     * 
     * @return
     */
    public function showBrowserHeaderAndFooter()
    {
        $this->showBrowserHeaderAndFooter = true;
        return $this;
    }

    /**
     * ChromeClient::hideBrowserHeaderAndFooter()
     * 
     * @return
     */
    public function hideBrowserHeaderAndFooter()
    {
        $this->showBrowserHeaderAndFooter = false;
        return $this;
    }

    /**
     * ChromeClient::deviceScaleFactor()
     * 
     * @param mixed $deviceScaleFactor
     * @return
     */
    public function deviceScaleFactor($deviceScaleFactor)
    {
        // Google Chrome currently supports values of 1, 2, and 3.
        $this->deviceScaleFactor = max(1, min(3, $deviceScaleFactor));
        return $this;
    }

    /**
     * ChromeClient::fullPage()
     * 
     * @return
     */
    public function fullPage()
    {
        $this->fullPage = true;
        return $this;
    }

    /**
     * ChromeClient::showBackground()
     * 
     * @return
     */
    public function showBackground()
    {
        $this->showBackground = true;
        return $this;
    }

    /**
     * ChromeClient::hideBackground()
     * 
     * @return
     */
    public function hideBackground()
    {
        $this->showBackground = false;
        return $this;
    }

    /**
     * ChromeClient::landscape()
     * 
     * @param bool $landscape
     * @return
     */
    public function landscape($landscape = true)
    {
        $this->landscape = $landscape;
        return $this;
    }

    /**
     * ChromeClient::margins()
     * 
     * @param mixed $top
     * @param mixed $right
     * @param mixed $bottom
     * @param mixed $left
     * @return
     */
    public function margins(int $top, int $right, int $bottom, int $left)
    {
        $this->margins = compact('top', 'right', 'bottom', 'left');
        return $this;
    }

    /**
     * ChromeClient::noSandbox()
     * 
     * @return
     */
    public function noSandbox()
    {
        $this->noSandbox = true;
        return $this;
    }

    /**
     * ChromeClient::pages()
     * 
     * @param mixed $pages
     * @return
     */
    public function pages($pages)
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * ChromeClient::paperSize()
     * 
     * @param mixed $width
     * @param mixed $height
     * @return
     */
    public function paperSize($width,$height)
    {
        $this->paperWidth = $width;
        $this->paperHeight = $height;
        return $this;
    }

    /**
     * ChromeClient::format()
     * 
     * @param mixed $format
     * @return
     */
    public function format($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * ChromeClient::timeout()
     * 
     * @param mixed $timeout
     * @return
     */
    public function timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * ChromeClient::userAgent()
     * 
     * @param mixed $userAgent
     * @return
     */
    public function userAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * ChromeClient::windowSize()
     * 
     * @param mixed $width
     * @param mixed $height
     * @return
     */
    public function windowSize($width,$height)
    {
        $this->windowWidth  = $width;
        $this->windowHeight = $height;
        return $this;
    }

    /**
     * ChromeClient::save()
     * 
     * @param mixed $targetPath
     * @return
     */
    public function save($targetPath)
    {
        if (strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) === 'pdf') {
            return $this->savePdf($targetPath);
        }
        $command = $this->createScreenshotCommand($targetPath);
        $this->callBrowser($command);
        $this->cleanupTemporaryHtmlFile();
        if (! file_exists($targetPath)) {
            throw CouldNotTakeBrowsershot::chromeOutputEmpty($targetPath);
        }
    }

    /**
     * ChromeClient::bodyHtml()
     * 
     * @return
     */
    public function bodyHtml()
    {
        $command = $this->createBodyHtmlCommand();
        return $this->callBrowser($command);
    }

    /**
     * ChromeClient::savePdf()
     * 
     * @param mixed $targetPath
     * @return
     */
    public function savePdf($targetPath)
    {
        $command = $this->createPdfCommand($targetPath);
        $this->callBrowser($command);
        $this->cleanupTemporaryHtmlFile();
        if (! file_exists($targetPath)) {
            throw CouldNotTakeBrowsershot::chromeOutputEmpty($targetPath);
        }
    }

    /**
     * ChromeClient::createBodyHtmlCommand()
     * 
     * @return
     */
    public function createBodyHtmlCommand()
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;
        return $this->createCommand($url, 'content');
    }

    /**
     * ChromeClient::createScreenshotCommand()
     * 
     * @param mixed $targetPath
     * @return
     */
    public function createScreenshotCommand($targetPath)
    {
        $url        = $this->html ? $this->createTemporaryHtmlFile() : $this->url;
        $command    = $this->createCommand($url, 'screenshot', ['path' => $targetPath]);
        if ($this->fullPage) {
            $command['options']['fullPage'] = true;
        }
        if ($this->clip) {
            $command['options']['clip'] = $this->clip;
        }
        return $command;
    }

    /**
     * ChromeClient::createPdfCommand()
     * 
     * @param mixed $targetPath
     * @return
     */
    public function createPdfCommand($targetPath)
    {
        $url        = $this->html ? $this->createTemporaryHtmlFile() : $this->url;
        $command    = $this->createCommand($url, 'pdf', ['path' => $targetPath]);
        if ($this->showBrowserHeaderAndFooter) {
            $command['options']['displayHeaderFooter'] = true;
        }
        if ($this->showBackground) {
            $command['options']['printBackground'] = true;
        }
        if ($this->landscape) {
            $command['options']['landscape'] = true;
        }
        if ($this->margins) {
            $command['options']['margin'] = [
                'top' => $this->margins['top'].'mm',
                'right' => $this->margins['right'].'mm',
                'bottom' => $this->margins['bottom'].'mm',
                'left' => $this->margins['left'].'mm',
            ];
        }
        if ($this->pages) {
            $command['options']['pageRanges'] = $this->pages;
        }
        if ($this->paperWidth > 0 && $this->paperHeight > 0) {
            $command['options']['width'] = $this->paperWidth.'mm';
            $command['options']['height'] = $this->paperHeight.'mm';
        }
        if ($this->format) {
            $command['options']['format'] = $this->format;
        }
        return $command;
    }

    /**
     * ChromeClient::createCommand()
     * 
     * @param mixed $url
     * @param mixed $action
     * @param mixed $options
     * @return
     */
    protected function createCommand($url,$action,$options = [])
    {
        $command = compact('url', 'action', 'options');
        $command['options']['viewport'] = [
            'width' => $this->windowWidth,
            'height' => $this->windowHeight,
        ];
        if ($this->userAgent) {
            $command['options']['userAgent'] = $this->userAgent;
        }
        if ($this->deviceScaleFactor > 1) {
            $command['options']['viewport']['deviceScaleFactor'] = $this->deviceScaleFactor;
        }
        if ($this->networkIdleTimeout > 0) {
            $command['options']['networkIdleTimeout'] = $this->networkIdleTimeout;
        }
        if ($this->noSandbox) {
            //$command['options']['args'] = ['--no-sandbox','--window-size=1920,1080','--force-device-scale-factor=2'];
            $command['options']['args'] = ['--no-sandbox'];
        }
        return $command;
    }

    /**
     * ChromeClient::createTemporaryHtmlFile()
     * 
     * @return
     */
    protected function createTemporaryHtmlFile()
    {
        $this->temporaryHtmlDirectory = (new TemporaryDirectory())->create();
        file_put_contents($temporaryHtmlFile = $this->temporaryHtmlDirectory->path('index.html'), $this->html);
        return "file://{$temporaryHtmlFile}";
    }

    /**
     * ChromeClient::cleanupTemporaryHtmlFile()
     * 
     * @return
     */
    protected function cleanupTemporaryHtmlFile()
    {
        if ($this->temporaryHtmlDirectory) {
            $this->temporaryHtmlDirectory->delete();
        }
    }
    
    /**
     * ChromeClient::callBrowser()
     * 
     * @param mixed $command
     * @return
     */
    protected function callBrowser($command)
    {
        $setNodePathCommand     = "NODE_PATH=`{$this->npmBinary} root -g`";
        $binPath                = __DIR__.'/../bin/browser.js';
        $fullCommand            = "sudo ".$setIncludePathCommand.' '.$setNodePathCommand.' '.$this->nodeBinary.' '.escapeshellarg($binPath).' '.escapeshellarg(json_encode($command));
        $process                = (new Process($fullCommand))->setTimeout($this->timeout);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return $process->getOutput();
    }
}
