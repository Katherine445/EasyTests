<?php

class DOMParseUtilsUpdated
{
    const VERSION = '2018-03-24';

    /** Export children of $element to an XML string
     * @param $element
     * @param bool $trim
     * @return null|string|string[]
     */
    static function saveChildren($element, $trim = false)
    {
        if ($trim)
            $element = self::trimDOM($element);
        $xml = $element->ownerDocument->saveXML($element, LIBXML_NOEMPTYTAG);
        $xml = preg_replace('/^\s*<[^>]*>(.*?)<\/[^\>]*>\s*$/uis', '\1', $xml);
        $xml = preg_replace('#(<(br|input)(\s+[^<>]*[^/])?)></\2>#', '\1 />', $xml);
        return $xml;
    }

    /** "Trim" tags
     * @param $element
     * @return
     */
    static function trimDOM($element)
    {
        if (!$element->childNodes->length)
            return $element;
        foreach ($element->childNodes as $e)
            if ($e->nodeType == XML_TEXT_NODE && !trim($e->nodeValue))
                $element->removeChild($e);
        if ($element->childNodes->length == 1) {
            $e = $element->childNodes->item(0);
            if ($e->nodeName == 'p' || $e->nodeName == 'li' ||
                $e->nodeName == 'div' || $e->nodeName == 'span'
            )
                return self::trimDOM($e);
        }
        return $element;
    }

    /** Split DOM element by text node containing $mark inside nodeValue
     * @param $element
     * @param $document
     * @param $mark
     * @return array
     */
    static function splitDOM($element, $document, $mark)
    {
        $frags = array($element->cloneNode(false));
        foreach ($element->childNodes as $child) {
            $parts = array();
            if ($child->nodeType == XML_ELEMENT_NODE)
                $parts = self::splitDOM($child, $document, $mark);
            elseif ($child->nodeType == XML_TEXT_NODE) {
                $txt = preg_split('/' . str_replace('/', '\\/', preg_quote($mark)) . '/is', $child->nodeValue);
                if (count($txt) > 1)
                    foreach ($txt as $t)
                        $parts[] = $document->createTextNode($t);
            }
            if (count($parts) > 1) {
                $frags[count($frags) - 1]->appendChild(array_shift($parts));
                while (count($parts)) {
                    $e = $element->cloneNode(false);
                    $e->appendChild(array_shift($parts));
                    $frags[] = $e;
                }
            } else
                $frags[count($frags) - 1]->appendChild($child->cloneNode(true));
        }
        return $frags;
    }

    /** Extract entitled sections from $element using DOM
     * $element     => DOMElement to scan
     * $headingmark => include only headings matching string or regexp at the beginning or at the end
     * $is_regexp   => if true, headingmark is treated as regexp (PCRE)
     * $nodenames   => include only nodes with names matching one of keys of this array, and assume their
     * heading level (an integer number) equal to values of this array. example:
     * array('h1' => 1, 'h2' => 2, <node_name> => <heading_level>, ...)
     * $section0    => if true, also extract the part of scanned element going before any matching heading
     * and return it as the first item of output array
     * Returns NULL when $element has no children.
     * Else returns array(array(
     * 'level'   => <heading_level>,
     * 'title'   => DOMElement <heading_content>,
     * 'match'   => array() <regexp_match_array>,
     * 'content' => DOMElement <section_content>,
     * ), ...)
     * @param $element
     * @param bool $headingmark
     * @param bool $is_regexp
     * @param null $nodenames
     * @param bool $include_section0
     * @return array|null
     */
    static function getSections($element, $headingmark = false, $is_regexp = false,
                                $nodenames = NULL, $include_section0 = false)
    {
        if (!$element->childNodes->length)
            return NULL;
        $document = $element->ownerDocument;
        if (!is_array($nodenames)) {
            $nodenames = array(
                'h1' => 1,
                'h2' => 2,
                'h3' => 3,
                'h4' => 4,
                'h5' => 5,
                'h6' => 6,
            );
        }
        $sections = array();
        $sect = NULL;
        $section0 = NULL;
        foreach ($element->childNodes as $child) {
            if ($child->nodeType == XML_ELEMENT_NODE) {
                if ($level = $nodenames[strtolower($child->nodeName)]) {
                    /* optionally check for heading mark */
                    if ($headingmark)
                        $checked = self::checkNode($child, $headingmark, $is_regexp);
                    else
                        $checked = false;
                    if (!$sect || $level <= $sect['level'] || $checked) {
                        if ($sect) {
                            $sections[] = $sect;
                            $sect = NULL;
                        }
                        if (!$headingmark || $checked) {
                            $sect = array(
                                'level' => $level,
                                'title' => $child,
                                'content' => $document->createElement('section'),
                            );
                            if ($checked)
                                list($sect['title'], $sect['match']) = $checked;
                        }
                        continue;
                    }
                    /* ... non-matching sub-headings are processed as usual
                       (appended to the current section) */
                }
                /* If sub-element itself contains matching sections, exclude it from output */
                $subinc = !$section0 && !$sect && !count($sections) && $include_section0;
                $subslides = self::getSections($child, $headingmark, $is_regexp, $nodenames, $subinc);
                if ($subinc) {
                    $s0 = array_shift($subslides);
                    if ($subslides)
                        $section0 = $s0['content'];
                }
                if ($subslides) {
                    if ($sect)
                        $sections[] = $sect;
                    $sect = NULL;
                    $sections = array_merge($sections, $subslides);
                    continue;
                }
            }
            /* Append $child to the last section */
            if ($child->nodeType != XML_TEXT_NODE || trim($child->nodeValue)) {
                if ($sect)
                    $sect['content']->appendChild($child->cloneNode(true));
                elseif (!count($sections) && $include_section0) {
                    if (!$section0)
                        $section0 = $document->createElement('section');
                    $section0->appendChild($child->cloneNode(true));
                }
            }
        }
        if ($sect)
            $sections[] = $sect;
        if ($include_section0)
            array_unshift($sections, array('content' => $section0));
        return $sections;
    }

