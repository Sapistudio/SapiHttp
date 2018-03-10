<?php

use Symfony\Component\DomCrawler\Crawler;

if (! function_exists('getDomCrawler')) {
    /**
     */
    function getDomCrawler($content = '')
    {
        return new Crawler($content);
    }
}
