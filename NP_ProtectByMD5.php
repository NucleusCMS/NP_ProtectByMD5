<?php 
/*
 * NP_ProtectByMD5 ver 0.3.0
 * Written by Katsumi
 * This library is GPL
 *
 * Acknowredgment
 *   This plugin uses a free javascript library,
 *   MD5.js.  I thank the author of MD5.js for
 *   constructing it and letting it be free.
 */
class NP_ProtectByMD5 extends NucleusPlugin { 
	function getName() { return 'NP_ProtectByMD5'; }
	function getMinNucleusVersion() { return 320; }
	function getAuthor()  { return 'Katsumi'; }
	function getVersion() { return '0.3.0'; }
	function getURL() {return 'http://japan.nucleuscms.org/wiki/plugins:protectbymd5';}
	function supportsFeature($what) { return (int)($what=='SqlTablePrefix'); }
	function getEventList() { return array('PostAuthentication','FormExtra'); }
	function getDescription() { 
		$ret=$this->getName();
		if ($this->getOption('usethis')=='yes') {
			$ret.=$this->translated(' plugin. This plugins is now enabled.');
			$ret.=$this->translated(' Following action(s) is/are checked: ');
			$ret.=preg_replace('[\s]',' ',$this->getOption('actions'));
		} else $ret.=$this->translated(' plugin. This plugins is now disabled. Edit the plugin option to enable this.');
		return $ret;
	} 
	function install() {
		$this->createOption('usethis', $this->translated('Enable this plugin?'), 'yesno', 'no');
		$this->createOption('checkmember', $this->translated('Use this plugin also for the post from member?'), 'yesno', 'yes');
		$this->createOption('log', $this->translated('Leave the log of spam?'), 'yesno', 'no');
		$this->createOption('actions', $this->translated('Check these actions:'), 'textarea', "addcomment\n");
		$this->createOption('jsencode', $this->translated('JSEncode?'), 'yesno', 'no');
		$this->createOption('formextra', $this->translated('Use FormExtra event?'), 'yesno', 'no');
		$this->createOption('formextraactions', $this->translated('Follow these actions in event_FormExtra:'), 'textarea', "commentform-loggedin\ncommentform-notloggedin\n");
		$this->createOption('errormessage', $this->translated('Error message when the authentication is failed:'), 'textarea', $this->translated('Please go back, reload the page, and do this again.'));
		$this->createOption('acceptlist', $this->translated('Always accept the posts from IP addresses defined in accept.php?'), 'yesno', 'no');
		$this->createOption('key1','hidden-option','text','','access=hidden');
		$this->createOption('key2','hidden-option','text','','access=hidden');
		$this->createOption('keyexpire','hidden-option','text','0','access=hidden');
	}
	var $info=false;
	var $num=0;
	function doSkinVar($skinType,$type,$info='') {
		if ($this->getOption('usethis')!='yes' || $this->_checkIP()) return;// _checkIP() also checks if member is logged in
		if ($info) $this->info=$info;
		switch(strtolower($type)){
		case 'begin':
			// If this is the first time of calling this code, include the javascript file (md5.js)
			$this->num++;
			if ($this->num==1) {
				echo '<script type="text/javascript" src="'.$this->getAdminURL().'md5.js"></script>'."\n";
			}
			ob_start();
			break;
		case 'end':
			$text=ob_get_contents();
			ob_end_clean();
			if (strstr($text,'<input type="hidden" name="np_protectbymd5"')) {
				// Just echo because the input tag is already inserted using FormExtra event
				echo $text;
			} else {
				// Insert the javascript to calculate MD5 value.
				if ($this->getOption('jsencode')=='yes') $this->_jsencode($text);
				else $this->_normal($text);
			}
			break;
		default:
		}
	}
	function event_FormExtra(&$data){
		if ($this->getOption('formextra')!='yes') return;
		if ($this->getOption('usethis')!='yes' || $this->_checkIP()) return;// _checkIP() also checks if member is logged in
		if (!($action=$data['type'])) return;
		$actions=' '.preg_replace('[\s]',' ',$this->getOption('formextraactions')).' ';
		if (!strstr($actions," $action ")) return;
		// If this is the first time of calling this code, include the javascript file (md5.js)
		$this->num++;
		if ($this->num==1) {
			echo '<script type="text/javascript" src="'.$this->getAdminURL().'md5.js"></script>'."\n";
		}
		// Insert the javascript to calculate MD5 value.
		if ($this->getOption('jsencode')=='yes') $this->_jsencode();
		else $this->_normal();
	}
	function _normal($text=''){
		$key=$this->_getKey();
		$replace='<input type="hidden" name="np_protectbymd5" value="'.$key.'">';
		if ($this->info) $replace.='<noscript>'.htmlspecialchars($this->info).'</noscript>';
		$replace.='
<script type="text/javascript">
/*<![CDATA[*/
  document.write(\'<input type="hidden" name="np_protectbymd5_hash" value="\'+MD5_hexhash("'.$key.'")+\'">\');
/*]]>*/
</script>'."\n";
		if ($text) {
			$replace.='</form>';
			foreach(explode("\n",$text) as $line) echo str_replace('</form>',$replace,$line)."\n";
		} else echo $replace;
	}
	function _jsencode($text=''){
		$key=$this->_getKey();
		do $random=md5(uniqid(rand(),true));
		while (strstr($text,$random));
		$replace='<input type="hidden" name="np_protectbymd5" value="'.$key.'">'."\n";
		$replace.='<input type="hidden" name="np_protectbymd5_hash" value="'.$random.'">'."\n";
		if ($text) {
			$replace.='</form>';
			$text=str_replace('</form>',$replace,$text);
		} else $text=$replace;
		echo '<span id="np_protectbymd5_'.(int)$this->num.'">'.htmlspecialchars($this->info).'<span style="display:none">';
		for ($i=0;$i<strlen($text);$i++) {
			$ascii=ord(substr($text,$i,1));
			if (0x7f<$ascii||$ascii==0xa||$ascii==0xd) echo substr($text,$i,1);
			else if ($ascii) echo '%'.substr('0'.dechex($ascii),-2);
		}
		echo '</span></span>';
?><script type="text/javascript">
/*<![CDATA[*/
  t=document.getElementById('np_protectbymd5_<?php echo (int)$this->num; ?>').innerHTML+'';
  i=t.indexOf('>');
  j=t.indexOf('</');
  if (0<=i && 0<=j) {
    t=unescape(t.substr(i+1,j-i-1));
    t=t.replace(/<?php echo $random; ?>/,MD5_hexhash("<?php echo $key; ?>"));
    document.getElementById('np_protectbymd5_<?php echo (int)$this->num; ?>').innerHTML=t;
  }
/*]]>*/
</script><?php
	}
	function _getKey(){
		$key=$this->getOption('key1');
		if ($this->getOption('keyexpire')<time()) {
			$this->setOption('keyexpire',time()+3600);
			$this->setOption('key2',$key);
			$key=md5(time());
			$this->setOption('key1',$key);
		}
		return $key;
	}
	function event_PostAuthentication(){
		if ( !($error=$this->_checkHash()) ) return;
		if ($this->getOption('log')=='yes') {
			$message='A spam from '.htmlspecialchars(serverVar('REMOTE_ADDR'),ENT_QUOTES).': ';
			$message.=htmlspecialchars($error,ENT_QUOTES);
			ACTIONLOG::add(WARNING,$message);
		}
		header('Content-type: text/html; charset='._CHARSET);
		echo '<html><head><title>Error</title></head><body>Wrong MD5 hash or expired key!<br /><br />';
//$this->createOption('errormessage', $this->translated('Error message for invalid action:'), 'textarea', $this->translated('Please go back, reload the page, and do this again.'));
		echo $this->getOption('errormessage');
		echo '</body></html>';
		exit;
	}
	function _checkHash(){
		if ($this->getOption('usethis')!='yes') return false;
		if (!($action=requestVar('action'))) return false;
		$actions=' '.preg_replace('[\s]',' ',$this->getOption('actions')).' ';
		if (!strstr($actions," $action ")) return false;
		if ($this->_checkIP()) return false;
		$key=postVar("np_protectbymd5");
		$hash=postVar("np_protectbymd5_hash");
		if ($hash!=md5($key)) return 'Wrong hash ('.$hash.')';
		if ($key!=$this->getOption('key1') && $key==$this->getOption('key2')) return 'Expired key';
		return false;
	}
	var $accept=false;
	function _checkIP(){
		global $member;
		if ($member->isLoggedIn() && $this->getOption('checkmember')=='no') return true;
		if ($this->getOption('acceptlist')!='yes') return false;
		include_once(dirname(__FILE__).'/protectbymd5/accept.php');
		return $this->accept;
	}
	function translated($english){
		if (!is_array($this->langArray)) {
			$this->langArray=array();
			$language=$this->getDirectory().str_replace( array('\\','/'), array('',''), getLanguageName()).'.php';
			if (file_exists($language)) include($language);
		}
		if (!($ret=$this->langArray[$english])) $ret=$english;
		return $ret;
	}
}
?>