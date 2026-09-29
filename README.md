# Hikvision Facial Cadastro Módulo

Módulo proprietário para cadastro, captura e distribuição de credenciais em terminais de controle de acesso Hikvision via ISAPI, sem dependência do HikCentral.

## Objetivo

Usar um terminal como o **DS-K1T673DX** como estação de cadastramento e manter a base central sob nosso controle:

- cadastrar dispositivos Hikvision;
- consultar capabilities do firmware;
- capturar cartão/NFC apresentado no leitor;
- capturar face pela câmera do terminal quando suportado;
- manter pessoa, cartão e foto facial na nossa base;
- distribuir pessoas, cartões e faces para vários dispositivos;
- registrar fila, tentativas, erros e auditoria de sincronização;
- permitir integração futura com Visitor, PMS, totens e outros módulos.

## Arquitetura

```
DS-K1T673DX (cadastro)
   | ISAPI / HTTP Digest
   v
hikivision_facial_cadastro_modulo
   |-- API REST
   |-- MariaDB
   |-- armazenamento de faces
   |-- fila de sincronização
   |-- auditoria
   v
DS-K1T673DX / catracas / terminais Hikvision
```

A foto facial original é o ativo mestre. Não dependemos de copiar o template biométrico interno de um equipamento para outro; cada terminal recebe a foto e gera o modelo compatível com o próprio firmware.

## Requisitos

- PHP 8.1+
- extensões `curl`, `pdo_mysql`, `json`, `fileinfo`
- Apache (XAMPP compatível)
- MariaDB/MySQL
- rede IP entre o servidor e os terminais
- ISAPI habilitado nos dispositivos Hikvision

## Instalação rápida

1. Clone o projeto em `C:\xampp\htdocs\hikivision_facial_cadastro_modulo`.
2. Copie `.env.example` para `.env`.
3. Crie o banco definido no `.env`.
4. Execute `php bin/migrate.php`.
5. Aponte o Apache para `public/` ou acesse `/public`.
6. Abra `GET /api/health`.

Nunca versione senhas reais dos dispositivos. O arquivo `.env` está ignorado pelo Git.

## Endpoints iniciais

- `GET /api/health`
- `GET /api/devices`
- `POST /api/devices`
- `GET /api/devices/{id}`
- `POST /api/devices/{id}/test`
- `GET /api/devices/{id}/capabilities`
- `POST /api/devices/{id}/capture-card`
- `POST /api/devices/{id}/capture-face`
- `GET /api/persons`
- `POST /api/persons`
- `GET /api/persons/{id}`
- `POST /api/persons/{id}/card`
- `POST /api/persons/{id}/face`
- `POST /api/persons/{id}/sync`
- `GET /api/sync/jobs`
- `POST /api/sync/process`

## Segurança

O sistema lida com credenciais de acesso e biometria. Em produção:

- mantenha a API em rede privada/VPN;
- use HTTPS;
- use um usuário ISAPI dedicado e com privilégio mínimo;
- criptografe backups;
- restrinja o diretório `storage/faces`;
- defina retenção e finalidade para dados biométricos;
- mantenha trilha de auditoria.

## Status

Primeiro esqueleto funcional da API proprietária. Os endpoints ISAPI são isolados no adapter para permitir compatibilidade por modelo/firmware.
