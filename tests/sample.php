<?php
ini_set('include_path',
  ini_get('include_path')
  .';..' // windows only include!
);
include_once ("wiki.ext.php");

function pps(&$x,$default=''){if(empty($x))return $default; else return $x;}

  /**
   * тестирование шаблона с генерацией нового класса
   */
  function wikitxt($data,$fmt='html'){
    return wiki_parcer::convert($data,$fmt);
  }
  
  /**
   * тестируем wiki разметку в препроцессоре
   */
  if (isset($_GET['file']))
  	$data=file_get_contents($_GET['file']);
  else	
    
$data2='
==Title1

  Just to
  say __hello__
  
=====Title1

Hello --world--!

* one
* two @@__strilke__@@

* one

just a sign
        
        ';
$data="—ам препроцессор будет набором скриптов на php и будет запускатьс€ в CLI
режиме. ƒостаточно несложно включить такой вариант использовани€ в ant-сборщик
дл€ Eclips'а или в [[какой-нибудь]] __другой__ билдер. ѕри запуске скрипта мы передадим ему
параметры
  /usr/local/php5/php.exe -f /preprocessor/preprocessor.php /Dtarget=release /Ddst=build/\$target config.xml
параметр /D - описание переменных c именами target и dst соответственно. ѕотом
эти переменные можно будет использовать.";

$fmt=pps($_GET['fmt'],'html');
if($fmt=='html')   
	echo wikitxt($data);
else {
	echo "<pre>";
	echo wikitxt($data,'txt');
	echo "<pre>";
}	

  