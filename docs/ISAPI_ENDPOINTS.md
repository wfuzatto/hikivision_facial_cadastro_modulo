# Endpoints ISAPI usados

## Descoberta
- GET /ISAPI/System/deviceInfo
- GET /ISAPI/System/capabilities
- GET /ISAPI/AccessControl/capabilities?format=json
- GET /ISAPI/AccessControl/UserInfo/capabilities?format=json
- GET /ISAPI/AccessControl/CardInfo/capabilities?format=json
- GET /ISAPI/AccessControl/CaptureCardInfo/capabilities?format=json
- GET /ISAPI/AccessControl/CaptureFaceData/capabilities?format=json
- GET /ISAPI/Intelligent/FDLib/capabilities?format=json

## Cadastro no leitor
- GET /ISAPI/AccessControl/CaptureCardInfo?format=json
- POST /ISAPI/AccessControl/CaptureFaceData?format=json

## Distribuição
- POST /ISAPI/AccessControl/UserInfo/Record?format=json
- PUT /ISAPI/AccessControl/UserInfo/Modify?format=json
- POST /ISAPI/AccessControl/CardInfo/Record?format=json
- PUT /ISAPI/AccessControl/CardInfo/Modify?format=json
- POST /ISAPI/Intelligent/FDLib/FaceDataRecord?format=json
- PUT /ISAPI/Intelligent/FDLib/FDSearch?format=json&FDID=...&FPID=...&faceLibType=...

O driver consulta capabilities antes de assumirmos compatibilidade de firmware.
