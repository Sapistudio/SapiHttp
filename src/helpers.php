<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Debug\Dumper;
use Illuminate\Contracts\Support\Htmlable;

if (! function_exists('getDom')) {
    /**
     * Assign high numeric IDs to a config item to force appending.
     *
     * @param  array  $array
     * @return array
     */
    function getDom(string $string)
    {
        return Symfony\Component\DomCrawler\Crawler($string);
    }
}
