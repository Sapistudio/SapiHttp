<?php
namespace SapiStudio\Http\Html;

use Exception;
use SapiStudio\Http\Html as Handler;

class Extractor extends Handler
{
    protected $elementsToSearch = null;
    protected $parsedElements   = [];
    
    /**
     * Extractor::filterElements()
     * 
     * @return
     */
    public function filterElements($elements){
        $this->elementsToSearch = is_array($elements) ? $elements : [$elements];
        foreach($this->elementsToSearch as $key=>$elementName){
            $this->parseElement($elementName);
        }
    }
    
    /**
     * Extractor::parseElement()
     * 
     * @return
     */
    protected function parseElement($element){
        $parsed = [];
        $this->domCrawler->filterXpath('//'.$element)->each(function($elementCrawler) use (&$parsed){
            foreach($elementCrawler->getNode(0)->attributes as $attr) {
                $elementAttributes[$attr->nodeName]= $attr->nodeValue;
            }
            $parsed[$elementCrawler->getNode(0)->nodeName]['attributes'] = $elementAttributes;
            $values = $elementCrawler->children()->each(function ($node) use (&$parsed){
            $parsed[$elementCrawler->getNode(0)->nodeName]['values'][] = $node->html();
            });
        });
        $this->parsedElements = array_merge($this->parsedElements,$parsed);
    }

    /**
     * Extractor::render()
     * 
     * @return
     */
    public function render($html)
    {
        $converter          = new CssSelectorConverter();
        $this->domCrawler   = getDomCrawler($html);
        $this->css          = $this->getCSS();
        foreach ($this->css->getAllRuleSets() as $ruleSet) {
            $selector = $ruleSet->getSelector();
            foreach ($this->domCrawler->evaluate($converter->toXPath($selector[0])) as $node) {
                $rules = $node->getAttribute('style') ? $node->getAttribute('style') . implode(' ',$ruleSet->getRules()) : implode(' ', $ruleSet->getRules());
                $node->setAttribute('style', $rules);
            }
        }
        return preg_replace('/\s+/', ' ', str_replace("\r\n", '',$this->domCrawler->html()));
    }
}
