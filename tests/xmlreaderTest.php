<?php


if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.dirname(dirname(__FILE__))) ;
    require 'PHPUnit/Autoload.php' ;
}

include_once ("src/wiki.ext.php");
include_once ("src/point.ext.php");
include_once ("src/preprocessor.class.php");

class xmlreaderTest extends PHPUnit_Framework_TestCase {

    function nl2nl($s){
        return trim(str_replace(array("\r\n","\r"),array("\n","\n"),$s))  ;
    }

    function createTestFiles($number,$dir='tmp',$prefix='tmp',$suffix='.tmp'){
        if(!is_dir($dir))
            mkdir($dir);
        for($i=0;$i<$number;$i++){
            file_put_contents($dir.'/'.$prefix.sprintf('%04d',$i).$suffix,' ');
        }
    }

    function removeTestFiles($dir='tmp',$prefix='tmp',$suffix='.tmp'){
        $files=glob($dir.'/'.$prefix.'*'.$suffix);
        foreach($files as $file)
            unlink($file);
        $files=glob($dir.'/*.*');
        if(empty($files))
            rmdir($dir);
    }

    function testEmptyDest(){
        $config=<<<XML
<config>
    <files dir="test1" dstdir="test2">
        <file dstdir="">*.*</file>
    </files>
</config>
XML;
        $class = new ReflectionClass('preprocessor');
        $opt = $class->getProperty('store');
        $opt->setAccessible(true);
        $this->createTestFiles(4,'test1');
        $preprocessor=preprocessor::instance();
        $preprocessor->xml_read($config);
        $store=$opt->getValue($preprocessor);
        $this->assertEquals(count($store),4) ;

        $preprocessor->xml_read($config);
        $store=$opt->getValue($preprocessor);
        $this->assertEquals(count($store),8) ;


        $this->removeTestFiles('test1');
    }

    function testSupportFunctions(){
        $this->createTestFiles(20,'test1');
        $files=glob('test1/*.*');
        $this->assertEquals(20,count($files));
        $this->assertTrue(is_dir('test1'));
        $this->removeTestFiles('test1');
        $this->assertFalse(is_dir('test1'));
    }

    function testXmlReader(){
        $config=<<<XML
<config>
    <file dir="test/test1">*.*</file>
</config>
XML;
        $class = new ReflectionClass('preprocessor');
        $opt = $class->getProperty('store');
        $opt->setAccessible(true);
        $this->createTestFiles(4,'test/test1');
        $preprocessor=preprocessor::instance();
        $preprocessor->xml_read($config);
        $store=$opt->getValue($preprocessor);
        $this->assertEquals(count($store),4) ;
        $config=<<<XML1
<config>
    <remove>test1\\*1.tmp</remove>
</config>
XML1;

        $preprocessor->xml_read($config);
        $store=$opt->getValue($preprocessor);
        $this->assertEquals(count($store),3) ;


        $this->removeTestFiles('test/test1');
    }

}
if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('xmlreaderTest');
    PHPUnit_TextUI_TestRunner::run( $suite);
}
?>