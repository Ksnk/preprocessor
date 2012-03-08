<?php
/**
 * Reader HTML 2 ML (mediterial language)
 * User: Сергей
 * Date: 12.02.12
 * Time: 22:31
 * To change this template use File | Settings | File Templates.
 */

require_once "MlObject.php";
/**
 * Class to read HTML and build some sort of Recursive array (ML)
 */
class reader_HTML
{

    private
        /**
         * @var array - стек неизвестных пока тегов
         */
        $stack = array(),
        /**
         * @var array - количество незакрытых тегов
         */
        $stat = array();

    /**
     * html tags to be dropped (contents will not be parsed!)
     *
     * @var array<string>
     */
    private $dropTags = array(
        'script',
        'head',
        'style',
        'form',
        'area',
        'object',
        'param',
        'iframe',
    );
    private $allowedTags = array(
        'p' => array(),
        'ul' => array(),
        'ol' => array(),
        'li' => array(),
        'br' => array(),
        'blockquote' => array(),
        'code' => array(),
        'pre' => array(),
        'a' => array(
            'name' => 'optional',
            'href' => 'optional',
            'title' => 'optional',
        ),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
        'img' => array(
            'src' => 'required',
            'alt' => 'optional',
            'title' => 'optional',
        ),
        'h1' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'hr' => array(),

    );
    private
        /** @var string - текст для парсинга */
        $text,
        /** @var array - массив тегов */
        $tags = array();

    /**
     * из входного потока выедает очередной тег
     * Останавливается, если удалось выковырять хотя бы один тег
     */
    function parseNext()
    {
        $something = false;
        //   -------------------1---2-------3------4------5-------
        while (preg_match('#<(?:(/)?(\w+)\s*(.*?)\s*(/)?>|(!--)|!doctype.+?>)#i', $this->text, $m, PREG_OFFSET_CAPTURE)) {
            $something = true;
            if ($m[0][1] > 0) {
                $x = substr($this->text, 0, $m[0][1]);
                $this->pushTag($x, 'text');
            }
            $this->text = substr($this->text, $m[0][1] + strlen($m[0][0]));
            $attr = array();
            if (!empty($m[2][0])) {
                $tag = strtolower($m[2][0]);
                if (!isset($this->allowedTags[$tag]))
                    continue;
                if (in_array($tag, $this->dropTags)) {
                    // scan for closing tag
                    preg_match('#.*?</' . $tag . '.*?>#i', $this->text, $mm);
                    $this->text = substr($this->text, strlen($mm[0]));
                    continue;
                }
                if (empty($m[1][0]) && !empty($m[3][0])) { // выковыриваем параметры
                    if (!empty($this->allowedTags[$tag]))
                        foreach ($this->allowedTags[$tag] as $k => $v) {
                            if (preg_match('/' . $k . '=([\'"])((?:\\.|[^\1])*)\1/i', $m[3][0], $mm)) {
                                $attr[$k] = html_entity_decode(stripslashes($mm[2]));
                            }
                        }
                }
                if (!empty($m[4][0])) {
                    $this->pushTag('', $tag, $attr);
                    $this->pushTag('/', $tag);
                }
                $this->pushTag($m[1][0], $tag, $attr);
            } else if (!empty($m[5][0])) { // comment
                if (($i = strpos($this->text, '-->')) >= 0) {
                    $this->text = substr($this->text, $i + 3);
                }
                continue;
            } else
                continue;
            break; // scan for first non empty
        }
        if (!$something && "" != $this->text) {
            $this->pushTag($this->text, 'text');
            $this->text = '';
            return true;
        }
        ;
        return $something;
    }

    function pushTag($val, $tag, $attr = array())
    {
        array_push($this->tags, new MlObject($tag, $val, array(), $attr));
    }

    /**
     *  выбрать следующий тег из входного потока
     * @return array|mixed
     */
    function getNextTag()
    {
        while (empty($this->tags) && $this->parseNext()) ;
        if (empty($this->tags)) return FALSE;
        return array_shift($this->tags);
    }

