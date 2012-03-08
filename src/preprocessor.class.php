<?php
/**
 * main preprocessor class + xml reader-parcer
 * <%=POINT::get('hat','comment');






%>
 */
$stderr = fopen('php://stderr', 'w');

class preprocessor
{
    var $obcnt = 0,
        $debug_str = '',
        //$result='',
        $srcfile = '',
        $logLevel = 2,
        $logs = array('');

    static function instance()
    {
        if (!isset($GLOBALS['preprocessor']))
            $GLOBALS['preprocessor'] = new preprocessor();
        return $GLOBALS['preprocessor'];
    }

    protected $options = array();

    /**
     * установка-получение параметров с сохранением предыдущего и откатом
     * @usage opt('a','b','c') - получить список параметров `list($a,$b,$c)=$this->opt('a','b','c')жё
     * @usage opt('a') - вернуть значение параметра 'a'
     * @usage opt(array('a'=>1,'b'=>2)) - установить параметры a,b и запомнить предыдущее состояние
     * @usage opt() - восстановить предыдущее состояние
     * @param $attr
     * @param null $value
     * @return  mixed
     */
    protected function opt($attr = null, $value = null)
    {
        static $save = array();
        if (is_null($attr)) {
            if (count($save) > 0)
                $this->options = array_pop($save);
        }
        else if (is_object($attr) || is_array($attr)) {
            array_push($save, $this->options);
            if (!empty($attr)) {
                $attr = $this->handle_attr($attr);
                $this->options = array_merge($this->options, $attr);
            }
        }
        elseif (is_null($value)) {
            if (!isset($this->options[$attr]))
                return '';
            else
                return $this->options[$attr];
        } else {
            $res = array();
            foreach (func_get_args() as $v) {
                if (isset($this->options[$v]))
                    $res[] = $this->options[$v];
                else
                    $res[] = null;
            }
            return $res;
        }
        return null;
    }

    /**
     * корректировка параметров
     * @param $attr
     * @return mixed|string
     */

    function handle_attr($attr)
    {
        $result = array();
        foreach ($attr as $name => $v) {
            $name = $this->evd($name);
            $v = $this->evd($v);
            switch ($name) {
                case 'dstdir':
                case 'dir': // наращиваем соответствующий параметр на это значение
                    if (preg_match('#[^<>%]#', $v)) {
                        $old = rtrim($this->opt($name), '/');
                        if (!empty($old) && !empty($v))
                            $v = $this->opt($name) . '/' . $v;
                    }
                    break;
                case 'depend':
                    $old = rtrim($this->opt($name), ';');
                    if (!empty($old) && !empty($v))
                        $v = $this->opt($name) . ';' . $v;
                    break;
            }
            $result[$name] = $v;
        }
        $this->debug($result);
        return $result;
    }

    /**
     * array with variables exported from outer space
     * (xml, command line and so on);
     */
    public $exported_var = array();

    /**
     * to hold the modification time of all evaluated files we need
     * to store the last time source files was modified.
     */
    public function cfg_time($n = null)
    {
        static $cfg_time = 0;
        if (!is_null($n)) {
            if (is_file($n))
                $cfg_time = max($cfg_time, filemtime($n));
            else
                $cfg_time = max($cfg_time, $n);
        }
        return $cfg_time;
    }

    /**
     * here we can store our files list, Ну да!
     */
    private $store = array();

    /**
     * store setter  SRC - DST - ACTION
     * @param $src - from
     * @param $dst - to
     * @param $act - what to do (eval||copy)
     */
    public function newpair($src, $dst = '', $act = 'eval', $par = '')
    {
        if (empty($par)) $par = array();
        $dst = preg_replace('#(\\/)\.\1#', '\1', $dst);
        //  $this->log(1,print_r(array($src,$dst,$act,$par),true));

        $this->store[] = array($src, $dst, $act, $par);
    }

    /**
     * store getter
     * @return mixed
     */
    private function getpair()
    {
        return array_shift($this->store);
    }

