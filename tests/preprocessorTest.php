<?php

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
        $preprocessor=new preprocessor();
        $GLOBALS['preprocessor']=$preprocessor;
        POINT::inline('test',$data);
        $this->assertEquals(POINT::get('test','html2js'),
            '<!doctype html>\n<html>\n<script type=\"text/javascript\">\nalert(11);\nalert(2);\n\n</script>\n<style> * {margin:0} </style>\n<body>\n\nHello!\n</body>');
    }

    /**
     * тестируем wiki разметку в препроцессоре
     */

    function testWiki1() {
        $data='
Hello world
* one 
* two

just a sign
        
        ';
        $this->assertEquals(
            $this->wikitxt($data),
            'if( \'hello\' ){ world };'
        );
    }

}

?>