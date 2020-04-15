<?php

    $msgs = array();
    $rcvNum = '';

    if (isset($_POST['upload'])) {$msgs = parseFile($_FILES['xmlfile']['tmp_name']);}
    if (isset($_POST['urlSubmit'])) {
        $url = $_POST['url'];
        if (strpos($url,'google.com') !== false) {
            preg_match('/[A-z_0-9]{33}/', $url, $ID);
            $url = 'https://drive.google.com/uc?id=' . $ID[0] . '&export=download';
        }
        $msgs = parseFile($url);
    }

    function parseFile($filename) {
        $file    = file_get_contents($filename);
        $results = preg_replace_callback('/(&#\d{5};){2}/', function($matches){return convertToEmoji($matches);}, $file);
        $tmpfile = $_SERVER['TMPDIR'] . '/' . substr(md5(uniqid()),0,6) . '.xml';
        $reader  = new XMLReader();
        $doc     = new DOMDocument;

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

				global $rcvNum;
    			if (!$rcvNum) {foreach ($node->addrs->addr as $addr) {if ($addr['type'] == '151') {$rcvNum = formatPhoneNumber($addr['address']);}}}
    
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
        $newStr     = $matches[0];
        $newStr     = str_replace('&#', '', $newStr);
        $myEmoji    = explode(';', $newStr);
        $newStr     = dechex($myEmoji[0]) . dechex($myEmoji[1]);
        $newStr     = hex2bin($newStr);
        return iconv('UTF-16BE', 'UTF-8', $newStr);
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
            form {margin: 20px;}
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
            #uploadForm {
                background-color: #eee;
                border-radius: 10px;
                color: black;
                margin: 20px;
                padding: 20px;
                text-align: center;
            }

            @media print {
            	body {
            		background-color: white;
            		color: black;
            	}
            	div {page-break-inside: avoid;}
            	#uploadForm {display: none;}
            }
        </style>
    </head>
    <body>
        <div id='uploadForm'>
            <form name='fileUpload' method='post' action='' enctype='multipart/form-data'>
        		<input type='file' name='xmlfile' accept='.xml,xml/*,text/xml'>
        		<input type='submit' name='upload' value='Upload'>
    		</form>
    		<form name='URLUpload' method='post' action='' enctype='multipart/form-data'>
    		    <input type='text' name='url'>
    		    <input type='submit' name='urlSubmit' value='Upload'>
    		</form>
        </div>
        <?php if ($msgs){?>
            <h1>Text Message History</h1>
            <?php
    			foreach ($msgs as $msg) {
    				echo "\t\t<div class='" . $msg['type'] . "'><span class='details'>" . $msg['readable_date'];
    				if ($msg['type'] == 'received') {echo ' - ' . $msg['contact_name'] . ' - ' . $msg['address'];}
    				elseif ($msg['type'] == 'sent') {echo ' - Me' . ($rcvNum ? ' - ' . $rcvNum : '');}
                    print_r('</span><br>' . $msg['text'] . "</div>\n");
    			}
    		?>
        <?php }?>
    </body>
</html>