    /**
     * just stollen and slightly rewriten from
     * http://ru2.php.net/manual/en/function.touch.php#88028
     * big thanks to author!
     * @param $file
     * @param $time
     * @return bool
     */
    function betouch($file, $time)
    {
        if (preg_match('/<>/', $file)) return false;
        if (touch($file, $time)) {
            clearstatcache();
            $stored_mtime = $time + $time - filemtime($file);
            if ($time == $stored_mtime) {
                return true;
            } else {
                return touch($file, $stored_mtime);
            }
        }
        return false;
    }

    /**
     * eval string with dollar sign, internal function
     */
    private function tmp_callback($m)
    {
        if (isset($m[2]) && array_key_exists($m[2], $this->exported_var))
            return $this->exported_var[$m[2]];
        elseif (isset($m[1]) && array_key_exists($m[1], $this->exported_var))
            return $this->exported_var[$m[1]];
        else
            return $m[0];
    }

    /**
     * eval string with dollar sign, with default value (just for fun)...
     * variables stored into 'exported_var' array.
     * @param $s
     * @param string $default
     * @return mixed|string
     */
    private function evd($s, $default = '')
    {
        if (empty($s)) return $default;
        if (strpos($s, '$') === FALSE) return (string)$s;

        return preg_replace_callback('/\$(?:(\w*)|\{([\w\.\[\]]*)\})/', array($this, 'tmp_callback'), $s);
    }

    /**
     * some oop handlers to simplify work with new tags
     */

    /**
     * handle VAR tag.
     * @param xmlstring $files
     * @example <var name='hello' default='ok'/>
     *     to assing Ok to hello variable if no assigned awile
     * @example <var name='hello'>Ok</var> - to assing Ok to hello variable
     */
    function handle_var(&$files)
    {
        if ((string)$files['name'] == '')
            $this->log(0, 'XML: there is no NAME parameter of VAR tag.'); // faked variable
        if ((string)$files == "") { // just assign a value if no values was a while
            if (isset($this->exported_var[(string)$files['name']]))
                return;
        }
        $val = (string)$files;
        if (empty($val))
            $val = (string)$files['default'];
        if (!empty($val))
            $this->export((string)$files['name'], $val);
    }

    /**
     * handle IMPORT tag.
     * @param xmlstring $files
     * @example <import name='/common/cms/config.xml'/>
     */
    function handle_import(&$files)
    {
        if ((string)$files['name'] == '')
            $this->log(0, 'XML: there is no NAME parameter of IMPORT tag.'); // faked variable
        $this->xml_read((string)$files['name']);
    }

    private function obend()
    {
        ob_end_clean();
        $this->obcnt--;
    }

    public function obget()
    {
        $res = ob_get_contents();
        return $res;
    }

    private function obstart()
    {
        $this->obcnt++;
        ob_start();
    }

    public function debug()
    {
        $na = func_num_args();
        if ($na > 0) {
            for ($i = 0; $i < $na; $i++) {
                $mess = func_get_arg($i);
                $this->log(4, $mess);
            }
        }
    }

    static public function log($level = -1)
    {
        $preprocessor = preprocessor::instance();
        if ($preprocessor->logLevel < $level) return;
        $na = func_num_args();
        if ($na > 1) {
            for ($i = 1; $i < $na; $i++) {
                $v = func_get_arg($i);
                if (is_array($v))
                    $v = print_r($v, true);
                $preprocessor->logs[] = array($level, $v);
            }
        }
        //if($na==0){ print_r($this->logs);}
        if (($preprocessor->obcnt == 0) && count($preprocessor->logs) > 0) {
            foreach ($preprocessor->logs as $v) {
                if (!empty($v[1]))
                    echo $v[1];
            }
            $preprocessor->logs = array();
        }

    }