    /**
     * Главная функция - парсинг html.
     * @param $html
     * @return array
     */
    function parseHtml($html)
    {
        $this->text = $html;
        /** @var MlObject $tag  */
        while ($tag = $this->getNextTag()) {
            $method = 'handle_' . $tag->type;
            $result = true;
            if (method_exists($this, $method))
                $result = $this->$method($tag);
            else if ($tag->value == '/')
                $result = $this->handle_closedTag($tag);
            if ($result !== false) {
                if (!isset($this->stat[$tag->type]))
                    $this->stat[$tag->type] = 1;
                else
                    $this->stat[$tag->type]++;
                array_push($this->stack, $tag);
            }


        }
        return $this->stack;
    }

    /**
     * @param MlObject $tag
     * @return bool
     */
    function handle_text(&$tag)
    {
        $tag->value = ltrim(preg_replace('/\s+$/', ' ', htmlspecialchars_decode($tag->value)));
        return true;
    }

    function handle_header(&$tag, $level)
    {
        $tag->type = 'header';
        $tag->level = $level;
        if ($tag->value == '/')
            return $this->handle_closedTag($tag);
        return true;
    }

    function handle_h1(&$tag)
    {
        return $this->handle_header($tag, 1);
    }

    function handle_h2(&$tag)
    {
        return $this->handle_header($tag, 2);
    }

    function handle_h3(&$tag)
    {
        return $this->handle_header($tag, 3);
    }

    function handle_h4(&$tag)
    {
        return $this->handle_header($tag, 4);
    }

    function handle_h5(&$tag)
    {
        return $this->handle_header($tag, 5);
    }

    function handle_h6(&$tag)
    {
        return $this->handle_header($tag, 6);
    }

    function handle_p(&$tag)
    {
        static $is_open_para;
        if (!isset($is_open_para)) $is_open_para = false;
        $tag->type = 'para';
        if ($tag->value == '/') {
            $is_open_para = false;
            $this->handle_closedTag($tag);
            if (empty($tag->childs))
                return false;
        } else {
            if ($is_open_para) { // close him first
                $xtag = new MlObject('para', '/');
                if ($this->handle_closedTag($xtag)) {
                    array_push($this->stack, $xtag);
                }
                ;
            }
            $is_open_para = true;
        }
        return true;
    }

    function handle_ul(&$tag)
    {
        $tag->type = 'list';
        $tag->bullet = '*';
        if ($tag->value == '/') {
            return $this->handle_closedTag($tag);
        }
        return true;
    }

    function handle_ol(&$tag)
    {
        $tag->type = 'list';
        $tag->bullet = '1';
        if ($tag->value == '/') {
            return $this->handle_closedTag($tag);
        }
        return true;
    }

    function handle_li(&$tag)
    {
        $tag->type = 'para';
        if ($tag->value == '/')
            return $this->handle_closedTag($tag);
        return true;
    }

    function handle_code(&$tag)
    {
        $result = true;
        if (empty($this->flag))
            $tag->type = 'icode';
        else
            $tag->type = 'code';
        if ($tag->value == '/') {
            $result = $this->handle_closedTag($tag);
            //
            $tag->value = '';
            if (!empty($tag->childs))
                foreach ($tag->childs as $v) {
                    if ($v->type == "text") $tag->value .= $v->value;
                }
            $tag->childs = array();
        }
        return $result;
    }

    function handle_pre(&$tag)
    {

        if ($tag->value == '/') {
            $this->flag = '';
        } else {
            $this->flag = 'pre';
        }
        return false;
    }

    function handle_a(&$tag)
    {
        $tag->type = 'link';
        if ($tag->value == '/')
            return $this->handle_closedTag($tag);
        return true;
    }

    /**
     * Функция ищет открывающий тег и бросает все остальное внутрь
     * @param MlObject $tag
     * @return bool
     */
    function handle_closedTag(&$tag)
    {
        if ($tag->value == '/') {
            //$tag=new MlObject('h1');
            // закрывающий тег
            // scan for closed tag
            if (!isset($this->stat[$tag->type]) || $this->stat[$tag->type] <= 0) {
                throw new Exception('Oops! Unknow tag ' . $tag->type);
            }
            $this->stat[$tag->type]--;
            do {
                if (empty($this->stack)) {
                    break;
                }
                $x = array_pop($this->stack);
                if (!!$x && ($x->type != $tag->type || $x->value != "")) {
                    array_unshift($tag->childs, $x);
                    continue;
                }
                break;
            } while (true);
            $tag->attr = $x->attr;
        }
        return true;
    }

    // обработка тегов

}