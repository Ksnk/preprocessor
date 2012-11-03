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

    /**
     * here we can store the file list, Ну да!
     */
    private $store = array();

    /**
     *  here we can store a current start of files we can remove from list by using remove tag
     */
    private $store_stack = array(0);

    /**
     * just an options to hold point attributes awhile parsing an xml tree
     * @var array
     */
    protected $options = array();

    /**
     * array with variables exported from outer space
     * (xml, command line and so on);
     */
    public $exported_var = array();


    /**
     * to get a system codepages from system registry (Windows)
     * @return array  (system code page, console code page)
     */
    private static function get_system_codepages()
    {
        ob_start();
        system("reg query HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Control\\Nls\\CodePage");
        $res = ob_get_contents();
        ob_end_clean();
        if (!preg_match_all('/(ACP|OEMCP)\s+REG_SZ\s+(\d+)(\S*)/', $res, $m))
            return array('UTF-8', 'UTF-8');
        for ($i = 0; $i < 2; $i++)
            if (empty($m[2][$i]))
                $m[2][$i] = $m[3][$i];
            else
                $m[2][$i] = 'cp' . $m[2][$i];
        if ($m[1][0] == 'ACP')
            return array($m[2][0], $m[2][1]);
        else
            return array($m[2][1], $m[2][0]);
    }

    /**
     * so convert string from unknown to (utf|system|console) codpage
     * by using autodetect ability
     */
    static function ic($code, $src)
    {
        static $codepages, $sample;
        if (empty($codepages)) {
            $codepages = self::get_system_codepages();
            $sample = array('utf' => 'UTF-8',
                'sys' => $codepages[0],
                'con' => empty($_ENV["SYS_CONSOLE"])?'UTF-8':$codepages[1] );
      //          'con' => $codepages[1]  );
        }
        if (isset($sample[$code]))
            $code = $sample[$code];

        if (preg_match('/^(?:[\x01-\x7F]|[\xC0-\xFF][\x80-\xBF])+$/', $src)) {
            // is it UTF-8?
            if ($code == 'UTF-8' || !preg_match('/[\x80-\xFF]/', $src))
                return $src;
            else {
                return iconv('UTF-8', $code . '//IGNORE', $src);
            }
        } else if (preg_match('/[\x80-\xFF]/', $src)) {
            // is it 1251?
            if ($code == $codepages[0])
                return $src;
            else
                return iconv($codepages[0], $code . '//IGNORE', $src);
        }
        return $src;
    }

    /**
     * some sort of singlethone
     * @static
     * @return mixed
     */
    static function instance()
    {
        if (!isset($GLOBALS['preprocessor']))
            $GLOBALS['preprocessor'] = new preprocessor();
        return $GLOBALS['preprocessor'];
    }

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
     * store setter  SRC - DST - ACTION
     * @param $src - from
     * @param $dst - to
     * @param $act - what to do (eval||copy)
     */
    public function newpair($src, $dst = '', $act = 'eval', $par = '')
    {
        if (empty($par)) $par = array();
        $dst = preg_replace('#(\\/)\.\1#', '\1', $dst);
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
        $name = (string)$files['name'];
        $value = (string)$files['value'];
        if (empty($value)) $value = (string)$files;
        if (empty($name))
            $this->log(0, 'XML: there is no NAME parameter of VAR tag.'); // faked variable
        if (empty($value)) { // just assign a value if no values was a while
            if (isset($this->exported_var[(string)$files['name']]))
                return;
        }
        if (empty($value))
            $value = (string)$files['default'];
        if (!empty($value))
            $this->export((string)$files['name'], $value);
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

    function handle_remove(&$files)
    {
        $start = $this->store_stack[0]; // so we trust it always be ;)
        $mask = (string)$files['name'];
        if (empty($mask)) $mask = (string)$files;
        if (empty($mask))
            $this->log(0, 'XML: there is no NAME or inner value of REMOVE tag.'); // faked variable
        /* so create mask */
        $mask = '#' . preg_replace(
            array('~[\\\\/]~', '/\./', '/\*\*+/', '/\*/', '/@@0@@/', '/@@1@@/', '/@@2@@/', '/\?/')
            , array('@@2@@', '\.', '@@0@@', '@@1@@', '.*', '[^:/\\\\\\\\]*', '[\/\\\\\\\\]', '[^:/\\\\\\\\]'), $mask) . '$#';
        //$this->log(2, 'remove: from: '.$start.' to:'.count($this->store).' mask built: '.$mask);
        for ($i = count($this->store) - 1; $i >= $start; $i--) {
            $src = $this->store[$i][0];
            //$this->log(2, 'remove:'.$src);
            if (preg_match($mask, $src)) {
                array_splice($this->store, $i, 1);
            }
        }
    }

    /**
     * `remove` sinonym, sorry not reflect this in documentation. ;)
     * @param $files
     */
    function handle_exclude(&$files)
    {
        return $this->handle_remove($files);
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
            $files = array_merge($files, glob(self::ic('sys', $m)));
        return array_unique($files);
    }

    /**
     * @param SimpleXMLElement $files
     */
    private function xml_string($files)
    {
        $this->opt($files->attributes());

        $name = 'handle_' . strtolower($files->getName());
        if (method_exists($this, $name)) {
            call_user_func_array(array($this, $name), array(&$files));
        } elseif ($files->getName() == 'files') {
            array_unshift($this->store_stack, count($this->store));
            foreach ($files->children() as $file) {
                $this->xml_string($file);
            }
            array_shift($this->store_stack);
        } else {
            $dst = $this->opt('dstdir');
            $attributes = array();
            foreach ($files->attributes() as $k => $v) {
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

            $str_file = $this->evd((string)$files);
            if (!empty($dst) && !preg_match('#[^\w\.\\/]#', $str_file) && dirname($str_file) != '') {
                $dst .= '/' . dirname($str_file);
            }
            if ($files->getName() == 'echo') {
                list($dst, $name) = $this->opt('dstdir', 'name');
                if (!empty($dst)) {
                    if (empty($name) && preg_match('#[\w\.\\\/#', $files)) {
                        $name = basename($files);
                    }
                    $dst .= '/' . $name;
                }
                $this->newpair(
                    (string)$files,
                    self::ic('sys', $dst),
                    $files->getName()
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
                $flist= $this->findByMask($dir . $str_file);
                if(count($flist)==0){
                    $this->log(3, sprintf("No  files found at  `%s` (cwd-`%s`) \n", $dir . $str_file,getcwd()));
                }
                foreach ($flist as $a) {
                    if(is_dir($a)) continue;
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
                        self::ic('sys', $dst),
                        $files->getName()
                        , $attributes);
                }
            }
        }
        $this->opt();
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
        $this->log(4, $oldcwd . ' ' . realpath($xml) . "\n");
        if (is_file(realpath($xml))) {
            $xml = realpath($xml);
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
            $this->xml_string($files);
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
                $s = str_replace("\xEF\xBB\xBF", '', trim($s));
                $s = preg_replace(array('~^\s*/\*\s*\*/\s*$~m', '~\s*/\*\s*\*/~'), array('', ''), $s);
                file_put_contents($dst, $s);
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
        //self::log(2,$this->store);
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
                            $srcfile = self::ic('con', $srcfile);
                            $dstfile = self::ic('con', $dstfile);

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

                        if (!empty($___m[3]['code'])) {
                            $s = file_get_contents($srcfile);
                            $this->decode($s, $___m[3]['code']);
                            file_put_contents($dstfile, $s);
                        } else {
                            $data = file_get_contents($srcfile);

                            $handle = fopen($dstfile, "w");
                            fwrite($handle, $data);
                            fclose($handle);
                           // was: copy($srcfile, $dstfile);
                        }

                        $this->betouch($dstfile, filemtime($srcfile));

                        $srcfile = self::ic('con', $srcfile);
                        $dstfile = self::ic('con', $dstfile);

                        $this->log(2, "c>$srcfile");
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