    /**
     * поддержка рекурсивной маски **
     * @param $mask
     * @param $arr
     * @param $mask2
     */
    private function recstar($mask, &$arr, $mask2)
    {
        $x = glob($mask . '*', GLOB_MARK + GLOB_ONLYDIR);
        foreach ($x as $a) {
            $arr[] = $a . $mask2;
            $this->recstar($a, $arr, $mask2);
        }
    }

    private function findByMask($mask)
    {
        $arr = array();
        $xx = explode('**', $mask);
        if (count($xx) == 1) {
            $arr[] = $mask;
        } else if (count($xx) == 2) {
            $this->recstar($xx[0], $arr, rtrim($xx[1], '\\/'));
        } else {
            $this->log(1, sprintf('Wrong mask "%s". Only one recursion per mask allowed.' . "\n", $mask));
        }
        $files = array();
        foreach ($arr as $m)
            $files = array_merge($files, glob($m));
        return array_unique($files);
    }


    /**
     * read xml file and parse information.
     * Look at path function for miracles
     * @param $xml
     */
    public function xml_read($xml, $insertbefore = false)
    {
        $this->debug('xml_read:', getcwd());
        $oldcwd = getcwd();
        if (is_file($xml)) {
            chdir(dirname($xml));
            $this->debug('file:', $xml);
            $this->cfg_time($xml);
            $config = simplexml_load_file($xml);
        } else {
            $config = new SimpleXMLElement($xml);
        }
        if ($insertbefore) {
            $sav = $this->store;
            $this->store = array();
        }
        $this->opt($config->attributes());
        foreach ($config->children() as $files) {
            $this->opt($files->attributes());
            $name = 'handle_' . strtolower($files->getName());
            if (method_exists($this, $name)) {
                call_user_func_array(array($this, $name), array(&$files));
            } else
                if ($files->getName() == 'files') {
                    foreach ($files->children() as $file) {
                        $this->opt($file->attributes());
                        $dst = $this->opt('dstdir');
                        $attributes = array();
                        foreach ($file->attributes() as $k => $v) {
                            $attributes[$k] = (string)$v;
                        }
                        $attributes['code'] = $this->opt('code');
                        $attributes['force'] = $this->opt('force');
                        $depend = $this->opt('depend');
                        if (!empty($depend)) {
                            $depends = explode(';', $depend);
                            $xtime = 0;
                            foreach ($depends as $d) {
                                $x = strtotime($d);
                                if ($x && $x > 0) {
                                    $xtime = max($x, $xtime);
                                } else {
                                    foreach ($this->findByMask($d) as $a) {
                                        $xtime = max($xtime, filemtime($a));
                                    }
                                }
                            }
                            $attributes['xtime'] = $xtime;
                        }

                        $str_file = $this->evd((string)$file);
                        if (!empty($dst) && !preg_match('#[^\w\.\\/]#', $str_file) && dirname($str_file) != '') {
                            $dst .= '/' . dirname($str_file);
                        }
                        if ($file->getName() == 'echo') {
                            list($dst, $name) = $this->opt('dstdir', 'name');
                            if (!empty($dst)) {
                                if (empty($name) && preg_match('#[\w\.\\\/#', $file)) {
                                    $name = basename($file);
                                }
                                $dst .= '/' . $name;
                            }
                            $this->newpair(
                                (string)$file,
                                $dst,
                                $file->getName()
                                , $attributes);
                        } else {
                            $dir = $this->opt('dir');
                            if (!empty($dir)) {
                                $dir = rtrim($dir, '\\/') . '/';
                            }
                            if (strpos($dir, '*') !== FALSE) {
                                $this->log(1, sprintf("`dir` parameter can't contain `*` (%s) \n", $dir));
                            }
                            // $this->log(2,$dir.(string)$file);
                            $pdir = '';
                            foreach ($this->findByMask($dir . $str_file) as $a) {
                                list($dst, $name) = $this->opt('dstdir', 'name');
                                $pdir = dirname(substr($a, strlen($dir)));
                                if (!empty($pdir)) $pdir .= '/';

                                if (!empty($dst)) {
                                    if (empty($name)) {
                                        $name = basename($a);
                                    }
                                    $dst .= '/' . $pdir . $name;
                                }
                                $this->newpair(
                                    realpath($a),
                                    $dst,
                                    $file->getName()
                                    , $attributes);
                            }
                        }

                        $this->opt();
                    }
                }
            $this->opt();
        }
        $this->opt();
        if ($insertbefore) {
            $this->store = array_merge($sav, $this->store);
        }
        //$this->debug(print_r($this,true));
        chdir($oldcwd);
    }

