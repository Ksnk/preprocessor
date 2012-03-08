<?php
/**
 * @include MlObject.php
 * Класс конверсии ML в текстовый вид
 *
 * text - склеивается с предыдущим типом text
 */

class writer_Text extends MlWriter
{

    /**
     * определяют окружение рендерера - ширину поля, отступы и красные строки
     * @var array
     */
    protected
        $options = array(
        'redstring' => ''
    , 'nextstring' => ''
    , 'width' => 80
    , 'justify.para' => true
    );

    /**
     * @param $text
     * @param $size
     * @param string $first
     * @param string $next
     * @return string
     */
    function ident($text, $size, $first = '', $next = ' ')
    {
        if ("" == trim($text)) return '';
        $result = rtrim(str_pad($first, $size) . str_replace("\n", "\n" . str_pad($next, $size), $text), ' ');
        return $result;
    }

    /**
     * Разбить текст по строкам, выравнивая по правому краю
     * @param $text
     * @param $redstring
     * @param $nextstring
     * @param $size
     * @return string
     */
    function justifyPara($text)
    {
        list($size, $redstring, $nextstring) = $this->opt('width', 'redstring', 'nextstring');
        /* $size=$this->opt('width');
        $redstring=$this->opt('redstring');
        $nextstring=$this->opt('nextstring');*/

        $result = array();
        $text = preg_replace('/\s+/', ' ', trim($text)) . ' ';
        $rest = '';
        //red line
        $prefix =& $redstring;
        do {
            $linesize = $size - mb_strlen($prefix, MlObject::$code);
            $this->cutStr($text, $rest, $linesize);

            if ("" != $rest && $this->opt('justify.para')) {
                $result [] = $prefix . $this->razeSpaces($text, $linesize - mb_strlen($text, MlObject::$code));
            } else {
                $result [] = $prefix . rtrim($text);
            }
            $text = $rest;
            unset($prefix);
            $prefix =& $nextstring;
        } while ($text != '');
        unset($prefix);
        return implode("\n", $result);
    }

    /**
     * @param array $ml
     * @param bool $justify
     * @return string
     */

    function renderTag(&$ml, $justify = false)
    {
        $res = '';
        $text = '';
        if (!empty($ml)) {
            /** @var MlObject $x */
            while (!!($x = array_shift($ml))) {
                $func = 'handle_' . $x->type;
                if (in_array($x->type, MlObject::$span_types)) {
                    $text .= $this->$func($x);
                } else {
                    if (!empty($text)) {
                        if ($justify)
                            $res .= $this->justifyPara($text) . "\n\n";
                        else
                            $res .= $text;
                        $text = '';
                    }
                    $res .= $this->$func($x);
                }
            }
        }
        if ($justify)
            $res .= $this->justifyPara($text) . "\n\n";
        else
            $res .= $text;
        return $res;
    }

    /**
     * главная функция - конвертер
     * @param array $ml
     * @param array $options
     * @return string
     */
    function writeText($ml, $options = array())
    {
        $this->opt($options);
        $res = preg_replace('#\n *(?:\n *)+(\n *)#', "\n\\1", str_replace("\x1f", ' ', $this->renderTag($ml)));
        $this->opt();
        return $res;
    }

    /**
     * обработчики тегов
     */

    /**
     * @param $ml
     * @return mixed
     * @todo - сделать центрирование и отступ
     */
    function handle_header($ml)
    {
        $text = $this->renderTag($ml->childs);
        $redstring = $this->opt('redstring');
        if ($ml->level < 3) {
            $x = $text . "\n" . $redstring . str_repeat(
                $ml->level == 1 ? '=' : '-', mb_strlen($text, MlObject::$code));
        } else
            $x = sprintf('%1$s %2$s %1$s', str_repeat('#', $ml->level), $text);
        return $redstring . $x . "\n" . "\n";
    }

    /**
     * @param $ml
     * @return mixed
     */
    function handle_text($ml)
    {
        return $this->renderTag($ml->childs) . $ml->value;
    }

    /**
     * @param $ml
     * @return mixed
     * @todo: проверить установленные параметры
     */
    function handle_para($ml)
    {
        return $this->renderTag($ml->childs, true);
    }

    /**
     * @param MlObject $ml
     * @return mixed
     */
    function handle_list($ml)
    {
        $result = '';
        $ind = 1;
        if (!empty($ml->childs)) {
            $this->opt(array('width' => $this->opt('width') - 4));
            foreach ($ml->childs as $mml) {
                $func = 'handle_' . $mml->type;
                $txt = $this->$func($mml);
                if ("" != trim($txt)) {
                    $result .= $this->ident($txt, 4, $ml->bullet == '*' ? '*' : $ind);
                    $ind++;
                }
            }
            $this->opt();
        }
        return $result;
    }

    /**
     * @param MlObject $ml
     * @return string
     */
    function handle_code($ml)
    {
        return $this->ident($ml->value, 4) . "\n\n";
    }

    /**
     * @param MlObject $ml
     * @return string
     */
    function handle_icode($ml)
    {
        return '`' . str_replace(' ', "\x1f", $ml->value . $this->renderTag($ml->childs)) . '`';
    }

    /**
     * @param MlObject $ml
     * @return string
     */
    function handle_strong($ml)
    {
        return $this->renderTag($ml->childs);
    }

    /**
     * @param MlObject $ml
     */
    function handle_em(&$ml)
    {
        return '_' . $this->renderTag($ml->childs) . '_';
    }

    /**
     * @param MlObject $ml
     */
    function handle_br(&$ml)
    {
        return '';
    }

    /**
     * @param MlObject $ml
     */
    function handle_link(&$ml)
    {
        $x = html_entity_decode(trim($this->renderTag($ml->childs)));
        if(!isset($ml->attr['href']))
            return $x;
        $href = $ml->attr['href'];
        if (empty($href)) return '';
        if (preg_match('/^#/', $href)) {
            return $x;
        }
        if ($x == $ml->attr['href']) {
            return '<' . $x . '> ';
        } else {
            return ' (' . $x . ')[' . $ml->attr['href'] . '] ';
        }
    }


}
