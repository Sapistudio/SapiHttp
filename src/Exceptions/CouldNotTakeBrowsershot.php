<?php
/** fork from https://github.com/spatie/browsershot*/
namespace SapiStudio\Http\Exceptions;

use Exception;

class CouldNotTakeBrowsershot extends Exception
{
    public static function chromeOutputEmpty(string $screenShotPath)
    {
        return new static("For some reason Chrome did not write a file at `{$screenShotPath}`.");
    }
}
