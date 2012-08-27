<?php


if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.dirname(dirname(__FILE__))) ;
    require 'PHPUnit/Autoload.php' ;
}

include_once ("src/wiki.ext.php");
include_once ("src/point.ext.php");
include_once ("src/preprocessor.class.php");

class pointTest extends PHPUnit_Framework_TestCase {

    function nl2nl($s){
        return trim(str_replace(array("\r\n","\r"),array("\n","\n"),$s))  ;
    }

    function createTestFiles($number,$dir='tmp',$prefix='tmp',$suffix='.tmp'){
        if(!is_dir($dir)) mkdir($dir);
        for($i=0;$i<$number;$i++){
            file_put_contents($dir.'/'.$prefix.sprintf('%04d',$i).$suffix,' ');
        }
    }

    function removeTestFiles($dir='tmp',$prefix='tmp',$suffix='.tmp'){
        $files=glob($dir.'/'.$prefix.'*'.$suffix);
        foreach($files as $file)
            unlink($file);
        $files=glob($dir.'/*.*');
        if(empty($files))
            rmdir($dir);
    }

    function testFilterCSS(){
        $data=<<<CSS
        table, td {
            vertical-align: top;     /* правило */
            padding: 0;
            margin: 0;
            border-collapse: collapse;
            table-layout: fixed;
            border-spacing: 0;
            overflow: hidden;
            line-height: 0;
            font-size: 1px;
        }

CSS;
        $pattern=<<<RESULT
table, td{vertical-align: top; padding: 0; margin: 0; border-collapse: collapse; table-layout: fixed; border-spacing: 0; overflow: hidden; line-height: 0; font-size: 1px;}
RESULT;
        $this->assertEquals(POINT::filter($data,'csscompress'),$pattern);
    }

