<?php

use Symfony\Component\DomCrawler\Crawler;

if (! function_exists('getDom')) {
    /**
     * Assign high numeric IDs to a config item to force appending.
     *
     * @param  array  $array
     * @return array
     */
    function getDom(string $string)
    {
        return Crawler($string);
    }
}
