<?php
	session_start();

	$_SESSION['tab'] = (isset($_SESSION['tab']) ? $_SESSION['tab'] : '');
	$example         = file_exists('example.xml');
	$msgs            = array();
	$rcvName         = '';
	$rcvNum          = '';
	$share           = '';
	$thisVersion     = 'v0.1.0';
	$url             = (isset($_GET['url']) ? urldecode(htmlspecialchars($_GET['url'])) : '');

	if (isset($_POST['upload'])) {
		$_SESSION['tab'] = 'file';
		$msgs 			 = parseFile($_FILES['xmlfile']['tmp_name']);
	}
	if (isset($_POST['urlSubmit']) || $url) {
		$_SESSION['tab'] = 'URL';
		if (isset($_POST['urlSubmit'])) {$url = urldecode($_POST['url']);}
		if (strpos($url,'google.com') !== false) {
			preg_match('/[A-z_\-0-9]{33}/', $url, $ID);
			$url = 'https://drive.google.com/uc?id=' . $ID[0] . '&export=download';
		}
		$msgs  = parseFile($url);
		$share = '<br><br><strong>Share:</strong> ' . "<input id='share' type='text' value='" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . '?url=' . urlencode($url) . 
			"' onclick='this.select();'>" . '<button onclick="copyToClipboard(document.getElementById(\'share\').id); return false;">Copy Link</button>';
	}
	if (isset($_POST['runExample'])) {
		$_SESSION['tab'] = 'example';
		$msgs            = parseFile('example.xml');
	}

	function parseFile($filename) {
		$doc     = new DOMDocument;
		$file    = file_get_contents($filename);
		$reader  = new XMLReader();
		$results = preg_replace_callback('/(&#\d{5};){2}|(&#0;)/', function($matches){return convertToEmoji($matches[0]);}, $file);
		$tmpdocs = glob($_SERVER['TMPDIR'] . '/sms*.xml');
		$tmpfile = $_SERVER['TMPDIR'] . '/sms' . substr(md5(uniqid()),0,6) . '.xml';

		foreach($tmpdocs as $tmpdoc){
			$timeDiff = abs(time() - filemtime($tmpdoc))/(60*60);
			if(is_file($tmpdoc) && $timeDiff > 24) {unlink($tmpdoc);}
		}
		file_put_contents($tmpfile,$results);
		if (!$reader->open($tmpfile)) {die('Failed to open XML file.');}

		while($reader->read()) {
			if ($reader->nodeType == XMLReader::ELEMENT && ($reader->name == 'sms')) {
				if     ($reader->getAttribute('type') == 1) {$type = 'received';}
				elseif ($reader->getAttribute('type') == 2) {$type = 'sent';}
				else {$type = 'notype';}

				$msgs[] = array(
					'address'       => formatPhoneNumber($reader->getAttribute('address')),
					'contact_name'  => $reader->getAttribute('contact_name'),
					'date'          => $reader->getAttribute('date'),
					'readable_date' => $reader->getAttribute('readable_date'),
					'text'          => $reader->getAttribute('body'),
					'type'          => $type
				);
			} elseif ($reader->nodeType == XMLReader::ELEMENT && ($reader->name == 'mms')) {
				$body = '';
				$node = simplexml_import_dom($doc->importNode($reader->expand(), true));

				if     ($reader->getAttribute('msg_box') == 1) {$type = 'received';}
				elseif ($reader->getAttribute('msg_box') == 2) {$type = 'sent';}
				else {$type = 'notype';}

				foreach ($node->parts->part as $part) {
					if     ($part['ct'] == 'image/png')  {$body .= "<img src='data:image/png;base64, " . $part['data'] . "' alt='" . $part['name'] . "'><br>";}
					elseif ($part['ct'] == 'image/jpeg') {$body .= "<img src='data:image/jpeg;base64, " . $part['data'] . "' alt='" . $part['name'] . "'><br>";}
					elseif ($part['ct'] == 'text/plain') {$body .= $part['text'] . '<br>';}
				}

				if (!$GLOBALS['rcvNum']) {
					foreach ($node->addrs->addr as $addr) {
						if ($addr['type'] == '151') {
							$GLOBALS['rcvNum']  = formatPhoneNumber($addr['address']);
							$GLOBALS['rcvName'] = (string) ($addr['contact_name'] ?: $GLOBALS['rcvName']);
						}
					}
				}

				$msgs[] = array(
					'address'       => formatPhoneNumber($reader->getAttribute('address')),
					'contact_name'  => $reader->getAttribute('contact_name'),
					'date'          => $reader->getAttribute('date'),
					'readable_date' => $reader->getAttribute('readable_date'),
					'text'          => $body,
					'type'          => $type
				);
			}
		}

		$reader->close();
		unlink($tmpfile);
		array_multisort(array_column($msgs, 'date'), $msgs);
		return $msgs;
	}

	function convertToEmoji($matches){
		if ($matches == '&#0;') {return '';}
		else {
			$newStr     = str_replace('&#', '', $matches);
			$myEmoji    = explode(';', $newStr);
			$newStr     = dechex($myEmoji[0]) . dechex($myEmoji[1]);
			$newStr     = hex2bin($newStr);
			return iconv('UTF-16BE', 'UTF-8', $newStr);
		}
	}

	function formatPhoneNumber($number){
		$clean = preg_replace('/\D/', '', $number);
		preg_match('/^(\d{3})(\d{3})(\d{4})$/', $clean, $trimmed);
		if ($trimmed) {return '(' . $trimmed[1] . ') ' . $trimmed[2] . '-' . $trimmed[3];}
		else {return $number;}
	}

	function checkVersion($thisVersion) {
		$releasePath = 'https://github.com/dynamiccookies/SMS-XML-Parser/releases/latest';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/dynamiccookies/SMS-XML-Parser/releases/latest'); 
		curl_setopt($ch, CURLOPT_USERAGENT, 'dynamiccookies/SMS-XML-Parser');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$latestRelease = json_decode(curl_exec($ch), true)['tag_name'];
		curl_close($ch);
		if (strcmp($thisVersion, $latestRelease) < 0) {
			return ' <mark id="version" style="font-weight:bold;">A <a href="' . $releasePath . '" target="_blank">new release (' . $latestRelease . ')</a> is available.</mark>';
		} else if (strcmp($thisVersion, $latestRelease) > 0) {
			return ' <mark id="version"><strong style="color:red;">BETA VERSION (' . $thisVersion . ')</strong> - This version is not a <a href="' . $releasePath . '" target="_blank">supported release</a>.</mark>';
		} else {return '';}
	}
