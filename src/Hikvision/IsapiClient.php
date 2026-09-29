<?php
declare(strict_types=1);

namespace App\Hikvision;

use App\Env;

final class IsapiClient
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $protocol,
        private readonly string $username,
        private readonly string $password
    ) {}

    public function deviceInfo(): array { return $this->request('GET', '/ISAPI/System/deviceInfo'); }

    public function capabilities(): array
    {
        $paths = [
            '/ISAPI/System/capabilities',
            '/ISAPI/AccessControl/capabilities?format=json',
            '/ISAPI/AccessControl/UserInfo/capabilities?format=json',
            '/ISAPI/AccessControl/CardInfo/capabilities?format=json',
            '/ISAPI/AccessControl/CaptureCardInfo/capabilities?format=json',
            '/ISAPI/AccessControl/CaptureFaceData/capabilities?format=json',
            '/ISAPI/Intelligent/FDLib/capabilities?format=json',
        ];
        $out = [];
        foreach ($paths as $path) {
            $r = $this->request('GET', $path);
            $out[$path] = ['http_status'=>$r['status'],'content_type'=>$r['content_type'],'data'=>$r['json'] ?? $r['body']];
        }
        return $out;
    }

    public function captureCard(): array
    {
        return $this->request('GET', '/ISAPI/AccessControl/CaptureCardInfo?format=json');
    }

    public function captureFace(): array
    {
        $r = $this->request('POST', '/ISAPI/AccessControl/CaptureFaceData?format=json', '{}', 'application/json');
        if ($r['status'] >= 200 && $r['status'] < 300) return $r;
        return $this->request('POST', '/ISAPI/AccessControl/CaptureFaceData', '{}', 'application/json');
    }

    public function upsertUser(array $userInfo): array
    {
        $body = json_encode(['UserInfo'=>$userInfo], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $create = $this->request('POST','/ISAPI/AccessControl/UserInfo/Record?format=json',$body,'application/json');
        if ($this->isSuccess($create)) return ['operation'=>'create','response'=>$create];
        $modify = $this->request('PUT','/ISAPI/AccessControl/UserInfo/Modify?format=json',$body,'application/json');
        return ['operation'=>'modify','response'=>$modify,'create_response'=>$create];
    }

    public function upsertCard(array $cardInfo): array
    {
        $body = json_encode(['CardInfo'=>$cardInfo], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $create = $this->request('POST','/ISAPI/AccessControl/CardInfo/Record?format=json',$body,'application/json');
        if ($this->isSuccess($create)) return ['operation'=>'create','response'=>$create];
        $modify = $this->request('PUT','/ISAPI/AccessControl/CardInfo/Modify?format=json',$body,'application/json');
        return ['operation'=>'modify','response'=>$modify,'create_response'=>$create];
    }

    public function upsertFace(string $faceUrl,string $fdid,string $faceLibType,string $fpid,string $name): array
    {
        $payload = ['faceURL'=>$faceUrl,'faceLibType'=>$faceLibType,'FDID'=>$fdid,'FPID'=>$fpid,'name'=>$name,'faceType'=>'normalFace','saveFacePic'=>true];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $create = $this->request('POST','/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json',$body,'application/json');
        if ($this->isSuccess($create)) return ['operation'=>'create','response'=>$create];

        $query = http_build_query(['format'=>'json','FDID'=>$fdid,'FPID'=>$fpid,'faceLibType'=>$faceLibType]);
        $modify = $this->request('PUT','/ISAPI/Intelligent/FDLib/FDSearch?'.$query,
            json_encode(['faceURL'=>$faceUrl,'name'=>$name], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'application/json');
        return ['operation'=>'modify','response'=>$modify,'create_response'=>$create];
    }

    public function download(string $urlOrPath): array
    {
        if (preg_match('#^https?://#i',$urlOrPath)) {
            $p = parse_url($urlOrPath);
            if (($p['host'] ?? '') === $this->host) {
                $path = ($p['path'] ?? '/') . (isset($p['query']) ? '?'.$p['query'] : '');
                return $this->request('GET',$path);
            }
            return $this->requestAbsolute('GET',$urlOrPath);
        }
        return $this->request('GET',$urlOrPath);
    }

    public function request(string $method,string $path,?string $body=null,?string $contentType=null): array
    {
        return $this->requestAbsolute($method,sprintf('%s://%s:%d',$this->protocol,$this->host,$this->port).$path,$body,$contentType);
    }

    private function requestAbsolute(string $method,string $url,?string $body=null,?string $contentType=null): array
    {
        $headers=['Accept: application/json, application/xml, image/jpeg, */*'];
        if ($contentType !== null) $headers[]='Content-Type: '.$contentType;
        $responseHeaders=[];
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_CUSTOMREQUEST=>strtoupper($method),
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_MAXREDIRS=>3,
            CURLOPT_CONNECTTIMEOUT=>Env::int('ISAPI_TIMEOUT',15),
            CURLOPT_TIMEOUT=>Env::int('ISAPI_TIMEOUT',15),
            CURLOPT_HTTPAUTH=>CURLAUTH_DIGEST,
            CURLOPT_USERPWD=>$this->username.':'.$this->password,
            CURLOPT_SSL_VERIFYPEER=>Env::bool('ISAPI_SSL_VERIFY',false),
            CURLOPT_SSL_VERIFYHOST=>Env::bool('ISAPI_SSL_VERIFY',false)?2:0,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_HEADERFUNCTION=>static function($curl,string $line) use (&$responseHeaders): int {
                $len=strlen($line); $line=trim($line);
                if ($line!=='' && str_contains($line,':')) { [$n,$v]=explode(':',$line,2); $responseHeaders[strtolower(trim($n))]=trim($v); }
                return $len;
            }
        ]);
        if ($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $responseBody=curl_exec($ch);
        if ($responseBody===false) { $e=curl_error($ch); curl_close($ch); throw new \RuntimeException('Falha ISAPI: '.$e); }
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $ct=(string)(curl_getinfo($ch,CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($ch);
        $json=null;
        if (str_contains(strtolower($ct),'json') || str_starts_with(ltrim($responseBody),'{')) {
            $d=json_decode($responseBody,true); if (is_array($d)) $json=$d;
        }
        return ['status'=>$status,'content_type'=>$ct,'headers'=>$responseHeaders,'body'=>$responseBody,'json'=>$json];
    }

    public function isSuccess(array $response): bool
    {
        if (($response['status']??0)<200 || ($response['status']??0)>=300) return false;
        $json=$response['json']??null;
        return !(is_array($json) && isset($json['statusCode'])) || (int)$json['statusCode']===1;
    }
}
