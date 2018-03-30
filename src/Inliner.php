<?php
namespace SapiStudio\Http;

use DOMDocument;
use DOMXPath;
use exception;
use Sabberworm\CSS;
use Symfony\Component\CssSelector\CssSelectorConverter;

class Inliner
{
    private $css;
    private $dom;
    private $finder;


    /**
     * Inliner::addCSS()
     * 
     * @return
     */
    public function addCSS($filename)
    {
        if (($css = @file_get_contents($filename)) === false) {
            throw new \Exception('Invalid css file path provided.');
        }
        $this->css .= $css;
    }

    /**
     * Inliner::getCSS()
     * 
     * @return
     */
    private function getCSS()
    {
        $this->domCrawler->filter('style')->each(function ($crawler){$this->css .= $crawler->text();foreach ($crawler as $node){$node->parentNode->removeChild($node);}});
        $parser = new CSS\Parser($this->css);
        $css = $parser->parse();
        if(!$css){
            throw new \Exception('There are no CSS rules provided.');
        }
        return $css;
    }

    /**
     * Inliner::render()
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
