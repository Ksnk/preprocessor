<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Сергей
 * Date: 16.02.12
 * Time: 9:24
 * To change this template use File | Settings | File Templates.
 */

class MlHelper {
    protected $options=array();

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
    protected function opt($attr=null,$value=null){
        static $save=array();
        if(is_null($attr)) {
            if(count($save)>0)
            $this->options=array_pop($save);
        }
        else if(is_array($attr)) {
            array_push($save,$this->options);
            if(!empty($attr))
                $this->options=array_merge($this->options,$attr);
        }
        elseif(is_null($value))
            return $this->options[$attr];
        else {
            $res=array();
            foreach(func_get_args() as $v){
                if (isset($this->options[$v]))
                    $res[]=$this->options[$v];
                else
                    $res[]=null;
            }
            return $res;
        }
        return null;
    }

    /**
     * расшить строку на нужное количество символов, увеличивая количество пробелов между словами
     * @param $text
     * @param $need
     * @return mixed|string
     */
    protected function razeSpaces($text,$need){
        $x=explode(' ',trim($text));
        // so we need to replace every space with (count($x)-1+need/(count($x)-1)) spaces
        $result=array_shift($x);$cnt=count($x);
        while(!empty($x)){
            $spc=floor(($cnt+$need)/$cnt);
            $need-=$spc-1;$cnt--;
            $result.=str_repeat(' ',$spc).array_shift($x);
        }
        return $result;
    }

    /**
     * отрезать от строки не более size символов, по пробельному символу.
     * Если нету пробелов - режем по первому встретившемуся пробелу.
     * @param $text
     * @param $rest
     * @param $size
     */
    protected function cutStr(&$text,&$rest,$size){
        $rest='';
        if($size<mb_strlen($text,MlObject::$code)) {
            $i=strrpos (mb_substr($text,0,$size,MlObject::$code),' ');
            if(FALSE==$i){
                $i=strpos ($text,' ');
            }
            if(FALSE!=$i) {
                $rest=ltrim(substr($text,$i));
                $text=substr($text,0,$i);
            }
        }
    }

    public function __call($name, $arguments)
    {
        // Note: value of $name is case sensitive.
        throw new Exception("Calling object method '$name' "
             . "\n");
    }
    /**  As of PHP 5.3.0  */
    public static function __callStatic($name, $arguments)
    {
        // Note: value of $name is case sensitive.
        throw new Exception("Calling static method '$name' "
             . "\n");
    }


}

class MlWriter extends MlHelper {

}

class MlReader extends MlHelper {

}

/**
 * helper class to hold MLObject
 * Block Elements
 Paragraphs and Line Breaks
 Headers
 Blockquotes
 Lists
 Code Blocks
 Horizontal Rules
 * Span Elements
 Links
 Emphasis
 Code
 Images
 */
class MlObject {
    static $code='utf8';

    static $block_types=array(
        'para','header','quotes','list','code','hrule','br'
    );
    static $span_types=array(
        'text','link','em','emp','icode','image','strong'
    );


    public
        $type=''
        ,$value=''
        ,$level=0
    ;
    public
        $childs=array(),
        $attr;

    /**
     * @param string|array $type
     * @param string $value
     * @param array $childs
     */
    function __construct($type='',$value='',$childs=array(),$attr=array()){
        $this->attr=$attr;
        if(is_array($type))
            foreach($type as $k=>$v){
                if(isset($this->$k))
                    $this->$k=$v;
            }
        else {
            $this->type=$type;
            $this->value=$value;
            $this->childs=$childs;
        }
    }
}