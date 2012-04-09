<?php
/**
 * POINTS (MACRO OOP) mechanism for PHP preperocessor
 * <%=POINT::get('hat','comment');



%>
 */

class POINT
{
    static private
        $points = array(),
        $cur_point = '',
        $ob_count = 0,

        $point_stat = array();
    static public
        $eval_src = '',
        $eval_idx = 0;

    static private
        $placeholder = array(),
        $curplaceloder = 0;

    /**
     * Добавить в "точку" содержимое файла с обработкой препроцессором
     * именованный буфер $point_name
     * @param string $point_name
     * @param $filename
     * @return void
     */
    static function file($point_name, $filename)
    {
        self::inline($point_name, file_get_contents($filename));
    }

    static function clear()
    {
        self::$points = array();
        self::$point_stat = array();
    }

    /**
     * Добавить в "точку" содержимое переменной с обработкой препроцессором
     * именованный буфер $point_name
     * @param $name string $point_name
     * @param $contents
     * @return void
     */
    static function inline($name, $contents)
    {
        if (empty($contents)) return;
        if (!isset(self::$points[$name]))
            self::$points[$name] = array();
        self::$eval_idx++;
        //  $GLOBALS['preprocessor']->log(2,'try to hold "'.$name.'" file "'.substr(self::$eval_src,0,80).'" '.self::$eval_idx."\n");

        if (!empty(self::$eval_src) && isset(self::$point_stat[self::$eval_src . '_' . self::$eval_idx])) {
            preprocessor::log(2, 'second try to hold "' . $name . '" file "' . substr(self::$eval_src, 0, 80) . '" '
                . self::$eval_idx . "\n" . print_r(self::$points, true));
            return;
        }
        self::$point_stat[self::$eval_src . '_' . self::$eval_idx] = true;
        self::$points[$name][] = preg_replace('/^\s+|^\*\/|\s+$|\/\*$/', '', $contents);
    }

    /**
     * стартовая метка "точки". Весь вывод после этого оператора попадает в
     * именованный буфер $point_name
     * @param string $point_name
     * @return void
     */
    static function start($point_name)
    {
        self::finish();
        self::$cur_point = $point_name;
        ob_start();
        self::$ob_count++;
    }

    /**
     * финишная метка точки. После этого оператора точка пополняется.
     * @return void
     */
    static function finish()
    {
        if (self::$ob_count == 0) return;
        self::$ob_count--;
        //$contents=preg_replace('/^\s+|\s+$/','',ob_get_contents());
        $contents = ob_get_contents();
        ob_end_clean();
        //echo $contents;
        self::inline(self::$cur_point, $contents);
    }

    static function insert($point_name)
    {
        echo "/****** point $point_name */\r\n" . POINT::get($point_name) . "/****finish point $point_name *//*\r\n";
    }

    static function _replace($m)
    {
        self::$placeholder[self::$curplaceloder] = $m[2];
        return $m[1] . '@' . self::$curplaceloder++ . '@' . $m[3];
    }

    static function _replace1($m)
    {
        self::$placeholder[self::$curplaceloder] = $m[0];
        return '@' . self::$curplaceloder++ . '@';
    }

    /**
     * @XXX@ заменяем на placeholder
     * @static
     * @param $m
     * @return string
     */
    static function _return($m)
    {
        return self::$placeholder[$m[1]];
    }

