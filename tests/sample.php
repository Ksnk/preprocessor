<?php
ini_set('include_path',
  ini_get('include_path')
  .';..' // windows only include!
);
include_once ("wiki.ext.php");

function pps(&$x,$default=''){if(empty($x))return $default; else return $x;}

  /**
   * ������������ ������� � ���������� ������ ������
   */
  function wikitxt($data,$fmt='html'){
    return wiki_parcer::convert($data,$fmt);
  }
  
  /**
   * ��������� wiki �������� � �������������
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
$data="��� ������������ ����� ������� �������� �� php � ����� ����������� � CLI
������. ���������� �������� �������� ����� ������� ������������� � ant-�������
��� Eclips'� ��� � [[�����-������]] __������__ ������. ��� ������� ������� �� ��������� ���
���������
  /usr/local/php5/php.exe -f /preprocessor/preprocessor.php /Dtarget=release /Ddst=build/\$target config.xml
�������� /D - �������� ���������� c ������� target � dst ��������������. �����
��� ���������� ����� ����� ������������.";

$fmt=pps($_GET['fmt'],'html');
if($fmt=='html')   
	echo wikitxt($data);
else {
	echo "<pre>";
	echo wikitxt($data,'txt');
	echo "<pre>";
}	

  