    /**
     * export variable from outer space (command line)
     * @param string $var
     * @param unknown_type $val
     */
    public function export($var, $val)
    {
        $this->exported_var[$var] = $val;
    }

    /**
     * prepare the file to be evaluated
     *
     * read and switch php-tags
     * @param $src - file name
     */
    private function prep_file($src, $isfile = true)
    {
        if ($isfile) {
            if (!is_file($src)) return '';
            $s = file_get_contents($src);
            if (strpos($s, '<' . '%') === FALSE) return null;
        } else {
            $s = $src;
        }
        $s = str_replace(
            array('<?', '?>', '<' . '%=', '<' . '%', '%' . '>'),
            array('<' . '@', '@' . '>', '<' . '?php echo ', '<' . '?php ', '?' . '>'), $s
        );
        $this->obstart();
        return '?' . '>' . $s;
    }

    private function decode(&$s, $code)
    {
        if (!empty($code)) { // iconv conversion
            list($from, $to) = explode(':', $code . ':');
            if (empty($from)) {
                $from = mb_detect_encoding($s);
            }
            $this->debug("\n convert from '", $from, "' to '", $to, "'\n");
            if (!empty($from) && !empty($to))
                $s = iconv($from, $to . '//IGNORE', $s);
        }
    }

    /**
     * switch php tags back
     * @param $dst - file to store evaluated result
     */
    private function post_process($dst = '', $time, $code = null)
    {

        $s = $this->obget();
        $s = str_replace( // + final linefeed correcion
        // replace LF with intel LF,
        // all MAC's LF replaced on Intel
        // Replace Intel LF with Windows LF for my editor glitched with different  :(
            array('<' . '@', '@' . '>', '%/' . '>', '<' . '/%', "\r\n", "\r", "\n"),
            array('<' . '?', '?' . '>', '%' . '>', '<' . '%', "\n", "\n", "\r\n"), $s
        );
        $this->obend();
        if (!empty($dst)) {
            $x = pathinfo($dst);
            // $this->log(1,$dst,' ',$x['dirname'],"\n");
            if (!is_dir($x['dirname'])) mkdir($x['dirname'], 0777, true);
            if (is_file($dst))
                $this->debug(array(filemtime($dst), max($time, $this->cfg_time()), true));
            if (!is_file($dst) || (filemtime($dst) < max($time, $this->cfg_time()))) {
                $this->decode($s, $code);
                // удаляем пустые комментарии - последствия корявой обработки вставки секций
                file_put_contents($dst, preg_replace(array('~^\s*/\*\*/\s*$~m', '~\s*/\*\s*\*/~'), array('', ''),
                    str_replace("\xEF\xBB\xBF", '', trim($s))
                ));
                $this->betouch($dst, max($time, $this->cfg_time()));
                return true;
            }
        }
        return false;
    }


    public function _handleNotice($errno, $errstr, $errfile, $errline)
    {
        if (error_reporting()) return;
        $trace = debug_backtrace();
        array_shift($trace);
        printf('notice %s,%s,%s,%s,%s'
            , $errno, $errstr, $errfile, $errline, print_r($trace, true));
    }

    public function _handleFatal()
    {
        $error = error_get_last();
        if (!is_array($error) || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return;
        }
        //if (!empty($GLOBAL['evaluated']))
        $this->log();
        $error['file'] = $this->srcfile;

        printf('fatal %s,%s,%s,%s'
            , $error['type'], $error['message'], $error['file'], $error['line']);
    }