    /** Check if $mark is present inside $element. Return false when not.
     * Return copy of $element with $mark removed from it when yes.
     * @param $element
     * @param $mark
     * @param bool $is_regexp
     * @return array|bool
     */
    static function checkNode($element, $mark, $is_regexp = false)
    {
        $document = $element->ownerDocument;
        $html = $document->saveXML($element);
        global $wgLanguageCode;
        $re = str_replace('/', '\\/', $is_regexp ? "(?:$mark)" : preg_quote($mark));
        if (preg_match("/^\s*((?:<[^<>]*>\s*)*)$re/uis", $html, $m, PREG_OFFSET_CAPTURE)) {
            $new = $m[1][0] . substr($html, strlen($m[0][0]));
            /*
             * We should slice array on 2 or 3 length
             * according to empty items
             */
            $simple_question = wfMsgReal('easytests-parse-question', NULL, true, $wgLanguageCode, false);
            if (preg_match("/^\s*((?:<[^<>]*>\s*)*)$simple_question/uis", $m[count($m) - 1][0]) and count($m) > 4) {
                // Remove founding html and spaces
                array_splice($m, 0, 3);
            } else {
                // Remove founding html
                array_splice($m, 0, 2);
            }
            // set question type for hard questions
            if (count($m) > 0 and self::findQuestionType($m, $wgLanguageCode)) {
                $m[count($m) - 1]['type'] = self::findQuestionType($m, $wgLanguageCode);
                // we need array with 2 items, so insert fake element
                // * magic *
                if (count($m) == 1) {
                    array_unshift($m, array('', -1));
                }
            }

        } elseif (preg_match("/$re((?:\s*<[^<>]*>)*)\s*$/uis", $html, $m, PREG_OFFSET_CAPTURE)) {
            $new = substr($html, 0, $m[0][1]) . $m[count($m) - 1][0];
            array_shift($m);
            array_pop($m);
        } else {
            return false;
        }
        $new_document = self::loadDOM($new);
        $new_element = $new_document->documentElement->childNodes->item(0)->childNodes->item(0);
        $new_element = $document->importNode($new_element, true);
        return array($new_element, $m);
    }

    private static function findQuestionType($array, $wgLanguageCode)
    {
        $pattern1 = wfMsgReal('easytests-parse-question-match', NULL, true, $wgLanguageCode, false);
        $pattern2 = wfMsgReal('easytests-parse-question-parallel', NULL, true, $wgLanguageCode, false);
        if (preg_match("/^\s*((?:<[^<>]*>\s*)*)$pattern1/uis", $array[count($array) - 1][0])) {
            return 'order';
        } elseif (preg_match("/^\s*((?:<[^<>]*>\s*)*)$pattern2/uis", $array[count($array) - 1][0])) {
            return 'parallel';
        }
        return false;
    }

    /** Load HTML content into a DOMDocument
     * @param $html
     * @return DOMDocument
     */
    static function loadDOM($html)
    {
        $dom = new DOMDocument();
        $oe = error_reporting();
        error_reporting($oe & ~E_WARNING);
        $dom->loadHTML("<?xml version='1.0' encoding='UTF-8'?>" . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        error_reporting($oe);
        return $dom;
    }

    /** Get list items of outer-level lists
     * @param $element
     * @return array
     */
    static function getListItems($element)
    {
        if (!$element->childNodes->length)
            return array();
        $r = array();
        foreach ($element->childNodes as $child) {
            if ($child->nodeType == XML_ELEMENT_NODE) {
                if ($child->nodeName == 'li')
                    $r[] = $child;
                else
                    $r = array_merge($r, self::getListItems($child));
            }
        }
        return $r;
    }
}
