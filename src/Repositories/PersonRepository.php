<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class PersonRepository
{
    public function all(): array
    {
        $rows=Database::pdo()->query('SELECT * FROM hfc_persons ORDER BY id DESC LIMIT 500')->fetchAll();
        return array_map(fn(array $r)=>$this->hydrate($r),$rows);
    }

    public function find(int $id): ?array
    {
        $s=Database::pdo()->prepare('SELECT * FROM hfc_persons WHERE id=?');$s->execute([$id]);$r=$s->fetch();
        return $r?$this->hydrate($r):null;
    }

    public function create(array $d): array
    {
        $code=trim((string)($d['person_code']??''));if($code==='')$code='HFC'.strtoupper(bin2hex(random_bytes(5)));
        $s=Database::pdo()->prepare('INSERT INTO hfc_persons(person_code,name,valid_from,valid_to,status,metadata_json) VALUES(?,?,?,?,?,?)');
        $s->execute([$code,trim((string)$d['name']),$d['valid_from']??date('Y-m-d H:i:s',time()-300),$d['valid_to']??'2037-12-31 23:59:59',$d['status']??'active',isset($d['metadata'])?json_encode($d['metadata'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null]);
        return $this->find((int)Database::pdo()->lastInsertId())??[];
    }

    public function addCard(int $personId,string $cardNo,?string $cardType,?int $sourceDeviceId): array
    {
        $s=Database::pdo()->prepare('INSERT INTO hfc_cards(person_id,card_no,card_type,source_device_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE person_id=VALUES(person_id),card_type=VALUES(card_type),source_device_id=VALUES(source_device_id),updated_at=NOW()');
        $s->execute([$personId,$cardNo,$cardType,$sourceDeviceId]);return $this->find($personId)??[];
    }

    public function setFace(int $personId,string $imagePath,string $mime,string $sha256,?int $captureDeviceId): array
    {
        $token=bin2hex(random_bytes(32));
        $s=Database::pdo()->prepare('INSERT INTO hfc_faces(person_id,image_path,mime_type,sha256,capture_device_id,pull_token) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE image_path=VALUES(image_path),mime_type=VALUES(mime_type),sha256=VALUES(sha256),capture_device_id=VALUES(capture_device_id),pull_token=VALUES(pull_token),updated_at=NOW()');
        $s->execute([$personId,$imagePath,$mime,$sha256,$captureDeviceId,$token]);return $this->find($personId)??[];
    }

    public function findFaceByToken(string $token): ?array
    {
        $s=Database::pdo()->prepare('SELECT * FROM hfc_faces WHERE pull_token=? LIMIT 1');$s->execute([$token]);$r=$s->fetch();return $r?:null;
    }

    private function hydrate(array $r): array
    {
        $r['metadata']=$r['metadata_json']?json_decode($r['metadata_json'],true):null;unset($r['metadata_json']);
        $s=Database::pdo()->prepare('SELECT id,card_no,card_type,source_device_id,created_at,updated_at FROM hfc_cards WHERE person_id=? ORDER BY id');$s->execute([$r['id']]);$r['cards']=$s->fetchAll();
        $f=Database::pdo()->prepare('SELECT id,image_path,mime_type,sha256,capture_device_id,pull_token,created_at,updated_at FROM hfc_faces WHERE person_id=? LIMIT 1');$f->execute([$r['id']]);$r['face']=$f->fetch()?:null;
        return $r;
    }
}