    /**
     * execute all file-pairs in a row
     */
    public function process()
    {
        register_shutdown_function(array($this, '_handleFatal'));
        if (!empty($this->exported_var))
            extract($this->exported_var);
        $___total_cnt = 0;
        $___all_cnt = 0;
        while ($___m = $this->getpair()) {
            $error = error_get_last();
            if (is_array($error)) {
                break;
            }
            //$this->debug('xxx-'.print_r($___m,true));
            $srcfile = $___m[0];
            $dstfile = $___m[1];
            $___all_cnt++;
            switch ($___m[2]) {
                case 'eval':
                case 'file':
                case 'echo':
                    $filemtime = 0;
                    if (is_file($srcfile)) {
                        $this->srcfile = $srcfile;
                        $filemtime = filemtime($srcfile);
                    } else {
                        $this->srcfile = '<-string->';
                    }
                    if (!empty($___m[3]['xtime'])) {
                        $filemtime = max($filemtime, $___m[3]['xtime']);
                    }
                    if (!empty($___m[3]))
                        if (!empty($___m[3]['force']))
                            $filemtime = time();
                    $___s = $this->prep_file($srcfile, $___m[2] != 'echo');
                    if ($___m[2] == 'echo')
                        $srcfile = '';
                    if (!is_null($___s)) {
                        $oldcwd = getcwd();
                        if (is_file($srcfile)) {
                            chdir(dirname($srcfile)); //$this->debug('xml_read:',getcwd());
                        }
                        POINT::$eval_src = $srcfile;
                        POINT::$eval_idx = 0;
                        eval($___s);
                        chdir($oldcwd);
                        if (empty($dstfile)) {
                            $this->cfg_time($filemtime);
                        }
                        if ($this->post_process($dstfile, $filemtime, $___m[3]['code'])) {
                            $this->log(2, "e>$srcfile");
                            if (strlen($srcfile) + strlen($dstfile) > 75) {
                                $this->log(2, "\n\r  ");
                            }
                            $this->log(2, "-->$dstfile");
                            $___total_cnt++;
                            $this->log(2, "\n\r");
                        }

                        break;
                    }
                case 'copy':
                    if (empty($dstfile)) break;
                    $___s = pathinfo($dstfile); //$this->debug( '!dst -"'.$dstfile.'" ',$___s);
                    //print_r($___s);
                    if (!empty($___s['dirname']) && !is_dir($___s['dirname'])) {
                        $this->log(2, $___s['dirname']);
                        mkdir($___s['dirname'], 0777, true);
                    }
                    if (is_file($dstfile))
                        $mtime = @filemtime($dstfile);
                    else
                        $mtime = 0;
                    $this->debug(array($mtime, filemtime($srcfile)));
                    if (!is_file($dstfile) || (filemtime($dstfile) < filemtime($srcfile))) {
                        $this->log(2, "c>$srcfile");
                        if (!empty($___m[3]['code'])) {
                            $s = file_get_contents($srcfile);
                            $this->decode($s, $___m[3]['code']);
                            file_put_contents($dstfile, $s);
                        } else
                            copy($srcfile, $dstfile);
                        $this->betouch($dstfile, filemtime($srcfile));
                        if (strlen($srcfile) + strlen($dstfile) > 75) {
                            $this->log(2, "\n\r  ");
                        }
                        $this->log(2, "-->$dstfile" . "\n\r"); //  was last modified: " . date ("F d Y H:i:s.", filectime($srcfile));
                        $___total_cnt++;
                    }
                    break;
            }
        }
        $error = error_get_last();
        if (is_array($error)) {
            $this->log(1,
                sprintf('Error: %s(%s) module raised "%s" ' . "\n\r"
                    , realpath($this->srcfile), $error['line'], $error['message'])
                    . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
        }
        $this->log(1, sprintf("
total %s of %s files copied.\n\r", $___total_cnt, $___all_cnt));
    }
}