<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DeviceRepository;
use App\Repositories\PersonRepository;

final class DeviceService
{
    public function __construct(
        private readonly DeviceRepository $devices = new DeviceRepository(),
        private readonly PersonRepository $persons = new PersonRepository()
    ) {}

    public function test(int $deviceId): array
    {
        $client=$this->devices->client($deviceId);
        $r=$client->deviceInfo();
        $data=$r['json']??$r['body'];
        $model=$this->findString($data,['model','deviceModel']);
        $serial=$this->findString($data,['serialNumber','serialNo']);
        $firmware=$this->findString($data,['firmwareVersion']);
        $this->devices->updateDetectedInfo($deviceId,$model,$serial,$firmware);
        return ['online'=>$r['status']>=200&&$r['status']<300,'http_status'=>$r['status'],'model'=>$model,'serial_no'=>$serial,'firmware'=>$firmware,'raw'=>$data];
    }

    public function capabilities(int $deviceId): array
    {
        $caps=$this->devices->client($deviceId)->capabilities();
        $this->devices->updateCapabilities($deviceId,$caps);
        return $caps;
    }

    public function captureCard(int $deviceId,?int $personId=null): array
    {
        $r=$this->devices->client($deviceId)->captureCard();
        $data=$r['json']??$r['body'];
        $cardNo=$this->findString($data,['cardNo']);
        $cardType=$this->findString($data,['cardType']);
        if(!$cardNo)return ['captured'=>false,'http_status'=>$r['status'],'raw'=>$data];

        $out=['captured'=>true,'card_no'=>$cardNo,'card_type'=>$cardType,'http_status'=>$r['status']];
        if($personId!==null){
            if(!$this->persons->find($personId))throw new \RuntimeException('Pessoa não encontrada.');
            $out['person']=$this->persons->addCard($personId,$cardNo,$cardType,$deviceId);
        }
        return $out;
    }

    private function findString(mixed $value,array $keys): ?string
    {
        if(is_string($value)){
            foreach($keys as $key){
                if(preg_match('/<'.preg_quote($key,'/').'[^>]*>(.*?)<\/'.preg_quote($key,'/').'>/si',$value,$m))return trim(strip_tags($m[1]));
            }
            return null;
        }
        if(!is_array($value))return null;
        foreach($keys as $key)if(array_key_exists($key,$value)&&is_scalar($value[$key]))return (string)$value[$key];
        foreach($value as $child)if(is_array($child)){ $found=$this->findString($child,$keys); if($found!==null&&$found!=='')return $found; }
        return null;
    }
}
