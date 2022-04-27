<?php
	$ch = curl_init("https://ssapi.ru/ip.php");
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT_MS => 2500,
		CURLOPT_PROXY => "37.46.128.146:3128",
		CURLOPT_PROXYTYPE => CURLPROXY_HTTP
	]);
	$res = curl_exec($ch);
	curl_close($ch);

	var_dump(curl_error($ch));