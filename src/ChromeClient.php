<?php
/** fork from https://github.com/spatie/browsershot*/
namespace SapiStudio\Http;

use Symfony\Component\Process\Process;
use SapiStudio\Http\Exceptions\CouldNotTakeBrowsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;


class ChromeClient
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
    protected $windowHeight         = 1800;
    protected $windowWidth          = 2880;

    public static function url($url)
    {
        return (new static)->setUrl($url);
    }

    public static function html($html)
    {
        return (new static)->setHtml($html);
    }

    public function __construct($url = NULL)
    {
        $this->url = $url;
    }

    public function setNodeBinary($nodeBinary = NULL)
    {
        $this->nodeBinary = $nodeBinary;
        return $this;
    }

    public function setNpmBinary($npmBinary = NULL)
    {
        $this->npmBinary = $npmBinary;
        return $this;
    }

    public function setIncludePath($includePath = NULL)
    {
        $this->includePath = $includePath;
        return $this;
    }

    public function setNetworkIdleTimeout(int $networkIdleTimeout = NULL)
    {
        $this->networkIdleTimeout = $networkIdleTimeout;
        return $this;
    }

    public function setUrl($url = NULL)
    {
        $this->url = $url;
        $this->html = '';
        return $this;
    }

    public function setHtml($html = NULL)
    {
        $this->html = $html;
        $this->url = '';
        $this->hideBrowserHeaderAndFooter();
        return $this;
    }

    public function clip(int $x, int $y, int $width, int $height)
    {
        $this->clip = compact('x', 'y', 'width', 'height');
        return $this;
    }

    public function showBrowserHeaderAndFooter()
    {
        $this->showBrowserHeaderAndFooter = true;
        return $this;
    }

    public function hideBrowserHeaderAndFooter()
    {
        $this->showBrowserHeaderAndFooter = false;
        return $this;
    }

    public function deviceScaleFactor($deviceScaleFactor)
    {
        // Google Chrome currently supports values of 1, 2, and 3.
        $this->deviceScaleFactor = max(1, min(3, $deviceScaleFactor));
        return $this;
    }

    public function fullPage()
    {
        $this->fullPage = true;
        return $this;
    }

    public function showBackground()
    {
        $this->showBackground = true;
        return $this;
    }

    public function hideBackground()
    {
        $this->showBackground = false;
        return $this;
    }

    public function landscape($landscape = true)
    {
        $this->landscape = $landscape;
        return $this;
    }

    public function margins(int $top, int $right, int $bottom, int $left)
    {
        $this->margins = compact('top', 'right', 'bottom', 'left');
        return $this;
    }

    public function noSandbox()
    {
        $this->noSandbox = true;
        return $this;
    }

    public function pages($pages)
    {
        $this->pages = $pages;
        return $this;
    }

    public function paperSize($width,$height)
    {
        $this->paperWidth = $width;
        $this->paperHeight = $height;
        return $this;
    }

    public function format($format)
    {
        $this->format = $format;
        return $this;
    }

    public function timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function userAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function windowSize($width,$height)
    {
        $this->windowWidth  = $width;
        $this->windowHeight = $height;
        return $this;
    }

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

    public function bodyHtml()
    {
        $command = $this->createBodyHtmlCommand();
        return $this->callBrowser($command);
    }

    public function savePdf($targetPath)
    {
        $command = $this->createPdfCommand($targetPath);
        $this->callBrowser($command);
        $this->cleanupTemporaryHtmlFile();
        if (! file_exists($targetPath)) {
            throw CouldNotTakeBrowsershot::chromeOutputEmpty($targetPath);
        }
    }

    public function createBodyHtmlCommand()
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;
        return $this->createCommand($url, 'content');
    }

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
            $command['options']['args'] = ['--no-sandbox'];
        }
        return $command;
    }

    protected function createTemporaryHtmlFile()
    {
        $this->temporaryHtmlDirectory = (new TemporaryDirectory())->create();
        file_put_contents($temporaryHtmlFile = $this->temporaryHtmlDirectory->path('index.html'), $this->html);
        return "file://{$temporaryHtmlFile}";
    }

    protected function cleanupTemporaryHtmlFile()
    {
        if ($this->temporaryHtmlDirectory) {
            $this->temporaryHtmlDirectory->delete();
        }
    }
    
    protected function callBrowser($command)
    {
        $setIncludePathCommand  = "PATH={$this->includePath}";
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
