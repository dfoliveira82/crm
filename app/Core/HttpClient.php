<?php
declare(strict_types=1);
namespace App\Core;
final class HttpClient {
    public function request(string $method,string $url,array $headers=[],?string $body=null): array {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_TIMEOUT=>max(3,(int)Env::get('RM_TIMEOUT',15)),CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_SSL_VERIFYPEER=>Env::bool('RM_VERIFY_SSL',true),CURLOPT_SSL_VERIFYHOST=>Env::bool('RM_VERIFY_SSL',true)?2:0]);
        if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $response=curl_exec($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($response===false) throw new \RuntimeException('Falha de comunicação com o serviço de autenticação: '.$error);
        return ['status'=>$status,'body'=>(string)$response];
    }
}
