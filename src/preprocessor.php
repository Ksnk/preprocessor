<?php
/**
 * to call preprocesor - type   php -f preprocessor.php file_name
 *
 * <%=POINT::get('hat','comment');



%>
 */
$dir = dirname(__FILE__);
include_once ($dir . DIRECTORY_SEPARATOR . "preprocessor.class.php");
include_once ($dir . DIRECTORY_SEPARATOR . "point.ext.php");

date_default_timezone_set('Europe/Moscow');
/**
 * @param $p
 * @param $def
 * @return mixed
 * @tutorial preprocessor.pkg
 */
function pps(&$p, $def)
{
    return empty($p) ? $def : $p;
}

$preprocessor = preprocessor::instance();

foreach ($_ENV as $k => $v) {
    $preprocessor->export('env_' . $k, $v);
}
;

echo '<%=$version%>

';
$arg = '';
for ($i = 1; $i < $argc; $i++)
    $arg .= ' ' . $argv[$i];
//echo $arg;

while (!empty($arg)) {
    //$preprocessor->debug($arg);
    if (preg_match('#^/force(?:\s|\=\'([^\']+)\')#', $arg, $m)) {
        $time = false;
        if (!empty($m[1])) {
            $time = strtotime($m[1]);
            if (!$time && $m[1] != 'force') {
                $preprocessor->debug('wrong date "' . $m[1] . '"');
            }
        }
        if (!$time) {
            $time = time();
        }
        $preprocessor->cfg_time($time);
        $arg = trim(substr($arg, strlen($m[0])));
    } elseif (preg_match('/^\s*\/P\=?(\S+)/', $arg, $m)) {
        if (is_readable($m[1])) {
            foreach (file($m[1]) as $v) {
                if (preg_match('/^(?:\;.*|\#.*|([^=]+)=(.*))$/', $v, $mm)) {
                    if (!empty($mm[1])) {
                        $preprocessor->export(trim($mm[1]), trim($mm[2]));
                    }
                }
            }
        } else {
            $preprocessor->log(1, 'no such a file "' . $m[1] . '"');
        }
        $arg = trim(substr($arg, strlen($m[0])));
    } elseif (preg_match('/^\s*\/D([\.\w]+)\=\'([^\']+)\'/', $arg, $m)) {
        $preprocessor->export($m[1], $m[2]);
        //echo 'export '.$m[1].'='.$m[2];
        $arg = trim(substr($arg, strlen($m[0])));
    } elseif (preg_match('/^\s*\/D([\.\w]+)\=(\S+)/', $arg, $m)) {
        if (!empty($m[2]))
            $preprocessor->export($m[1], $m[2]);
        else
            $preprocessor->export($m[1], $m[3]);
        $arg = trim(substr($arg, strlen($m[0])));
    } else if (preg_match('~^([\.\\/\w]+)~', $arg, $m)) { //is_file($argv[$i])) {
        $arg = trim(substr($arg, strlen($m[0])));
        if (is_file($m[0])) {
            $arg1 = pathinfo($m[0]);
            if ($arg1['extension'] == 'xml') {
                $preprocessor->log(1, "making " . $m[0] . "\n");
                $xmlstr = $m[0];

            } else {
                $xmlstr = <<<XML
<?xml version='1.0' standalone='yes'?>
<config>
	<files dstdir="build">
		<file>${argv[$i]}</file>
	</files>
</config>
XML;

            }
            $preprocessor->xml_read($xmlstr);
        } else {
            echo 'fail! wrong parameter-' . $m[0];
            exit;
        }
    } else {
        echo 'fail! wrong parameter-' . $arg;
        exit;
    }
}
$preprocessor->process();

?>