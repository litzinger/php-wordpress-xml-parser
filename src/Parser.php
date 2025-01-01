<?php

namespace Litzinger\PHPWordpressXMLParser;

use Litzinger\PHPWordpressXMLParser\Parsers\Regex;
use Litzinger\PHPWordpressXMLParser\Parsers\SimpleXML;
use Litzinger\PHPWordpressXMLParser\Parsers\XML;

class Parser
{
    public static function parseString(string $content)
    {
        // Attempt to use proper XML parsers first
        if (extension_loaded('simplexml')) {
            $parser = new SimpleXML;
            $result = $parser->parseString(simplexml_load_string($content));

            return $result;
        }
    }

    public static function parse($file)
    {
        return self::parseFile($file);
    }

    public static function parseFile(string $file)
    {
        // Attempt to use proper XML parsers first
        if (extension_loaded('simplexml')) {
            $parser = new SimpleXML;
            $result = $parser->parse($file);

            return $result;
        }

        // use regular expressions if nothing else available or this is bad XML
        $parser = new Regex;
        return $parser->parse($file);
    }
}
