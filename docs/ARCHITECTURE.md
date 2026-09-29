# Arquitetura proprietária

O HikCentral não participa do fluxo operacional. Os terminais são dispositivos de borda controlados diretamente por ISAPI.

## Fonte de verdade
- hfc_persons: identidade
- hfc_cards: cartões
- hfc_devices: equipamentos e capabilities
- hfc_sync_jobs: fila de distribuição
- hfc_sync_logs: histórico

## Fluxo atual
1. Cadastrar terminal.
2. Testar conectividade e capabilities.
3. Criar pessoa.
4. Capturar o cartão no terminal de cadastro.
5. Enfileirar para terminais de destino.
6. Sincronizar pessoa e cartão diretamente por ISAPI.
7. Repetir falhas com backoff.

## Biometria
A camada de biometria permanece isolada no adapter, para que requisitos de firmware, segurança e tratamento de dados não contaminem o núcleo de pessoas/dispositivos.

## Próximas camadas
- interface web
- grupos e zonas
- horários e validade
- eventos
- abertura remota
- revogação
- worker contínuo
- integração Visitor/PMS
- alta disponibilidade
