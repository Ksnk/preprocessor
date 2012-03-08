<?php


if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.dirname(dirname(__FILE__))) ;
    require 'PHPUnit/Autoload.php' ;
}

include_once ("src/wiki.ext.php");
include_once ("src/point.ext.php");
include_once ("src/preprocessor.class.php");
function pps(&$x,$default=''){if(empty($x))return $default; else return $x;}

class preprocessorTest extends PHPUnit_Framework_TestCase {

    /**
     * тестирование шаблона с генерацией нового класса
     */
    function wikitxt($data){
        static $wiki_parcer;
        if(empty($wiki_parcer)) $wiki_parcer= new wiki_parcer();

        $wiki_parcer->read_string($data);
        return $wiki_parcer->wiki_txt();
    }


    function testHtml2Js (){
        $data = <<<HTML
<!doctype html>
<html>
<script type="text/javascript">
alert(11);// it's a joke
/**
 *   test1
 */
alert(2);
/**
 *   document!
 */
</script>
<style>
/* kdncdskc */
* {margin:0}
</style>
<body>
<!-- hello! -->
Hello!
</body>
HTML;
        $result='<!doctype html> <html><script type=\"text/javascript\">\nalert(11);\nalert(2);\n</script><style> * {margin:0} </style><body>Hello!</body>';

        $preprocessor=preprocessor::instance();
        POINT::inline('test',$data);
        $this->assertEquals(POINT::get('test','html2js'), $result);
        POINT::clear();
    }

    /**
     * ловим ошибку - вставка кода в // комментарии
     */

    function testPOINTComment (){
        $data=<<<HTML
<?xml version='1.0' standalone='yes'?>
<config>
<files dstdir='tests'>
    <echo name="xx.txt"><![CDATA[
        /*
<% POINT::start('xxx');%>
    it's a text
<% POINT::finish();%>
 */
// <%=POINT::get('xxx');%>

## <%=POINT::get('xxx');%>
/* <%=POINT::get('xxx');%> */
]]></echo>
    </files>
</config>
HTML;
        $result=<<<HTML

//  ----point::xxx----
it's a text

##  ----point::xxx----
it's a text
    			it's a text
HTML;
        $preprocessor=preprocessor::instance();
        $preprocessor->xml_read($data);
        $preprocessor->process();
        $data=file_get_contents('tests/xx.txt');
        $this->assertEquals($result,$data);
        POINT::clear();
        unlink('tests/xx.txt') ;
    }


}
if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('preprocessorTest');
    PHPUnit_TextUI_TestRunner::run( $suite);
}
?>