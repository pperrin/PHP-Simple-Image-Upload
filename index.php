<?php
	$username="";
	if (!authorised()) {return;}	
?>
<html>
<head><title>Image Uploader</title></head>
<style>
.thumb {height:75px; border:4px solid black; margin-right:5px;}
</style> 	
<body>
<?php
echo '<div id="login">You are logged in as: ' . $username . '</div>';
	if ($_SERVER['REQUEST_METHOD']=='POST') {
		echo "<div id='action'>";
		$act = json_decode(stripslashes($_POST['action']));
		if ($act->action == 'add') handleupload($act->set);
		if ($act->action == 'del') deleteset($act->set);
		if ($act->action == 'new') createset();
		if ($act->action == 'delpic') deletepic($act->set,$act->pic);
		echo "</div>";
	}
echo '<form action="" method="post" enctype="multipart/form-data">';
$new = json_encode(array("action"=>"new"));
echo "<div id='newset'><input type='text' name='newsetname'><button type='submit' name='action' value='$new' >New Set</button></div>";
echo '<div id="newfile">Select File <input type="file" name="imagefile" /></div>';
$sets = scandir('sets');
	foreach ($sets as $set) {
	if (is_dir('sets/'.$set)) {
		if (safedir($set)) {
			$files = scandir('sets/'.$set);
			$add = json_encode(array("action"=>"add","set"=>$set));
			$del = json_encode(array("action"=>"del","set"=>$set));
			echo "<fieldset><legend> $set <button type='submit' name='action' value='$add'>Add Image</button><button type='submit' name='action' onclick='return confirm(\"Are you sure?\")' value='$del'>Delete Set</button></legend>";
			foreach($files as $file) {
				if (!is_dir($file)) {
					$del = json_encode(array("action"=>"delpic","set"=>$set,"pic"=>$file));
					echo "<button type='submit' name='action' onclick='return confirm(\"Confirm Delete?\")' value='$del'><img src='sets/$set/$file' class='thumb' /></button>";
			}	}
			echo '</fieldset>';
}	}	}
?>
</form>
</body>
</html>
<?php
function deletepic($set,$img) {
	if ($set=="") {echo ('no set name given'); return;}
	if (!safedir($set)) {echo ('invalid set name'); return;}
	if ($img=="") {echo ('no image name given'); return;}
	if (!safefile($img)) {echo ('invalid image name'); return;}
	$path = getcwd()."/sets/".$set."/".$img;
	if (is_dir($path)) die ('file error '.$path);
	$allowed_filetypes = array('.jpg','.jpeg','.png','.gif');
	$ext = substr($img, strpos($img,'.'), strlen($img)-1);	
	if(!in_array($ext,$allowed_filetypes)) {echo ('invalid image filename'); return;}
	if (!file_exists($path)) {echo ('file does not exist');  return;}
	unlink($path);	
}
function createset() {
	if (!isset($_POST['newsetname'])) {echo ('no set name given'); return;}
	$set = $_POST['newsetname'];
	if ($set=="") {echo ('no set name entered'); return;}
	if (!safedir($set)) {echo ('invalid setname'); return;}
	$path = getcwd().'/sets/';
	if (file_exists($path.$set)) {echo ('set already exists');  return;}
	if(!is_writable($path)) {echo ('You cannot create the specified directory, please CHMOD to 777.');  return;}
	mkdir($path.$set, 0777, true);	
}

function safefile($name) {
	return $name===preg_replace("/[^A-Za-z0-9.-_]/", "-", $name);
}
function safedir($name) {
	return $name===preg_replace("/[^A-Za-z0-9-_]/", "-", $name);
}
function handleupload($setname) {
	if (!safedir($setname)) {echo ('illegal set name'); return;}
	if (!isset($_FILES['imagefile']['name'])) {echo ('no file name given'); return;}
	$filename = $_FILES['imagefile']['name'];
	if ($filename=="") {echo ("no file name entered"); return;}
	if (!safefile($filename)) {echo ("illegal file name entered"); return;}
	$allowed_filetypes = array('.jpg','.jpeg','.png','.gif');
	$max_filesize = 10485760;
	$ext = substr($filename, strpos($filename,'.'), strlen($filename)-1);	
	if(!in_array($ext,$allowed_filetypes)) {echo ('The file you attempted to upload is not allowed.'); return;}

	$path =getcwd() . "/sets/" . $setname . '/';

	if(filesize($_FILES['imagefile']['tmp_name']) > $max_filesize) {echo ('The file you attempted to upload is too large.'); return;}
	if (!file_exists($path)) mkdir($path, 0777, true);
	if(!is_writable($path)) {echo ('You cannot upload to the specified directory, please CHMOD it to 777.'); return;}
	
	if(move_uploaded_file($_FILES['imagefile']['tmp_name'],$path . $filename)) {
		echo 'Your file upload was successful!';
	} else {
		echo 'There was an error during the file upload.  Please try again.';
	}
}
function deleteset($setname) {
	if (!safedir($setname)) {echo ('illegal set name'); return;}
	$path = getcwd()."/sets/".$setname;
	if (!is_dir($path)) {echo ('invalid set name'); return; }
	if (count(scandir($path))>2) {echo ('set not empty'); return; } 
	rmdir($path);
};

function authorised() {
	$realm = 'Restricted area';
	$users = array('admin' => 'Pa$$word1', 'user' => 'Pa$$word2');

	if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
		die('Operation Cancelled.');
	}

	// analyze the PHP_AUTH_DIGEST variable
	if (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) ||
		!isset($users[$data['username']])) {
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
		die('Wrong Credentials!');
		}
		// generate the valid response
	$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
	$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
	$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

	if ($data['response'] != $valid_response) {
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
		die('Wrong Credential!');
	}
		
	// ok, valid username & password
	global $username;
	$username=$data['username'];
	return true;
}

// function to parse the http auth header
function http_digest_parse($txt) {
    // protect against missing data
    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
    $data = array();
    $keys = implode('|', array_keys($needed_parts));

    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $data[$m[1]] = $m[3] ? $m[3] : $m[4];
        unset($needed_parts[$m[1]]);
    }

    return $needed_parts ? false : $data;
}
?>
