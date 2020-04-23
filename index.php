<?php
    session_start();
    $msgs            = array();
    $share           = '';
    $rcvNum          = '';
    $rcvName         = '';
	$example		 = file_exists('example.xml');
    $url             = (isset($_GET['url']) ? urldecode(htmlspecialchars($_GET['url'])) : '');
    $_SESSION['tab'] = (isset($_SESSION['tab']) ? $_SESSION['tab'] : '');

    if (isset($_POST['upload'])) {
		$_SESSION['tab'] = 'file';
		$msgs = parseFile($_FILES['xmlfile']['tmp_name']);
    }
    if (isset($_POST['urlSubmit']) || $url) {
        if (isset($_POST['urlSubmit'])) {$url = urldecode($_POST['url']);}
        $_SESSION['tab'] = 'URL';
        if (strpos($url,'google.com') !== false) {
            preg_match('/[A-z_\-0-9]{33}/', $url, $ID);
            $url = 'https://drive.google.com/uc?id=' . $ID[0] . '&export=download';
        }
        $share = '<br><br><strong>Share:</strong> ' . 
            "<input id='share' type='text' value='" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . '?url=' . urlencode($url) . "' onclick='this.select();'>" . 
            '<button onclick="copyToClipboard(document.getElementById(\'share\').id); return false;">Copy Link</button>';
        $msgs = parseFile($url);
    }
	if (isset($_POST['runExample'])) {
		$_SESSION['tab'] = 'example';
		$msgs = parseFile('example.xml');
	}

    function parseFile($filename) {
        $file    = file_get_contents($filename);
        $results = preg_replace_callback('/(&#\d{5};){2}|(&#0;)/', function($matches){return convertToEmoji($matches[0]);}, $file);
        $tmpfile = $_SERVER['TMPDIR'] . '/sms' . substr(md5(uniqid()),0,6) . '.xml';
        $reader  = new XMLReader();
        $doc     = new DOMDocument;
        $tmpdocs = glob($_SERVER['TMPDIR'] . '/sms*.xml');

        foreach($tmpdocs as $tmpdoc){
            $timeDiff = abs(time() - filemtime($tmpdoc))/(60*60);
            if(is_file($tmpdoc) && $timeDiff > 24)
            unlink($tmpdoc);
        }

        file_put_contents($tmpfile,$results);
        if (!$reader->open($tmpfile)) {die('Failed to open XML file.');}

        while($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && ($reader->name == 'sms')) {
    
                if ($reader->getAttribute('type') == 1)     {$type = 'received';}
                elseif ($reader->getAttribute('type') == 2) {$type = 'sent';}
                else {$type = 'notype';}
    
    			$msgs[] = array(
    				'type'          => $type,
    				'text'          => $reader->getAttribute('body'),
    				'date'          => $reader->getAttribute('date'),
    				'address'       => formatPhoneNumber($reader->getAttribute('address')),
    				'contact_name'  => $reader->getAttribute('contact_name'),
    				'readable_date' => $reader->getAttribute('readable_date')
    			);
    
            } elseif ($reader->nodeType == XMLReader::ELEMENT && ($reader->name == 'mms')) {
    
                if ($reader->getAttribute('msg_box') == 1)     {$type = 'received';}
                elseif ($reader->getAttribute('msg_box') == 2) {$type = 'sent';}
                else {$type = 'notype';}
    
    			$body = '';
                $node = simplexml_import_dom($doc->importNode($reader->expand(), true));
                foreach ($node->parts->part as $part) {
                    if  ($part['ct'] == 'image/png') 	{$body .= "<img src='data:image/png;base64, " . $part['data'] . "' alt='" . $part['name'] . "'><br>";}
    				elseif ($part['ct'] == 'image/jpeg'){$body .= "<img src='data:image/jpeg;base64, " . $part['data'] . "' alt='" . $part['name'] . "'><br>";}
    				elseif ($part['ct'] == 'text/plain'){$body .= $part['text'] . '<br>';}
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
    				'type'          => $type,
    				'text'          => $body,
    				'date'          => $reader->getAttribute('date'),
    				'address'       => formatPhoneNumber($reader->getAttribute('address')),
    				'contact_name'  => $reader->getAttribute('contact_name'),
    				'readable_date' => $reader->getAttribute('readable_date')
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
            	-moz-transform: scale(1.5);
            	-ms-transform: scale(1.5);
            	-o-transform: scale(1.5);
            	-webkit-transform: scale(1.5);
                transition: 
					-ms-transform 1.5s, 
					-moz-transform 1.5s, 
					-o-transform 1.5s, 
					-webkit-transform 1.5s, 
					transform 1.5s;
                transition-delay: 1s;
                transition-timing-function: ease-in-out;
            	transform: scale(1.5);
                transform-origin: top left;
            }
            input[type='text'] {width: 253px;}
            .details {font-weight: bold;}
            .received {
            	-khtml-border-radius: 0 20px 20px;
            	-moz-border-radius: 0 20px 20px;
            	-webkit-border-radius: 0 20px 20px;
            	background-color: lightgrey;
            	border-radius: 0 20px 20px;
            	float: left;
            }
            .sent, .received {
            	clear: both;
            	color: black;
            	margin: 10px;
            	max-width: 75%;
            	padding: 15px;
            	text-align: justify;
            }
            .sent {
            	-khtml-border-radius: 20px 0 20px 20px;
            	-moz-border-radius: 20px 0 20px 20px;
            	-webkit-border-radius: 20px 0 20px 20px;
            	background-color: lightblue;
            	border-radius: 20px 0 20px 20px;
            	float: right;
            }
            .sent .details {float: right;}
            .sent img:hover {transform-origin: top right;}
            #copied {
                display: none;
                font-weight: bold;
                color: crimson;
                margin: 10px auto;
            }
            #uploadForm {
				display: none;
				background-color: lightgrey;
				border: 5px solid darkgray;
				border-radius: 10px;
				color: black;
				margin: 20px auto;
				width: 50%;
				min-width: 400px;
            }
			.tablink {
				float: left;
				border: none;
				outline: none;
				cursor: pointer;
				padding: 14px 16px;
				font-size: 17px;
				margin: auto;
			}
			.width33 {
				width: 33.33%;
				width: calc(100% / 3);
			}
			.width50 {width: 50%;}
			.tablink:hover {font-weight:bold!important;}
        	.tabcontent {
        		display: none;
        		padding: 100px 20px 50px;
        		text-align: center;
        		background-color: silver;
        		border-radius: 0px 0px 10px 10px;
        	}
        	.lefttab {border-radius: 10px 0 0 0;}
        	.righttab {border-radius: 0 10px 0 0;}
            .lds-ring {
                margin: auto;
                display: block;
                position: relative;
                width: 80px;
                height: 80px;
            }
            .lds-ring div {
                box-sizing: border-box;
                display: block;
                position: absolute;
                width: 64px;
                height: 64px;
                margin: 8px;
                border: 8px solid #fff;
                border-radius: 50%;
                animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
                border-color: #fff transparent transparent transparent;
            }
            .lds-ring div:nth-child(1) {animation-delay: -0.45s;}
            .lds-ring div:nth-child(2) {animation-delay: -0.3s;}
            .lds-ring div:nth-child(3) {animation-delay: -0.15s;}
            @keyframes lds-ring {
                0%   {transform: rotate(0deg);}
                100% {transform: rotate(360deg);}
            }

            @media print {
            	body {
            		background-color: white;
            		color: black;
					font-size: .75em;
            	}
            	div {page-break-inside: avoid;}
            	#uploadForm {display: none!important;}
            	#loading {display: none;}
				.sent, .received {
					margin: 2px 10px;
					max-width: 90%;
				}
				img {max-width: 300px;}
            }
        </style>
    </head>
    <body>
        <div id='loading' class='lds-ring'><div></div><div></div><div></div><div></div></div>
        <div id='uploadForm'>
			<button class='tablink lefttab<?php echo ($example ? ' width33' : ' width50') ?>' onclick="openTab('file', this, 'left')"<?php echo (!$_SESSION['tab'] || $_SESSION['tab'] == 'file' ? " id='defaultTab'":'');?>>Upload File</button>
			<button class='tablink<?php echo ($example ? ' width33' : ' righttab width50') ?>' onclick="openTab('URL', this, '<?php echo ($example ? 'middle' : 'right');?>')"<?php echo ($_SESSION['tab'] == 'URL' ? " id='defaultTab'":'');?>>Upload URL</button>
			<?php if ($example) {?>
				<button class='tablink width33' onclick="openTab('example', this, 'right')"<?php echo ($_SESSION['tab'] == 'example' ? " id='defaultTab'":'');?>>Example</button>
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
			document.getElementById('loading').style.display = 'none';
			document.getElementById('uploadForm').style.display = 'block';
			document.getElementById('defaultTab').click();
			function openTab(tabName,elmnt,position) {
				var i, tabcontent, tablinks;
				tabcontent = document.getElementsByClassName('tabcontent');
				for (i = 0; i < tabcontent.length; i++) {tabcontent[i].style.display = 'none';}
				tablinks = document.getElementsByClassName('tablink');
				for (i = 0; i < tablinks.length; i++) {
					tablinks[i].style.backgroundColor = '#daecee';
					tablinks[i].style.fontWeight = 'normal';
					tablinks[i].style.borderBottom = '1px solid';
					tablinks[i].style.borderTop = 'none';
					tablinks[i].style.borderRight = 'none';
					tablinks[i].style.borderLeft = 'none';
				}
				document.getElementById(tabName).style.display = 'block';
				elmnt.style.backgroundColor = 'silver';
				elmnt.style.fontWeight = 'bold';
				elmnt.style.borderBottom = 'none';
				elmnt.style.borderTop = '3px solid blue';
				if (position == 'left'  || position == 'middle') {elmnt.style.borderRight = '1px solid';}
				if (position == 'right' || position == 'middle') {elmnt.style.borderLeft  = '1px solid';}
			}
			function copyToClipboard(id) {
				var copyText = document.getElementById(id);
				copyText.select();
				copyText.setSelectionRange(0, 99999)
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
