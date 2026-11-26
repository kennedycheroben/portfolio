<?php
declare(strict_types=1);

[$script,$listenPort,$backendPort,$certificate] = $argv;
$context = stream_context_create(['ssl'=>[
    'local_cert'=>$certificate,
    'crypto_method'=>STREAM_CRYPTO_METHOD_TLS_SERVER,
    'allow_self_signed'=>true,
]]);
$server = stream_socket_server('tcp://127.0.0.1:' . (int)$listenPort,$errno,$error,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$context);
if (!is_resource($server)) { fwrite(STDERR,"TLS proxy failed: $error ($errno) cert=" . (is_file($certificate)?'yes':'no') . "\n"); exit(2); }
while (true) {
    $client = @stream_socket_accept($server,1);
    if (!is_resource($client)) continue;
    fwrite(STDERR,"TLS client accepted\n");
    stream_set_timeout($client,5);
    if (@stream_socket_enable_crypto($client,true,STREAM_CRYPTO_METHOD_TLS_SERVER)!==true) { fclose($client); continue; }
    $request = '';
    while (!str_contains($request,"\r\n\r\n") && !feof($client)) {
        $chunk = fread($client,8192);
        if (!is_string($chunk) || $chunk === '') break;
        $request .= $chunk;
        if (strlen($request) > 1048576) break;
    }
    $split = strpos($request,"\r\n\r\n");
    if ($split === false) { fclose($client); continue; }
    $headers = substr($request,0,$split+4);
    $body = substr($request,$split+4);
    if (preg_match('/^Content-Length:\s*(\d+)/im',$headers,$match)) {
        $length=(int)$match[1];
        while (strlen($body)<$length && !feof($client)) { $chunk=fread($client,min(8192,$length-strlen($body))); if(!is_string($chunk)||$chunk==='')break; $body.=$chunk; }
        $body=substr($body,0,$length);
    }
    $headers = preg_replace('/^Connection:.*\r\n/im','',$headers);
    $headers = preg_replace('/^Host:.*\r\n/im','Host: localhost\r\n',$headers);
    $headers = rtrim($headers,"\r\n") . "\r\nConnection: close\r\n\r\n";
    $backend = @stream_socket_client('tcp://127.0.0.1:' . (int)$backendPort,$backendErrno,$backendError,5);
    if (!is_resource($backend)) { fwrite(STDERR,"backend failed $backendError\n"); fwrite($client,"HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\nConnection: close\r\n\r\n"); fclose($client); continue; }
    stream_set_timeout($backend,5);
    fwrite($backend,$headers.$body);
    $response='';
    while (!feof($backend)) { $chunk=fread($backend,8192); if(!is_string($chunk)||$chunk==='')break; $response.=$chunk; if(strlen($response)>2097152)break; }
    fwrite($client,$response);
    fclose($backend);
    fclose($client);
}
fclose($server);
