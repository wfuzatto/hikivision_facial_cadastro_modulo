<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Env;
use App\Repositories\DeviceRepository;
use App\Repositories\PersonRepository;

final class SyncService
{
    public function __construct(
        private readonly DeviceRepository $devices = new DeviceRepository(),
        private readonly PersonRepository $persons = new PersonRepository()
    ) {}

    public function enqueue(int $personId,array $deviceIds): array
    {
        if(!$this->persons->find($personId))throw new \RuntimeException('Pessoa não encontrada.');
        if($deviceIds===[])$deviceIds=array_map(fn(array $d)=>(int)$d['id'],array_filter($this->devices->all(),fn(array $d)=>(int)$d['active']===1));
        $pdo=Database::pdo();
        $s=$pdo->prepare("INSERT INTO hfc_sync_jobs(person_id,device_id,action,status,next_attempt_at) VALUES(?,?,'upsert','queued',NOW())");
        $created=[];
        foreach(array_unique(array_map('intval',$deviceIds)) as $deviceId){
            if(!$this->devices->find($deviceId))continue;
            $s->execute([$personId,$deviceId]);$created[]=(int)$pdo->lastInsertId();
        }
        return ['queued_jobs'=>$created,'count'=>count($created)];
    }

    public function jobs(int $limit=100): array
    {
        $limit=max(1,min($limit,500));
        return Database::pdo()->query("SELECT j.*,p.person_code,p.name person_name,d.name device_name FROM hfc_sync_jobs j JOIN hfc_persons p ON p.id=j.person_id JOIN hfc_devices d ON d.id=j.device_id ORDER BY j.id DESC LIMIT {$limit}")->fetchAll();
    }

    public function process(int $limit=10): array
    {
        $limit=max(1,min($limit,50));
        $s=Database::pdo()->prepare("SELECT * FROM hfc_sync_jobs WHERE status IN('queued','error') AND attempts<? AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) ORDER BY id LIMIT {$limit}");
        $s->execute([Env::int('SYNC_MAX_ATTEMPTS',8)]);
        $results=[];
        foreach($s->fetchAll() as $job)$results[]=$this->processJob($job);
        return ['processed'=>count($results),'results'=>$results];
    }

    private function processJob(array $job): array
    {
        $pdo=Database::pdo();
        $pdo->prepare("UPDATE hfc_sync_jobs SET status='running',attempts=attempts+1,started_at=NOW() WHERE id=?")->execute([$job['id']]);
        try{
            $person=$this->persons->find((int)$job['person_id']);
            if(!$person)throw new \RuntimeException('Pessoa não encontrada.');
            $client=$this->devices->client((int)$job['device_id']);

            $user=$client->upsertUser([
                'employeeNo'=>(string)$person['person_code'],
                'name'=>(string)$person['name'],
                'userType'=>'normal',
                'Valid'=>['enable'=>true,'beginTime'=>date('Y-m-d\TH:i:s',strtotime($person['valid_from'])),'endTime'=>date('Y-m-d\TH:i:s',strtotime($person['valid_to']))],
                'doorRight'=>'1',
                'RightPlan'=>[['doorNo'=>1,'planTemplateNo'=>'1']]
            ]);
            if(!$client->isSuccess($user['response']))throw new \RuntimeException('Falha ao sincronizar pessoa.');

            $cards=[];
            foreach($person['cards'] as $card){
                $r=$client->upsertCard(['employeeNo'=>(string)$person['person_code'],'cardNo'=>(string)$card['card_no'],'cardType'=>'normalCard']);
                if(!$client->isSuccess($r['response']))throw new \RuntimeException('Falha ao sincronizar cartão '.$card['card_no'].'.');
                $cards[]=['card_no'=>$card['card_no'],'operation'=>$r['operation'],'http_status'=>$r['response']['status']??null];
            }

            $payload=['user'=>['operation'=>$user['operation'],'http_status'=>$user['response']['status']??null],'cards'=>$cards];
            $pdo->prepare("UPDATE hfc_sync_jobs SET status='success',finished_at=NOW(),last_error=NULL,result_json=? WHERE id=?")->execute([json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$job['id']]);
            return ['job_id'=>(int)$job['id'],'status'=>'success','result'=>$payload];
        }catch(\Throwable $e){
            $attempts=(int)$job['attempts']+1;
            $delay=min(60,max(1,2**min($attempts,6)));
            $pdo->prepare("UPDATE hfc_sync_jobs SET status='error',finished_at=NOW(),last_error=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id=?")->execute([$e->getMessage(),$delay,$job['id']]);
            return ['job_id'=>(int)$job['id'],'status'=>'error','error'=>$e->getMessage()];
        }
    }
}
