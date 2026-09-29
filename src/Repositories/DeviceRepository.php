<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Crypto;
use App\Database;
use App\Hikvision\IsapiClient;

final class DeviceRepository
{
    public function all(): array
    {
        $rows=Database::pdo()->query('SELECT id,uuid,name,host,port,protocol,username,model,serial_no,firmware,role,face_lib_id,face_lib_type,active,capabilities_json,last_seen_at,created_at,updated_at FROM hfc_devices ORDER BY name')->fetchAll();
        foreach($rows as &$row){$row['capabilities']=$row['capabilities_json']?json_decode($row['capabilities_json'],true):null;unset($row['capabilities_json']);}
        return $rows;
    }

    public function find(int $id,bool $withSecret=false): ?array
    {
        $sql=$withSecret?'SELECT * FROM hfc_devices WHERE id=?':'SELECT id,uuid,name,host,port,protocol,username,model,serial_no,firmware,role,face_lib_id,face_lib_type,active,capabilities_json,last_seen_at,created_at,updated_at FROM hfc_devices WHERE id=?';
        $s=Database::pdo()->prepare($sql);$s->execute([$id]);$row=$s->fetch();
        if(!$row)return null;
        if(isset($row['capabilities_json'])){$row['capabilities']=$row['capabilities_json']?json_decode($row['capabilities_json'],true):null;unset($row['capabilities_json']);}
        return $row;
    }

    public function create(array $d): array
    {
        $s=Database::pdo()->prepare('INSERT INTO hfc_devices(uuid,name,host,port,protocol,username,password_enc,role,face_lib_id,face_lib_type,active) VALUES(?,?,?,?,?,?,?,?,?,?,1)');
        $protocol=$d['protocol']??'http';
        $s->execute([$d['uuid']??bin2hex(random_bytes(16)),$d['name'],$d['host'],(int)($d['port']??($protocol==='https'?443:80)),$protocol,$d['username'],Crypto::encrypt($d['password']),$d['role']??'both',(string)($d['face_lib_id']??'1'),$d['face_lib_type']??'blackFD']);
        return $this->find((int)Database::pdo()->lastInsertId())??[];
    }

    public function client(int $id): IsapiClient
    {
        $d=$this->find($id,true); if(!$d)throw new \RuntimeException('Dispositivo não encontrado.');
        return new IsapiClient($d['host'],(int)$d['port'],$d['protocol'],$d['username'],Crypto::decrypt($d['password_enc']));
    }

    public function updateDetectedInfo(int $id,?string $model,?string $serial,?string $firmware): void
    {
        $s=Database::pdo()->prepare('UPDATE hfc_devices SET model=COALESCE(?,model),serial_no=COALESCE(?,serial_no),firmware=COALESCE(?,firmware),last_seen_at=NOW() WHERE id=?');
        $s->execute([$model,$serial,$firmware,$id]);
    }

    public function updateCapabilities(int $id,array $caps): void
    {
        $s=Database::pdo()->prepare('UPDATE hfc_devices SET capabilities_json=?,last_seen_at=NOW() WHERE id=?');
        $s->execute([json_encode($caps,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]);
    }
}