    function testFilterJS(){
        $data=<<<DATA
    function sortfunc(a, b) {
        return a - b;
    }
    /**
     * Draws table by rectandles description
     *
     * @param {Object} arr - {x:, y:, h:, w:, color:} rectangles descriptors
     */
    function makeTable(arr) {

        var i, r, x, y, aL;

        // so calc all rows and cells
        var rows = {}, cells = {}, curcel = 0, currow = 0, table = [];

        for (i = 0, aL = arr.length; i < aL; i++) {
            r = arr[i];
            cells[r.x] = 0;
            cells[r.x + r.w] = 0;
            rows[r.y] = 0;
            rows[r.y + r.h] = 0;
        }
        //renumber rows and cells
        (function () {
            var _cell = [], _row = [];
            for (i in cells) _cell.push(i);
            for (i in rows) _row.push(i);
            _cell.sort(sortfunc);
            _row.sort(sortfunc);
            cells = {};
            rows = {};
            for (i = 0; i < _cell.length; i++) cells[_cell[i]] = curcel++;
            for (i = 0; i < _row.length; i++) rows[_row[i]] = currow++;
        })()  // so row-cells really sorted;

        //create table image
        for (i in cells) table.push(new Array(currow));

        // draw rectangles at the table image
        for (i = 0, aL = arr.length; i < aL; i++) {
            r = arr[i];
            for (x = cells[r.x]; x < cells[r.x + r.w]; x++)
                for (y = rows[r.y]; y < rows[r.y + r.h]; y++) {
                    table[x + 1][y + 1] = r;
                }
        }

        var html = [], last = 0;

        html.push('<col width="1">'); // height definition col
        for (i in cells) {
            html.push('<col width="' + (i - last) + '">');
            last = i;
        }
        // so create the rest html
        last = 0;
        var emptycel;
        function fillemptycell(){
            if (!!emptycel) {
                html.push('<td'
                        + (emptycel == 1 ? '' : ' colspan="' + emptycel + '"')
                        + '><!-- --></td>');
                emptycel = 0;
            }
        }

DATA;
        $pattern=<<<RESULT
function sortfunc(a, b) {
return a - b;
}
function makeTable(arr) {
var i, r, x, y, aL;
var rows = {}, cells = {}, curcel = 0, currow = 0, table = [];
for (i = 0, aL = arr.length; i < aL; i++) {
r = arr[i];
cells[r.x] = 0;
cells[r.x + r.w] = 0;
rows[r.y] = 0;
rows[r.y + r.h] = 0;
}
(function () {
var _cell = [], _row = [];
for (i in cells) _cell.push(i);
for (i in rows) _row.push(i);
_cell.sort(sortfunc);
_row.sort(sortfunc);
cells = {};
rows = {};
for (i = 0; i < _cell.length; i++) cells[_cell[i]] = curcel++;
for (i = 0; i < _row.length; i++) rows[_row[i]] = currow++;
})()
for (i in cells) table.push(new Array(currow));
for (i = 0, aL = arr.length; i < aL; i++) {
r = arr[i];
for (x = cells[r.x]; x < cells[r.x + r.w]; x++)
for (y = rows[r.y]; y < rows[r.y + r.h]; y++) {
table[x + 1][y + 1] = r;
}
}
var html = [], last = 0;
html.push('<col width="1">');
for (i in cells) {
html.push('<col width="' + (i - last) + '">');
last = i;
}
last = 0;
var emptycel;
function fillemptycell(){
if (!!emptycel) {
html.push('<td'
+ (emptycel == 1 ? '' : ' colspan="' + emptycel + '"')
+ '><!-- --></td>');
emptycel = 0;
}
}
RESULT;
        $this->assertEquals(trim(POINT::filter($data,'jscompress')),$this->nl2nl($pattern));
    }

    function testFilterJS2JS(){
        $data=<<<DATA
    function sortfunc(a, b) {
        return a - b;
    }
    /**
     * Draws table by rectandles description
     *
     * @param {Object} arr - {x:, y:, h:, w:, color:} rectangles descriptors
     */
    function makeTable(arr) {

        var i, r, x, y, aL;

        // so calc all rows and cells
        var rows = {}, cells = {}, curcel = 0, currow = 0, table = [];

        for (i = 0, aL = arr.length; i < aL; i++) {
            r = arr[i];
            cells[r.x] = 0;
            cells[r.x + r.w] = 0;
            rows[r.y] = 0;
            rows[r.y + r.h] = 0;
        }
        //renumber rows and cells
        (function () {
            var _cell = [], _row = [];
            for (i in cells) _cell.push(i);
            for (i in rows) _row.push(i);
            _cell.sort(sortfunc);
            _row.sort(sortfunc);
            cells = {};
            rows = {};
            for (i = 0; i < _cell.length; i++) cells[_cell[i]] = curcel++;
            for (i = 0; i < _row.length; i++) rows[_row[i]] = currow++;
        })()  // so row-cells really sorted;

        //create table image
        for (i in cells) table.push(new Array(currow));

        // draw rectangles at the table image
        for (i = 0, aL = arr.length; i < aL; i++) {
            r = arr[i];
            for (x = cells[r.x]; x < cells[r.x + r.w]; x++)
                for (y = rows[r.y]; y < rows[r.y + r.h]; y++) {
                    table[x + 1][y + 1] = r;
                }
        }

        var html = [], last = 0;

        html.push('<col width="1">'); // height definition col
        for (i in cells) {
            html.push('<col width="' + (i - last) + '">');
            last = i;
        }
        // so create the rest html
        last = 0;
        var emptycel;
        function fillemptycell(){
            if (!!emptycel) {
                html.push('<td'
                        + (emptycel == 1 ? '' : ' colspan="' + emptycel + '"')
                        + '><!-- \\ --></td>');
                emptycel = 0;
            }
        }

DATA;
        $pattern=<<<RESULT
function sortfunc(a, b) {\\nreturn a - b;\\n}\\nfunction makeTable(arr) {\\nvar i, r, x, y, aL;\\nvar rows = {}, cells = {}, curcel = 0, currow = 0, table = [];\\nfor (i = 0, aL = arr.length; i < aL; i++) {\\nr = arr[i];\\ncells[r.x] = 0;\\ncells[r.x + r.w] = 0;\\nrows[r.y] = 0;\\nrows[r.y + r.h] = 0;\\n}\\n(function () {\\nvar _cell = [], _row = [];\\nfor (i in cells) _cell.push(i);\\nfor (i in rows) _row.push(i);\\n_cell.sort(sortfunc);\\n_row.sort(sortfunc);\\ncells = {};\\nrows = {};\\nfor (i = 0; i < _cell.length; i++) cells[_cell[i]] = curcel++;\\nfor (i = 0; i < _row.length; i++) rows[_row[i]] = currow++;\\n})()\\nfor (i in cells) table.push(new Array(currow));\\nfor (i = 0, aL = arr.length; i < aL; i++) {\\nr = arr[i];\\nfor (x = cells[r.x]; x < cells[r.x + r.w]; x++)\\nfor (y = rows[r.y]; y < rows[r.y + r.h]; y++) {\\ntable[x + 1][y + 1] = r;\\n}\\n}\\nvar html = [], last = 0;\\nhtml.push('<col width=\\"1\\">');\\nfor (i in cells) {\\nhtml.push('<col width=\\"' + (i - last) + '\\">');\\nlast = i;\\n}\\nlast = 0;\\nvar emptycel;\\nfunction fillemptycell(){\\nif (!!emptycel) {\\nhtml.push('<td'\\n+ (emptycel == 1 ? '' : ' colspan=\\"' + emptycel + '\\"')\\n+ '><!-- \\\\ --></td>');\\nemptycel = 0;\\n}\\n}\\n
RESULT;
        $this->assertEquals(trim(POINT::filter(POINT::filter($data,'jscompress'),'2js')),$this->nl2nl($pattern));
    }
}
if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('pointTest');
    PHPUnit_TextUI_TestRunner::run( $suite);
}
?>