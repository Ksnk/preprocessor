<?php
/**
 * wiki-разметка для использования в препроцессоре
 * Упрощенный wiki парсер
 * -- параграфы отбиваются двумя и более переводами строк
 * -- если первый символ строки - пробельный - эту строку нельзя форматировать
 * -- первый символ == строка - заголовок, уровень заголовка определяется количеством =
 *
 * <%=point('hat','comment')%>
 */
/**
 * @class wiki-parcer
 */
class wiki_parcer{

	private $currentline,$currenttype;
	private $store=array();
	private $newline="\n";
	
	static function convert(&$s,$format){
		static $self;
		if (empty($self)) $self=new wiki_parcer();
		$self->read_string($s);
		return $self->wiki_txt();		
	}
	/**
	 * Читаем строку с wiki разметкой и храним ее в хранилище.
	 * @param string $s
	 */
	public function read_string($s){
		$offset=0;
		/**
		 * читаем в цикле одну строку и анализируем первый символ
		 */
		while($offset<strlen($s) && preg_match('/(====|===|==|\t|\s\s|)\s*([^\n\r]*)($|[\n\r]+)/',$s,$m,0,$offset)){
			//var_dump($m);
			$offset+=strlen($m[0]);
			switch($m[1]){
				case "==": 
					$this->newline(preg_replace('/\s+/',' ',$m[2]),'header',1); 
					break;
				case "===": 
					$this->newline(preg_replace('/\s+/',' ',$m[2]),'header',2); 
					break;
				case "====": 
					$this->newline(preg_replace('/\s+/',' ',$m[2]),'header',3); 
					break;
				case "": 
					$this->line(preg_replace('/\s+/',' ',$m[2]),'para'); 
					break;
				default:
					$this->line(str_replace("\t",'    ',rtrim($m[0]).$this->newline),'pre'); 
			}
			if (strlen($m[3])>2)
				$this->line_complete();
		}
		$this->line_complete();
	}
	
	private function newline($s,$type="para",$level=0){
		$this->line_complete();
		$this->line($s,$type,$level); 
	}
	
	private function line($s,$type="para",$lvl=0){
		if($this->currenttype!=$type)
			$this->line_complete();
		$this->currentline[]=$s;
		$this->currenttype =$type;
		$this->currentlevel =$lvl;
	}
	
	private function line_complete(){
		if (!empty($this->currentline))
			$this->store[]=array('txt'=>implode($this->currentline,' ')
				,'type'=>$this->currenttype
				,'lvl'=>$this->currentlevel);
		$this->currentline=array();
	}
	
	/**
	 * вывод текста в текстовом виде
	 * -- заголовки отбиваются переводами строк
	 * -- параграфы выравниваются влево по 80 символов
	 * -- pre не форматируются
	 */
	public function wiki_txt(){
		$result='';
		foreach($this->store as $line){
			switch($line['type']){
				case 'para':
					$result.=$this->jLeft($line['txt']).$this->newline;
					break;
				case 'pre':
					$result.=$line['txt'].$this->newline;
					break;
				case 'header':
					$result.=$this->jLeft($line['txt'],80,10,10).$this->newline;
					break;
			}
		}
		return $result;
	}
	/**
	 * отбить влево параграф. Строки не более $size символов. справа и слева отступы
	 * @param string $s
	 * @param integer $size
	 */
	function jLeft($s,$size=80,$left=0,$right=0) {
		$result='';
		$leftspaces="";
		if($left>0)
			$leftspaces=str_pad(' ',$left);
		$rsize=$size-$left-$right;
		while($s!=""){
			if(strlen($s)>$rsize)
				$i=strrpos(substr($s,0,$rsize),' ');
			else 
				$i=	strlen($s);
			if($i===FALSE) $i=strlen($s);
			$result.=$leftspaces.trim(substr($s,0,$i)).$this->newline;
			$s=trim(substr($s,$i+1));
		}
		return $result;
	}
}
