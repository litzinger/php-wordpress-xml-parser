<?php

namespace Litzinger\PHPWordpressXMLParser;

use DOMDocument;
use Litzinger\PHPWordpressXMLParser\Parsers\Regex;
use Litzinger\PHPWordpressXMLParser\Parsers\SimpleXML;

class Parser
{
    public static function parseString(string $content)
    {
        // Attempt to use proper XML parsers first
        if (extension_loaded('simplexml')) {
            $parser = new SimpleXML;

            $dom = new DOMDocument();
            $old_value = null;

            if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
                $old_value = libxml_disable_entity_loader( true );
            }

            $success = $dom->loadXML($content);
            if ( ! is_null( $old_value ) ) {
                libxml_disable_entity_loader( $old_value );
            }

            if ( ! $success || isset($dom->doctype) ) {
                throw new \Exception( 'There was an error when reading this WXR file' );
            }

            $result = $parser->parseString(simplexml_import_dom($dom));

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
