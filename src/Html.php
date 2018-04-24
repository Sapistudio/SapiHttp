<?php
namespace SapiStudio\Http;

use Exception;

class Html
{
    protected $htmlDom;
    protected $domCrawler;
    
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
    
    /**
     * Html::getDomImages()
     * 
     * @param mixed $imagesAttributes
     * @return
     */
    public function getDomImages($imagesAttributes = ['src','title','alt']){
        return $this->domCrawler->filterXpath('//img')->extract($imagesAttributes);
    }
    
    /**
     * Html::getDomUris()
     * 
     * @param mixed $urisAttributes
     * @return
     */
    public function getDomUris($urisAttributes = ['href','title','alt']){
        return $this->domCrawler->filterXpath('//a')->extract($urisAttributes);
    }
    
    /**
     * Html::getDomAreas()
     * 
     * @param mixed $areasAttributes
     * @return
     */
    public function getDomAreas($areasAttributes = ['href','title','alt']){
        return $this->domCrawler->filterXpath('//area')->extract($areasAttributes);
    }
    
    /**
     * Html::getAllLinks()
     * 
     * @return
     */
    public function getAllLinks(){
        return array_merge($this->getDomUris(),$this->getDomAreas());
    }
    
    /**
     * Html::isValidURL()
     * 
     * @param mixed $url
     * @return
     */
    public static function isValidURL($url = null){
        return preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url);
    }
    
    /**
     * Html::urlIsImage()
     * 
     * @param mixed $contentType
     * @return
     */
    public static function urlIsImage($contentType = null){
        switch ($contentType) {
            case 'image/png' :
            case 'image/jpg' :
            case 'image/jpeg':
            case 'image/gif' :
                $urlIsImage = true;
            break;
        default:
                $urlIsImage = false;
          break;
      }
      return $urlIsImage;
    }
}
