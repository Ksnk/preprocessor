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
class reader_MARKDOWN
{

    function _detab_callback($matches) {
        $line = $matches[0];
        $strlen = $this->utf8_strlen; # strlen function for UTF-8.

        # Split in blocks.
        $blocks = explode("\t", $line);
        # Add each blocks to the line.
        $line = $blocks[0];
        unset($blocks[0]); # Do not add first block twice.
        foreach ($blocks as $block) {
            # Calculate amount of space, insert spaces, insert block.
            $amount = $this->tab_width -
                $strlen($line, 'UTF-8') % $this->tab_width;
            $line .= str_repeat(" ", $amount) . $block;
        }
        return $line;
    }

    /**
     * выковырять первый элемент
     * @param $s
     */

    function getBlockElement($s){
        if(preg_match(
            '/$( {4}| {0-3}\t)|(\s*#*)|/'
            ,''
            ,$m
        )){

        }
    }

    function parseMarkdown($s){
        // замена неопределенностей
        $s=str_replace(array("\r\n","\r"),
            array("\n",""),$s) ;
        $s = preg_replace_callback('/^.*\t.*$/m',
            array(&$this, '_detab_callback'), $s);
        // расширение табуляции
        return array(new MlObject('text', $s));
    }
}
