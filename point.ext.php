<?php
/**
 * POINTS (MACRO OOP) mechanism for PHP preperocessor
 * <%=point('hat','comment');%>
 */

$points=array();
$cur_point='';
$ob_count=0;

/**
 * стартовая метка "точки". Весь вывод после этого оператора попадает в
 * именованный буфер $point_name
 * @param string $point_name
 * @return void
 */

function point_start($point_name){
	global $ob_count,$cur_point,$points;
	//echo "ob_start\n\r";
	point_finish();
	$cur_point=$point_name;
	ob_start();
	$ob_count++;
}

/**
 * финишная метка точки. После этого оператора точка пополняется.
 * @return void
 */
function point_finish(){
	global $ob_count,$cur_point,$points;
	//echo "ob_finish\n\r";
	if($ob_count==0) return;
	$ob_count--;
	//$contents=preg_replace('/^\s+|\s+$/','',ob_get_contents()); 
	$contents=ob_get_contents();
	ob_end_clean();
	//echo $contents;
	if (empty($contents)) return;
	if(!isset($points[$cur_point]))
	  $points[$cur_point]=array();
	$points[$cur_point][]=preg_replace('/^\s+|^\*\/|\s+$|\/\*$/','',$contents);
}

/**
 * вывод содержимого точки. При выводе применяются фильтры, которые позволяют вставляться
 * в комментарии (пустой фильтр), как часть комментария (фильтр comment), с текстовой обработкой (wiki)
 * @param $point_name
 * @param string $filter
 * @return mixed|string
 */
function point($point_name, $filter=''){
	global $ob_count,$cur_point,$points,$preprocessor;
	//echo "insert_point $point_name */\n\r";
	$s='';
	if(isset($points[$point_name]))
	  $s= join($points[$point_name],"\r\n");
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
            $s=iconv('UTF-8', 'CP1251',$s);
			return wiki_parcer::convert($s,'txt');
			break;
		case 'wiki-html':
			include_once("wiki.ext.php");
			return wiki_parcer::convert($s,'html');
			break;
        case 'line_comment':
            // перед строкой стоит строковый комменарий - выводим с новой строки
            return ' ----point::'.$point_name."----\r\n".$s."\r\n";
        case 'everyline_comment':
             // каждая строка начинается с комментария //
             return trim(preg_replace(
				array('/\n/'),
				array("\n// "),
				$s))."\r\n";
			break;
        case 'tplcomment':
             // каждая строка начинается с комментария ##
             return trim(preg_replace(
				array('/\n/'),
				array("\n## "),
				$s))."\r\n";
			break;
		case 'jscomment':
			return trim(preg_replace(
				array('/\n/'),
				array("\n * "),
				$s))."\r\n";
			break;
		case 'php_comment':	
			// выводим php код в окружении закрывающего - открывающего комментария
			return '*/
			'.$s.'
			/*';
		case 'html2js':
			// выводим html для вставки в изображение строки с двойными кавычками.
			// TODO: добавить резку текста по длине строки 
			// TODO: работа со скриптами и стилями нужна? 
			return preg_replace(
				array('/"/','/\\\\/','/\\s\\s+/','/^\\s+|\\s+$/m'),
				array('\"','\\\\',' ',''),
		 		$s);
		case 'css2js':
			// выводим css для вставки в изображение строки с двойными кавычками.
			return preg_replace(
				array('/\/\*.*\*\//ms','/\/\/.*?/','/"/','/\\\\/','/\\s\\s+/','/^\\s+|\\s+$/m'),
				array('',' ','\"','\\\\',' ',''),
				$s);
	}
	return $s;  
}

function insert_point($point_name){
	echo "/****** point $point_name */\r\n".point($point_name)."/****finish point $point_name *//*\r\n";
}
