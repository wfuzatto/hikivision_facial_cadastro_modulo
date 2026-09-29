<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Database;
$pdo=Database::pdo();
$files=glob(dirname(__DIR__).'/migrations/*.sql')?:[];sort($files);
foreach($files as $file){echo 'Aplicando '.basename($file).PHP_EOL;$sql=file_get_contents($file);if($sql===false)throw new RuntimeException('Falha ao ler migration: '.$file);$pdo->exec($sql);}
echo "Migrations concluídas.".PHP_EOL;