    /**
     * вывод содержимого точки. При выводе применяются фильтры, которые позволяют вставляться
     * в комментарии (пустой фильтр), как часть комментария (фильтр comment), с текстовой обработкой (wiki)
     * @param $point_name
     * @param string $filter
     * @return mixed|string
     */
    static function get($point_name, $filters = '')
    {
        global $preprocessor;
        //echo "insert_point $point_name */\n\r";
        $s = '';
        if (isset(self::$points[$point_name]))
            $s = join(self::$points[$point_name], "\r\n");

        foreach (explode('|', $filters) as $filter) {
            if ($filter == '' || $filter == 'comment') {
                $ss = $preprocessor->obget();
                if (preg_match('~(\s+\*)\s*$|(/\*+)\s*$|(//)\s*$|(\#\#\s*)$~s', $ss, $m)) {
                    if (!empty($m[1])) {
                        // javascript comment
                        $filter = $filter == 'comment' ? 'jscomment' : 'php_comment';
                    } elseif (!empty($m[2])) {
                        // javascript comment
                        $filter = $filter == 'comment' ? 'jscomment' : 'php_comment';
                    } elseif (!empty($m[3])) {
                        // javascript comment
                        $filter = $filter == 'comment' ? 'everyline_comment' : 'line_comment';
                    } elseif (!empty($m[4])) {
                        $filter = $filter == 'comment' ? 'tplcomment' : 'line_comment';
                    }
                }
                preprocessor::log(4, 'point: ' . $point_name . ' filter :"' . $filter . '"' . "\n");
            }
            switch ($filter) {
                case 'wiki-txt':
                    include_once("wiki.ext.php");
                    $s = iconv('UTF-8', 'CP1251//IGNORE', $s);
                    $s = wiki_parcer::convert($s, 'txt');
                    break;
                case 'markdown-html':
                    $dir = dirname(__FILE__);
                    include_once $dir . DIRECTORY_SEPARATOR . 'markdown.filter/markdown.php';

                    $s = Markdown($s);
                    break;
                case 'mark-txt':
                    $dir = dirname(__FILE__);
                    include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/MlObject.php');
                    include_once $dir . DIRECTORY_SEPARATOR . 'markdown.filter/markdown2ml.php';
                    include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/ml2text.php');

                    $reader = new reader_MARKDOWN();
                    $writer = new writer_Text();
                    $s = $writer->writeText($reader->parseMarkdown($s));
                    break;
                case 'markdown-txt':
                    $dir = dirname(__FILE__);
                    //return 'xxx';
                    include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/MlObject.php');
                    include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/html2ml.php');
                    include_once($dir . DIRECTORY_SEPARATOR . 'markdown.filter/ml2text.php');
                    include_once $dir . DIRECTORY_SEPARATOR . 'markdown.filter/markdown.php';
                    $txt = '';
                    // try{
                    $reader = new reader_HTML();
                    $writer = new writer_Text();
                    $html = Markdown($s);
                    $ml = $reader->parseHtml($html);
                    $txt = $writer->writeText($ml);
                    // } catch (Exception $e){
                    //     echo($e->getMessage());
                    // }
                    $s = $txt;
                    break;
                case 'wiki-html':
                    include_once("wiki.ext.php");
                    $s = wiki_parcer::convert($s, 'html');
                    break;
                case 'line_comment':
                    // перед строкой стоит строковый комменарий - выводим с новой строки
                    $s = ' ----point::' . $point_name . "----\r\n" . $s . "\r\n";
                    break;
                case 'everyline_comment':
                    // каждая строка начинается с комментария //
                    $s = trim(preg_replace(
                        array('/\n/'),
                        array("\n// "),
                        $s)) . "\r\n";
                    break;
                case 'tplcomment':
                    // каждая строка начинается с комментария ##
                    $s = trim(preg_replace(
                        array('/\n/'),
                        array("\n## "),
                        $s)) . "\r\n";
                    break;
                case 'jscomment':
                    $s = trim(preg_replace(
                        array('/\n/'),
                        array("\n * "),
                        $s)) . "\r\n";
                    break;
                case 'php_comment':
                    // выводим php код в окружении закрывающего - открывающего комментария
                    $s = '*/
' . $s . '
/*';
                    break;
                case 'html2js':
                    // выводим html для вставки в изображение строки с двойными кавычками.
                    // $scripts
                    // коррекция NL
                    $s = str_replace(array("\r\n", "\r"), array("\n", "\n"), $s);
                    // чистим шаблонные вставки
                    // чистим скрипты
                    $start = self::$curplaceloder;
                    $s = preg_replace_callback('#(<script[^>]*>)(.*?)(</script[^>]*>)#is', array('POINT', '_replace'), $s);
                    for (; $start < self::$curplaceloder; $start++) {
                        self::$placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                            , array('POINT', '_return'),
                            preg_replace(array('#//.*$#m', '#/\*.*?\*/#s', "/\n/", '/"/', '/\s+/', '#\s*(\\\\n\s*)+#')
                                , array("", "", '\n', '\\"', ' ', '\n'),
                                self::$placeholder[$start]
                            ));
                    }
                    //стили
                    $start = self::$curplaceloder;
                    $s = preg_replace_callback('#(<style[^>]*>)(.*?)(</style[^>]*>)#is', array('POINT', '_replace'), $s);
                    for (; $start < self::$curplaceloder; $start++) {
                        self::$placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                            , array('POINT', '_return'), preg_replace('#\s+#', " ",
                                preg_replace('#/\*.*?\*/#s', "",
                                    self::$placeholder[$start])
                            ));
                    }
                    // условные комментарии
                    $s = preg_replace_callback('#(<!--\[)(.*?)(\]-->)#is', array('POINT', '_replace'), $s);
                    // пробелы
                    $s = preg_replace(
                        array('/<!--.*?-->/s', '/"/', '/\\\\/', '/\s+/'
                        , '#\s*(<|</)(body|div|br|script|style|option|dd|dt|dl|iframe)([^<]*>)\s*#is'),
                        array('', '\"', '\\\\', ' ', '\1\2\3'),
                        $s);
                    $s = preg_replace_callback('#@(\d+)@#', array('POINT', '_return'), $s);
                    break;
                case 'tplcompress':
                    // выводим html для вставки в изображение строки с двойными кавычками.
                    // $scripts
                    // коррекция NL
                    $s = str_replace(array("\r\n", "\r"), array("\n", "\n"), $s);
                    // чистим шаблонные вставки
                    // $start = self::$curplaceloder;
                    $s = preg_replace_callback('~^##.*?\n~im', array('POINT', '_replace1'), $s);
                    $s = preg_replace_callback('~{{.*?}}|{%.*?%}|{#.*?#}~is', array('POINT', '_replace1'), $s);
                    // echo $s;
                    // чистим скрипты
                    $start = self::$curplaceloder;
                    $s = preg_replace_callback('#(<script[^>]*>)(.*?)(</script[^>]*>)#is', array('POINT', '_replace'), $s);
                    for (; $start < self::$curplaceloder; $start++) {
                        self::$placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                            , array('POINT', '_return'),
                            preg_replace(array('#//.*?$#m', '#\n#', '#/\*.*?\*/#s', '/\s+/', '/@0@/', '#\s*(\n\s*)+#')
                                , array("", "@0@", "", ' ', "\n", "\n"),
                                self::$placeholder[$start]
                            ));
                    }
                    //стили
                    $start = self::$curplaceloder;
                    $s = preg_replace_callback('#(<style[^>]*>)(.*?)(</style[^>]*>)#is', array('POINT', '_replace'), $s);
                    for (; $start < self::$curplaceloder; $start++) {
                        self::$placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                            , array('POINT', '_return'), preg_replace('#\s+#', " ",
                                preg_replace('#/\*.*?\*/#s', "",
                                    self::$placeholder[$start])
                            ));
                    }
                    // условные комментарии
                    $s = preg_replace_callback('#(<!--\[)(.*?)(\]-->)#is', array('POINT', '_replace'), $s);
                    // пробелы
                    $s = preg_replace(
                        array('/<!--.*?-->/s', '/\s+/'
                        , '#\s*(<|</)(body|div|br|script|style|option|dd|dt|dl|iframe)([^<]*>)\s*#is'),
                        array('', ' ', '\1\2\3'),
                        $s);
                    $s = preg_replace_callback('#@(\d+)@#', array('POINT', '_return'), $s);
                    break;
                case 'css2js':
                    // выводим css для вставки в изображение строки с двойными кавычками.
                    $s = preg_replace(
                        array('/<!--.*?-->/s', '/\/\*.*\*\//s', '/\/\/.*?/', '/"/', '/\\\\/', '/\\s\\s+/', '/^\\s+|\\s+$/m'),
                        array('', '', ' ', '\"', '\\\\', ' ', ''),
                        $s);
                    break;
            }
        }
        return $s;
    }

}

function point_start($point_name)
{
    return POINT::start($point_name);
}

function point_finish()
{
    POINT::finish();
}

function point($point_name, $filter = '')
{
    return POINT::get($point_name, $filter);
}

function insert_point($point_name)
{
    echo "/****** point $point_name */\r\n" . POINT::get($point_name) . "/****finish point $point_name *//*\r\n";
}