?>
<!doctype html>
<html>
    <head>
        <title>SMS XML Parser</title>
        <style>
			body {
				background-color: black;
				color: white;
				margin: auto;
				max-width: 90%;
			}
			img {
				border: 1px solid darkslategray;
				margin-top: 5px;
				max-width: 400px;
			}
			img:hover {
				transition: -ms-transform 1.5s, -webkit-transform 1.5s, transform 1.5s;
                -webkit-transition-delay: 1s;
                     -o-transition-delay: 1s;
                        transition-delay: 1s;
                -webkit-transition-timing-function: ease-in-out;
                     -o-transition-timing-function: ease-in-out;
                        transition-timing-function: ease-in-out;
                -webkit-transform: scale(1.5);
                   -moz-transform: scale(1.5);
                    -ms-transform: scale(1.5);
                     -o-transform: scale(1.5);
                        transform: scale(1.5);
				-webkit-transform-origin: top left;
    				-ms-transform-origin: top left;
	        			transform-origin: top left;
			}
			input[type='text'] {width: 253px;}

			#copied {
				color: crimson;
				display: none;
				font-weight: bold;
				margin: 10px auto;
			}
			#uploadForm {
				background-color: lightgrey;
				border: 5px solid darkgray;
				border-radius: 10px;
				color: black;
				display: none;
				margin: 20px auto;
				min-width: 400px;
				width: 50%;
			}
			#version {float: right;}

			.details {font-weight: bold;}
			.lds-ring {
				display: block;
				height: 80px;
				margin: auto;
				position: relative;
				width: 80px;
			}
			.lds-ring div {
				-webkit-animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
				        animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
				border: 8px solid #fff;
				border-color: #fff transparent transparent transparent;
				border-radius: 50%;
				-webkit-box-sizing: border-box;
				        box-sizing: border-box;
				display: block;
				height: 64px;
				margin: 8px;
				position: absolute;
				width: 64px;
			}
			.lds-ring div:nth-child(1) {
				-webkit-animation-delay: -0.45s;
				        animation-delay: -0.45s;
			}
			.lds-ring div:nth-child(2) {
				-webkit-animation-delay: -0.3s;
				        animation-delay: -0.3s;
			}
			.lds-ring div:nth-child(3) {
				-webkit-animation-delay: -0.15s;
				        animation-delay: -0.15s;
			}
			.lefttab {border-radius: 10px 0 0 0;}
			.received {
				background-color: lightgrey;
                -webkit-border-radius: 0 20px 20px;
                 -khtml-border-radius: 0 20px 20px;
                   -moz-border-radius: 0 20px 20px;
                        border-radius: 0 20px 20px;
				float: left;
			}
			.righttab {border-radius: 0 10px 0 0;}
			.sent {
				background-color: lightblue;
				-webkit-border-radius: 20px 0 20px 20px;
				 -khtml-border-radius: 20px 0 20px 20px;
				   -moz-border-radius: 20px 0 20px 20px;
				        border-radius: 20px 0 20px 20px;
				float: right;
			}
			.sent img:hover {
                -webkit-transform-origin: top right;
                    -ms-transform-origin: top right;
                        transform-origin: top right;
			}
			.sent .details {float: right;}
			.sent, .received {
				clear: both;
				color: black;
				margin: 10px;
				max-width: 75%;
				padding: 15px;
				text-align: justify;
			}
			.tabcontent {
				background-color: silver;
				border-radius: 0px 0px 10px 10px;
				display: none;
				padding: 100px 20px 50px;
				text-align: center;
			}
			.tablink {
				border: none;
				cursor: pointer;
				float: left;
				font-size: 17px;
				outline: none;
				padding: 14px 16px;
				margin: auto;
			}
			.tablink:hover {font-weight:bold!important;}
			.width33 {
				width: 33.33%;
				width: calc(100% / 3);
			}
			.width50 {width: 50%;}

			@-webkit-keyframes lds-ring {
				0% {
					-webkit-transform: rotate(0deg);
					        transform: rotate(0deg);
				}
				100% {
					-webkit-transform: rotate(360deg);
					        transform: rotate(360deg);
				}
			}
			@keyframes lds-ring {
				0% {
					-webkit-transform: rotate(0deg);
					        transform: rotate(0deg);
				}
				100% {
					-webkit-transform: rotate(360deg);
					        transform: rotate(360deg);
				}
			}
			@media print {
				body {
					background-color: white;
					color: black;
					font-size: .75em;
				}
				div {page-break-inside: avoid;}
				img {max-width: 300px;}
				#loading {display: none;}
				#uploadForm {display: none!important;}
				.sent, .received {
					margin: 2px 10px;
					max-width: 90%;
				}
			}
        </style>
    </head>
    <body>
		<div>SMS XML Parser <?php echo $thisVersion . checkVersion($thisVersion);?></div>
        <div id='loading' class='lds-ring'><div></div><div></div><div></div><div></div></div>
        <div id='uploadForm'>
			<button 
				class='tablink lefttab<?php echo ($example ? ' width33' : ' width50') ?>' 
				onclick="openTab('file', this, 'left')"
				<?php if (!$_SESSION['tab'] or $_SESSION['tab'] == 'file' or (!$example and $_SESSION['tab'] == 'example')) {echo " id='defaultTab'";}?>
			>Upload File</button>
			<button 
				class='tablink<?php echo ($example ? ' width33' : ' righttab width50') ?>' 
				onclick="openTab('URL', this, '<?php echo ($example ? 'middle' : 'right');?>')"
				<?php echo ($_SESSION['tab'] == 'URL' ? " id='defaultTab'":'');?>
			>Upload URL</button>
			<?php if ($example) {?>
				<button 
					class='tablink width33' 
					onclick="openTab('example', this, 'right')"
					<?php echo ($_SESSION['tab'] == 'example' ? " id='defaultTab'":'');?>
				>Example</button>
			<?php }?>
            <form id='file' class='tabcontent' name='fileUpload' method='post' action='' enctype='multipart/form-data'>
        		<input type='file' name='xmlfile' accept='.xml,xml/*,text/xml'>
        		<input type='submit' name='upload' value='Upload' id='submitFile' onclick='loading(this.id); return false;'>
    		</form>
    		<form id='URL' class='tabcontent' name='URLUpload' method='post' action='' enctype='multipart/form-data'>
    		    <input type='text' name='url' placeholder='Upload URL'>
    		    <input type='submit' name='urlSubmit' value='Upload' id='submitURL' onclick='loading(this.id); return false;'>
    		    <br><br><strong><a href='https://drive.google.com' target='_blank'>Google Drive</a> links supported!</strong>
    		    <? echo ($share ?: '');?>
    		    <br><span id='copied'>Copied!</span>
    		</form>
			<?php if ($example) {?>
				<form id='example' class='tabcontent' name='example' method='post' action='' enctype='multipart/form-data'>
					<input type='submit' name='runExample' value='Run Example' id='submitExample' onclick='loading(this.id); return false;'>
					<br><br><strong>Download and open the <a href='example.xml' download='example.xml'>example.xml</a> file to see the code before it's parsed.</strong>
				</form>
			<?php }?>
        </div>
        <?php if ($msgs){?>
            <h1>Text Message History</h1>
			<?php
				$urlRegEx = '/(https?:\/\/([\w_-]+(?:(?:\.[\w_-]+)+))([\w.,@?^=%&:\/~+#-]*[\w@?^=%&\/~+#-])?)/';
				foreach ($msgs as $msg) {
					echo "\t\t<div class='" . $msg['type'] . "'><span class='details'>" . $msg['readable_date'];
					if ($msg['type'] == 'received') {echo ($msg['contact_name'] == '(Unknown)' ? '' : ' - ' . $msg['contact_name']) . ' - ' . $msg['address'];}
					elseif ($msg['type'] == 'sent') {echo ' - ' . ($rcvName ?: 'Me') . ($rcvNum ? ' - ' . $rcvNum : '');}
					print_r('</span><br>' . preg_replace($urlRegEx, "<a href='$1' target='_blank'>$1</a>", $msg['text']) . "</div>\n");
				}
			?>
        <?php }?>
        <script>
			document.getElementById('loading').style.display    = 'none';
			document.getElementById('uploadForm').style.display = 'block';
			document.getElementById('defaultTab').click();
			
			function openTab(tabName,elmnt,position) {
				var i, tabcontent, tablinks;
				tabcontent = document.getElementsByClassName('tabcontent');
				for (i = 0; i < tabcontent.length; i++) {tabcontent[i].style.display = 'none';}
				tablinks = document.getElementsByClassName('tablink');
				for (i = 0; i < tablinks.length; i++) {
					tablinks[i].style.backgroundColor = '#daecee';
					tablinks[i].style.fontWeight      = 'normal';
					tablinks[i].style.borderBottom    = '1px solid';
					tablinks[i].style.borderTop       = 'none';
					tablinks[i].style.borderRight     = 'none';
					tablinks[i].style.borderLeft      = 'none';
				}
				document.getElementById(tabName).style.display = 'block';
				elmnt.style.backgroundColor = 'silver';
				elmnt.style.fontWeight      = 'bold';
				elmnt.style.borderBottom    = 'none';
				elmnt.style.borderTop       = '3px solid blue';
				if (position == 'left'  || position == 'middle') {elmnt.style.borderRight = '1px solid';}
				if (position == 'right' || position == 'middle') {elmnt.style.borderLeft  = '1px solid';}
			}
			function copyToClipboard(id) {
				var copyText = document.getElementById(id);
				copyText.select();
				copyText.setSelectionRange(0, 99999);
				document.execCommand('copy');
				document.getElementById('copied').style.display = 'block';
			}
			function loading(id) {
			    document.getElementById('loading').style.display = 'block';
			    document.getElementById(id).submit();
			}
        </script>
    </body>
</html>
