<?php
namespace SapiStudio\Http;

use Exception;

class Html
{
    protected $htmlDom;
    protected $domCrawler;
    protected $elementsToSearch = null;
    protected $parsedElements   = [];
    
    /**
     * Html::loadHtml()
     * 
     * @return
     */
    public static function loadHtml($html){
        return new static($html);
    }
    
    /**
     * Html::__construct()
     * 
     * @return
     */
    public function __construct($html=''){
        $this->htmlDom      = $html;
        $this->domCrawler   = getDomCrawler($html);
    }
}
