<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Database;
use App\Env;
use App\Http\Json;
use App\Repositories\DeviceRepository;
use App\Repositories\PersonRepository;
use App\Services\DeviceService;
use App\Services\SyncService;

function apiPath(): string {
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
    $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'');
    $base=rtrim(str_replace('/index.php','',$script),'/');
    if($base!==''&&str_starts_with($path,$base))$path=substr($path,strlen($base))?:'/';
    return '/'.ltrim($path,'/');
}
function requireApiKey(): void {
    $configured=Env::get('API_KEY');if($configured===null)return;
    if(!hash_equals($configured,$_SERVER['HTTP_X_API_KEY']??''))Json::send(['error'=>'unauthorized'],401);
}
function requireFields(array $d,array $fields): void {
    foreach($fields as $f)if(!isset($d[$f])||trim((string)$d[$f])==='')throw new InvalidArgumentException("Campo obrigatório: {$f}");
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');$path=apiPath();
try{
    $persons=new PersonRepository();$devices=new DeviceRepository();$deviceService=new DeviceService();$sync=new SyncService();

    if($method==='GET'&&$path==='/')Json::send(['name'=>'hikivision_facial_cadastro_modulo','mode'=>'proprietary-direct-isapi','hikcentral_required'=>false,'version'=>'0.1.0']);
    if($method==='GET'&&$path==='/api/health'){
        $db='ok';try{Database::pdo()->query('SELECT 1');}catch(Throwable $e){$db='error: '.$e->getMessage();}
        Json::send(['status'=>$db==='ok'?'ok':'degraded','database'=>$db,'time'=>date(DATE_ATOM)],$db==='ok'?200:503);
    }

    requireApiKey();

    if($method==='GET'&&$path==='/api/devices')Json::send(['devices'=>$devices->all()]);
    if($method==='POST'&&$path==='/api/devices'){$b=Json::body();requireFields($b,['name','host','username','password']);Json::send(['device'=>$devices->create($b)],201);}
    if($method==='GET'&&preg_match('#^/api/devices/(\d+)$#',$path,$m)){$d=$devices->find((int)$m[1]);$d?Json::send(['device'=>$d]):Json::send(['error'=>'not_found'],404);}
    if($method==='POST'&&preg_match('#^/api/devices/(\d+)/test$#',$path,$m))Json::send($deviceService->test((int)$m[1]));
    if($method==='GET'&&preg_match('#^/api/devices/(\d+)/capabilities$#',$path,$m))Json::send(['capabilities'=>$deviceService->capabilities((int)$m[1])]);
    if($method==='POST'&&preg_match('#^/api/devices/(\d+)/capture-card$#',$path,$m)){$b=Json::body();Json::send($deviceService->captureCard((int)$m[1],isset($b['person_id'])?(int)$b['person_id']:null));}

    if($method==='GET'&&$path==='/api/persons')Json::send(['persons'=>$persons->all()]);
    if($method==='POST'&&$path==='/api/persons'){$b=Json::body();requireFields($b,['name']);Json::send(['person'=>$persons->create($b)],201);}
    if($method==='GET'&&preg_match('#^/api/persons/(\d+)$#',$path,$m)){$p=$persons->find((int)$m[1]);$p?Json::send(['person'=>$p]):Json::send(['error'=>'not_found'],404);}
    if($method==='POST'&&preg_match('#^/api/persons/(\d+)/card$#',$path,$m)){$b=Json::body();requireFields($b,['card_no']);Json::send(['person'=>$persons->addCard((int)$m[1],(string)$b['card_no'],$b['card_type']??null,isset($b['source_device_id'])?(int)$b['source_device_id']:null)]);}

    if($method==='POST'&&preg_match('#^/api/persons/(\d+)/sync$#',$path,$m)){$b=Json::body();$ids=is_array($b['device_ids']??null)?$b['device_ids']:[];Json::send($sync->enqueue((int)$m[1],$ids),202);}
    if($method==='GET'&&$path==='/api/sync/jobs')Json::send(['jobs'=>$sync->jobs((int)($_GET['limit']??100))]);
    if($method==='POST'&&$path==='/api/sync/process'){$b=Json::body();Json::send($sync->process((int)($b['limit']??10)));}

    Json::send(['error'=>'route_not_found','method'=>$method,'path'=>$path],404);
}catch(InvalidArgumentException $e){Json::send(['error'=>'validation_error','message'=>$e->getMessage()],422);}
catch(Throwable $e){Json::send(['error'=>'internal_error','message'=>$e->getMessage(),'type'=>Env::get('APP_ENV')==='development'?get_class($e):null],500);}
