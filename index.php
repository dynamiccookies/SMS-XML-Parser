<?php

    /********** Enter Your Values Here **********/

    $SMSXMLFilename = '';   /* Your Filename */

    /******* Don't Modify Below This Line *******/

    $file    = file_get_contents($SMSXMLFilename);
    $results = preg_replace_callback('/(&#\d{5};){2}/', function($matches){return convertToEmoji($matches);}, $file);
    $tmpfile = $_SERVER['TMPDIR'] . '/' . substr(md5(uniqid()),0,6) . '.xml';
    $reader  = new XMLReader();
    $doc     = new DOMDocument;
    $msgs    = array();
	$rcvNum	 = '';

    file_put_contents($tmpfile,$results);

    if (!$reader->open($tmpfile)) {die('Failed to open XML file.');}

    while($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT && ($reader->name == 'sms')) {

            if ($reader->getAttribute('type') == 1)     {$type = 'received';}
            elseif ($reader->getAttribute('type') == 2) {$type = 'sent';}
            else {$type = '';}

			$msgs[] = array(
				'type'=>$type,
				'text'=>$reader->getAttribute('body'),
				'date'=>$reader->getAttribute('date'),
				'address'=>formatPhoneNumber($reader->getAttribute('address')),
				'contact_name'=>$reader->getAttribute('contact_name'),
				'readable_date'=>$reader->getAttribute('readable_date')
			);

        } elseif ($reader->nodeType == XMLReader::ELEMENT && ($reader->name == 'mms')) {

            if ($reader->getAttribute('msg_box') == 1)     {$type = 'received';}
            elseif ($reader->getAttribute('msg_box') == 2) {$type = 'sent';}
            else {$type = '';}

			$body = '';
            $node = simplexml_import_dom($doc->importNode($reader->expand(), true));
            foreach ($node->parts->part as $part) {
                if ($part['ct'] == 'image/png') 	{$body .= "<img src='data:image/png;base64, " . $part['data'] . "'><br>";}
				elseif ($part['ct'] == 'image/jpeg'){$body .= "<img src='data:image/jpeg;base64, " . $part['data'] . "'><br>";}
				elseif ($part['ct'] == 'text/plain'){$body .= $part['text'] . '<br>';}
            }

			if (!$rcvNum) {foreach ($node->addrs->addr as $addr) {if ($addr['type'] == '151') {$rcvNum = formatPhoneNumber($addr['address']);}}}

			$msgs[] = array(
				'type'=>$type,
				'text'=>$body,
				'date'=>$reader->getAttribute('date'),
				'address'=>formatPhoneNumber($reader->getAttribute('address')),
				'contact_name'=>$reader->getAttribute('contact_name'),
				'readable_date'=>$reader->getAttribute('readable_date')
			);
        }
    }

    $reader->close();
	unlink($tmpfile);
    array_multisort(array_column($msgs, 'date'), $msgs);

    function convertToEmoji($matches){
        $newStr     = $matches[0];
        $newStr     = str_replace('&#', '', $newStr);
        $myEmoji    = explode(';', $newStr);
        $newStr     = dechex($myEmoji[0]) . dechex($myEmoji[1]);
        $newStr     = hex2bin($newStr);
        return iconv('UTF-16BE', 'UTF-8', $newStr);
    }

    function formatPhoneNumber($number){
        $number = preg_replace('/\D/', '', $number);
        preg_match('/(\d{3})(\d{3})(\d{4})$/', $number, $trimmed);
        if ($trimmed) {return '(' . $trimmed[1] . ') ' . $trimmed[2] . '-' . $trimmed[3];}
        else {return '(' . $number . ')';}
    }
?>
<!doctype html>
<html>
    <head>
        <title>SMS XML Parser</title>
        <link rel='stylesheet' type='text/css' href='phpstyle.css'>
    </head>
    <body>
        <h1>Text Message History</h1>
        <?php
			foreach ($msgs as $msg) {
				echo "\t\t<div class='" . ($msg['type']?:'notype') . "'><span class='details'>" . $msg['readable_date'];

				if ($msg['type'] == 'received') {echo ' - ' . $msg['contact_name'] . ' - ' . $msg['address'];}
				elseif ($msg['type'] == 'sent') {echo ' - Me' . ($rcvNum ? ' - ' . $rcvNum : '');}

				print_r('</span><br>' . $msg['text'] . "</div>\n");
			}
		?>
    </body>
</html>
