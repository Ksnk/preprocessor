<?php

/**
 * проверка фильтра markdown по нарастающей
 */

    if (!defined('PHPUnit_MAIN_METHOD')) {
        ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.dirname(dirname(__FILE__))) ;
        require 'PHPUnit/Autoload.php' ;
    }

    include_once ("src/wiki.ext.php");
    include_once ("src/point.ext.php");
    include_once ("src/preprocessor.class.php");

class markdownTest extends PHPUnit_Framework_TestCase {
    function testMarkdown1 (){
        $data=<<<MARKDOWN
# Hello

It's a trap
MARKDOWN;
        $result=<<<HTML
<h1>Hello</h1>

<p>It's a trap</p>

HTML;
        $preprocessor=preprocessor::instance();
        POINT::clear();
        POINT::inline('test1',$data);
        $this->assertEquals(str_replace("\r\n","\n",$result),POINT::get('test1','markdown-html'));
    }
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('markdownTest');
    PHPUnit_TextUI_TestRunner::run( $suite);
}
?>
