<?php
/**
 * POINTS (MACRO OOP) mechanism for PHP preperocessor
 * @version PHP Preprocessor, written by Ksnk (sergekoriakin@gmail.com). Ver : 1.1
 *  Rev: $WCREV$, Modified: $WCDATE$
 *  SVN: $WCURL$
 * @license License MIT (c) Serge Koriakin - Jule 2010-2012
 */

class POINT {
    static private
        $points=array(),
        $cur_point='',
        $ob_count=0,

        $point_stat=array();
    static public
        $eval_src='',
        $eval_idx=0;

/**
 * Добавить в "точку" содержимое файла с обработкой препроцессором
 * именованный буфер $point_name
 * @param string $point_name
 * @param $filename
 * @return void
 */
    static function file($point_name,$filename){
    	self::inline($point_name,file_get_contents($filename));
    }

    /**
     * Добавить в "точку" содержимое переменной с обработкой препроцессором
      * именованный буфер $point_name
     * @param string $point_name
     * @return void
     */
    static function inline($point_name,$contents){
        //echo $contents;
        if (empty($contents)) return;
        if(!isset(self::$points[$point_name]))
            self::$points[$point_name]=array();
        self::$points[$point_name][]=preg_replace('/^\s+|^\*\/|\s+$|\/\*$/','',$contents);
    }

    /**
     * стартовая метка "точки". Весь вывод после этого оператора попадает в
     * именованный буфер $point_name
     * @param string $point_name
     * @return void
     */
    static function start($point_name){
    	self::finish();
        self::$cur_point=$point_name;
    	ob_start();
    	self::$ob_count++;
    }

    /**
     * финишная метка точки. После этого оператора точка пополняется.
     * @return void
     */
    static function finish(){
    	if(self::$ob_count==0) return;
        self::$ob_count--;
    	//$contents=preg_replace('/^\s+|\s+$/','',ob_get_contents());
    	$contents=ob_get_contents();
    	ob_end_clean();
    	//echo $contents;
    	if (empty($contents)) return;
    	if(!isset(self::$points[self::$cur_point]))
            self::$points[self::$cur_point]=array();

        if(isset(self::$point_stat[(self::$eval_idx++).' '.self::$eval_src]))
            return;
        self::$point_stat[(self::$eval_idx++).' '.self::$eval_src]=true;
        self::$points[self::$cur_point][]=preg_replace('/^\s+|^\*\/|\s+$|\/\*$/','',$contents);
    }

    static function insert($point_name){
    	echo "/****** point $point_name */\r\n".POINT::get($point_name)."/****finish point $point_name *//*\r\n";
    }

    /**
     * вывод содержимого точки. При выводе применяются фильтры, которые позволяют вставляться
     * в комментарии (пустой фильтр), как часть комментария (фильтр comment), с текстовой обработкой (wiki)
     * @param $point_name
     * @param string $filter
     * @return mixed|string
     */
    static function get($point_name, $filters=''){
    	global $preprocessor;
    	//echo "insert_point $point_name */\n\r";
    	$s='';
    	if(isset(self::$points[$point_name]))
    	  $s= join(self::$points[$point_name],"\r\n");
        foreach(explode('|',$filters) as $filter) {
        if($filter=='' || $filter=='comment'){
            $ss=$preprocessor->obget();
            if(preg_match('~(\s+\*)\s*$|(/\*)\s*$|(//)\s*$|(\#\#\s*)$~',$ss,$m)){
                if(!empty($m[1])){
                    // javascript comment
                    $filter = $filter=='comment'?'jscomment':'php_comment';
                } elseif(!empty($m[2])){
                    // javascript comment
                    $filter = $filter=='comment'?'jscomment':'php_comment';
                } elseif(!empty($m[3])){
                    // javascript comment
                    $filter = $filter=='comment'?'everyline_comment':'line_comment';
                } elseif(!empty($m[4])){
                    $filter = $filter=='comment'?'tplcomment':'line_comment';
                }
            }
        }
    	switch($filter){
    		case 'wiki-txt':
    			include_once("wiki.ext.php");
                $s=iconv('UTF-8', 'CP1251//IGNORE',$s);
    			$s= wiki_parcer::convert($s,'txt');
    			break;
            case 'markdown-html':
                $dir=dirname(__FILE__);
                include_once $dir . DIRECTORY_SEPARATOR . 'markdown.filter/markdown.php';

                $s=Markdown($s);
                break;
            case 'markdown-txt':
                $dir=dirname(__FILE__);
                //return 'xxx';
                include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/MlObject.php');
                include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/html2ml.php');
                include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/ml2text.php');
                include_once $dir . DIRECTORY_SEPARATOR . 'markdown.filter/markdown.php';
                $txt='';
               // try{
                    $reader= new reader_HTML();
                    $writer= new writer_Text();
                    $html=Markdown($s);
                    $ml=$reader->parseHtml($html);
                    $txt=$writer->writeText($ml);
               // } catch (Exception $e){
               //     echo($e->getMessage());
               // }
                $s= $txt;
                break;
    		case 'wiki-html':
    			include_once("wiki.ext.php");
                $s= wiki_parcer::convert($s,'html');
    			break;
            case 'line_comment':
                // перед строкой стоит строковый комменарий - выводим с новой строки
                $s= ' ----point::'.$point_name."----\r\n".$s."\r\n";
            case 'everyline_comment':
                 // каждая строка начинается с комментария //
                $s= trim(preg_replace(
    				array('/\n/'),
    				array("\n// "),
    				$s))."\r\n";
    			break;
            case 'tplcomment':
                 // каждая строка начинается с комментария ##
                $s= trim(preg_replace(
    				array('/\n/'),
    				array("\n## "),
    				$s))."\r\n";
    			break;
    		case 'jscomment':
                $s= trim(preg_replace(
    				array('/\n/'),
    				array("\n * "),
    				$s))."\r\n";
    			break;
    		case 'php_comment':
    			// выводим php код в окружении закрывающего - открывающего комментария
                $s= '*/
    			'.$s.'
    			/*';
                break;
    		case 'html2js':
    			// выводим html для вставки в изображение строки с двойными кавычками.
    			// TODO: добавить резку текста по длине строки
    			// TODO: работа со скриптами и стилями нужна?
                $s= preg_replace(
    				array('/"/','/\\\\/','/\\s\\s+/','/^\\s+|\\s+$/m'),
    				array('\"','\\\\',' ',''),
    		 		$s);
                break;
    		case 'css2js':
    			// выводим css для вставки в изображение строки с двойными кавычками.
                $s= preg_replace(
    				array('/\/\*.*\*\//ms','/\/\/.*?/','/"/','/\\\\/','/\\s\\s+/','/^\\s+|\\s+$/m'),
    				array('',' ','\"','\\\\',' ',''),
    				$s);
                break;
    	}
        }
    	return $s;
    }

}

function point_start($point_name) {
    return POINT::start($point_name);
}
function point_finish(){
    POINT::finish();
}
function point($point_name, $filter=''){
    return POINT::get($point_name, $filter);
}

function insert_point($point_name){
	echo "/****** point $point_name */\r\n".POINT::get($point_name)."/****finish point $point_name *//*\r\n";
}