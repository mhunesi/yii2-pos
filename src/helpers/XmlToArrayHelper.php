<?php
/**
 * Xml Helper
 * https://github.com/ushainformatique/usniframework/blob/master/library/components/XML2Array.php
 * @link http://www.mustafaunesi.com.tr/
 * @copyright Copyright (c) 2019 Polimorf IO
 * @product PhpStorm.
 * @author : Mustafa Hayri ÜNEŞİ <mhunesi@gmail.com>
 * @date: 25.02.2019
 * @time: 11:33
 */

namespace mhunesi\pos\helpers;

use DOMAttr;
use DOMText;
use DOMElement;
use DOMDocument;
use DOMCdataSection;
use DOMNamedNodeMap;

/**
 * XmlToArrayHelper::convert($xml)
 */
class XmlToArrayHelper
{
    protected $document;

    public function __construct(string $xml)
    {
        $this->document = new DOMDocument();
        $this->document->loadXML($xml);
    }
    public static function convert(string $xml): array
    {
        $converter = new static($xml);
        return $converter->toArray();
    }
    protected function convertAttributes(DOMNamedNodeMap $nodeMap)
    {
        if ($nodeMap->length === 0) {
            return null;
        }
        $result = [];
        /** @var DOMAttr $item */
        foreach ($nodeMap as $item) {
            $result[$item->name] = $item->value;
        }
        return ['_attributes' => $result];
    }
    protected function isHomogenous(array $arr)
    {
        $firstValue = current($arr);
        foreach ($arr as $val) {
            if ($firstValue !== $val) {
                return false;
            }
        }
        return true;
    }
    protected function convertDomElement(DOMElement $element)
    {
        $sameNames = false;
        $result = $this->convertAttributes($element->attributes);
        if ($element->childNodes->length > 1) {
            $childNodeNames = [];
            foreach ($element->childNodes as $key => $node) {
                $childNodeNames[] = $node->nodeName;
            }
            $sameNames = $this->isHomogenous($childNodeNames);
        }
        foreach ($element->childNodes as $key => $node) {
            if ($node instanceof DOMCdataSection) {
                $result['_cdata'] = $node->data;
                continue;
            }
            if ($node instanceof DOMText) {
                $result = $node->textContent;
                continue;
            }
            if ($node instanceof DOMElement) {
                if ($sameNames) {
                    $result[$node->nodeName][$key] = $this->convertDomElement($node);
                } else {
                    $result[$node->nodeName] = $this->convertDomElement($node);
                }
                continue;
            }
        }
        return $result;
    }
    public function toArray(): array
    {
        $result = [];
        if ($this->document->hasChildNodes()) {
            $children = $this->document->childNodes;
            foreach ($children as $child) {
                $result[$child->nodeName] = $this->convertDomElement($child);
            }
        }
        return $result;
    }

    public static function isValid ( $xml ) {
        libxml_use_internal_errors( true );

        $doc = new DOMDocument('1.0', 'utf-8');

        $doc->loadXML( $xml );

        $errors = libxml_get_errors();

        return empty( $errors );
    }
}