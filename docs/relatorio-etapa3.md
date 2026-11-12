# Relatório Técnico de Implementação - Etapa 3

## Módulo NFS-e: Conexão Real com o TOTVS RM

**Data de Implementação:** 10/11/2026  
**Ambiente:** CRM / TotvsWEB (PHP 8.2+ MVC, SQL Server, IIS, Vanilla JS, Font Awesome)  
**Objetivo:** Estabelecer a conexão real e somente leitura com o banco de dados do TOTVS RM, realizar a consulta de lançamentos e baixas financeiras, aplicar as regras de negócio para quitação integral e pagamentos parciais, e disponibilizar o painel de monitoramento com dados reais.

---

## 1. Resumo das Implementações

### 1.1 Arquitetura de Serviços Criada (`app/Services/Rm/`)

1. **`RmConnection.php`**:
   - Fachada de conexão para o TOTVS RM baseada em PDO SQL Server.
   - Método `test()` para verificar conectividade, versão do SQL Server e hora do servidor.
   - Método `checkTables()` que inspeciona a presença das tabelas `FLAN`, `FLANBAIXA` e `FCFO` via `INFORMATION_SCHEMA.TABLES`.
   - Método `pdo()` para obter a conexão ativa somente leitura.

2. **`RmReceivableRepository.php`**:
   - Repositório especializado para consultas no ERP TOTVS RM.
   - Consulta paginada (`OFFSET ... ROWS FETCH NEXT ... ROWS ONLY`) otimizada para alto volume de dados.
   - Junção das tabelas `dbo.FLAN` (lançamentos) com `dbo.FCFO` (tomador) e subquery `OUTER APPLY` em `dbo.FLANBAIXA` para agrupar todas as baixas ativas.
   - Aplicação de filtros em nível SQL: período de vencimento (`DATAVENCIMENTO`), coligada (`CODCOLIGADA`), cliente (`CODCFO` / nome / documento), situação financeira (`QUITADO`, `PARCIAL`, `PENDENTE`, `CANCELADO`) e elegibilidade (`ELEGIVEL`, `NAO_ELEGIVEL`).
   - Método `dashboardIndicators()` que calcula em tempo real os 6 indicadores exigidos diretamente dos dados reais do RM.
   - Método `findByIdlan()` e `findBaixas()` para detalhamento completo no modal interativo.
   - Regra estrita de tolerância de R$ 0,01 para arredondamentos.

3. **`RmFinancialService.php`**:
   - Serviço orquestrador entre o TOTVS RM e a base da aplicação.
   - Método `sync()`: percorre os lançamentos e realiza a persistência idempotente (`UPSERT`) em `dbo.NFS_COBRANCA` e `dbo.NFS_BAIXAS`, gerando hashes SHA-256 de integridade e registrando eventos no log técnico (`dbo.NFS_LOG`).
   - Método `panelIndicators()`: consolida os números apurados do RM com as emissões registradas na fila local.
   - **Garantia de segurança:** Nenhuma operação `INSERT`, `UPDATE` ou `DELETE` é executada no TOTVS RM.

### 1.2 Ajustes no Core e Configuração

1. **`app/Core/RmDatabase.php`**:
   - Suporte padronizado às variáveis de ambiente:
     - `RM_DB_HOST`
     - `RM_DB_PORT`
     - `RM_DB_DATABASE` (com fallback para `RM_DB_NAME`)
     - `RM_DB_USERNAME` (com fallback para `RM_DB_USER`)
     - `RM_DB_PASSWORD` (com fallback para `RM_DB_PASS`)
     - `RM_DB_DRIVER` (`sqlsrv` ou `dblib`)
     - `RM_DB_ENCRYPT` e `RM_DB_TRUST_SERVER_CERTIFICATE`
   - Método `reset()` para reiniciar a instância PDO em testes.

2. **`app/Config/nfse.php`**:
   - Mapeamento explícito ativado (`'enabled' => true`, `'read_only' => true`).
   - Mapeamento de todas as colunas de `FLAN`, `FLANBAIXA` e `FCFO`.
   - Parametrização das regras de negócio (somente quitar integralmente, tolerância de centavo, etc.).

3. **`app/Config/routes.php`**:
   - `GET /backoffice/gestao-financeira/nfse` → Painel principal com dados reais.
   - `POST /api/nfse/sync` → Sincronização assíncrona RM → NFS-e (com CSRF e autenticação).
   - `GET /api/nfse/lancamento` → Detalhes completos do lançamento e histórico de baixas via AJAX.
   - `GET /api/nfse/diagnostico` → Verificação de conectividade e integridade do schema.

### 1.3 Interface Visual do Painel (`app/Views/nfse/index.php` e `public/css/nfse.css`)

1. **Indicadores do Painel (6 Cards):**
   - Total de lançamentos encontrados (contas a receber no RM).
   - Mensalidades integralmente quitadas.
   - Mensalidades parcialmente pagas (com alerta de não elegibilidade).
   - Mensalidades pendentes (sem nenhuma baixa).
   - Lançamentos cancelados ou com baixa cancelada.
   - Lançamentos elegíveis para emissão de NFS-e.

2. **Barra de Filtros Completos:**
   - Vencimento De / Até.
   - Coligada.
   - Cliente (código, nome ou CPF/CNPJ).
   - Situação financeira (Todas, Quitado, Parcial, Pendente, Cancelado).
   - Situação de emissão (Todas, Elegível, Não elegível).
   - Botão "Limpar" e submissão rápida.

3. **Tabela de Lançamentos:**
   - Coligada.
   - Identificador do lançamento (`IDLAN`).
   - Cliente / Tomador (Nome e código CFO).
   - CPF / CNPJ do tomador.
   - Referência da mensalidade (Número do documento e histórico com tooltip).
   - Data de vencimento.
   - Valor original.
   - Total pago (com indicação da quantidade de baixas ativas quando > 1).
   - Saldo restante (destaque visual quando zerado ou pendente).
   - Situação financeira com badges coloridos (Quitado, Parcial, Pendente, Cancelado).
   - Elegibilidade para emissão (Elegível / Não elegível).
   - Botão para abertura do modal de detalhes.

4. **Modal de Detalhes e Histórico de Baixas:**
   - Apresenta os dados cadastrais do tomador, endereço completo, dados do título e discriminação de cada baixa em `FLANBAIXA` (data, valor recebido, juros, multas, descontos, status da baixa e estorno).

5. **Sincronização Assíncrona:**
   - Botão "Sincronizar RM" no cabeçalho com feedback visual (spinner, toast de notificação e atualização de página).

---

## 2. Validação das Regras de Negócio

| Regra Solicitada | Como foi Implementada | Status |
|---|---|---|
| **Somente Leitura no RM** | O usuário configurado possui apenas SELECT; todos os métodos do repositório executam exclusivamente `SELECT`. | Validado |
| **Múltiplas Baixas** | Subquery `OUTER APPLY` agrega `SUM(VALORBAIXA)` e `SUM(VALORORIGINAL)` das baixas ativas (`DATACANCELBAIXA IS NULL`). | Validado |
| **Quitação Integral** | Quando o total amortizado/pago atinge o valor original com tolerância de R$ 0,01, o status é `QUITADO` e `ELEGIVEL_EMISSAO = true`. | Validado |
| **Pagamento Parcial** | Quando há baixa mas resta saldo > R$ 0,01, o status é `PARCIAL` e `ELEGIVEL_EMISSAO = false`. Não autoriza emissão fiscal. | Validado |
| **Estornos / Cancelamentos** | Se `DATACANCELAMENTO` ou `DATACANCELBAIXA` estiverem preenchidos no lançamento, ou na baixa individual, o evento é desconsiderado ou classificado como `CANCELADO`. | Validado |
| **Paginação e Performance** | A paginação é executada no SQL Server via `OFFSET ... ROWS FETCH NEXT ... ROWS ONLY`, garantindo tempo de resposta sub-segundo mesmo com dezenas de milhares de títulos. | Validado |
| **Idempotência na Sincronização** | O serviço calcula hash SHA-256 e utiliza `CODCOLIGADA + IDLAN_RM` como chave natural única para prevenir duplicidades. | Validado |
| **Logs de Auditoria** | Falhas e sucessos de sincronização gravam registros detalhados na tabela `dbo.NFS_LOG`. | Validado |

---

## 3. Arquivos Entregues / Atualizados

```
c:\inetpub\wwwroot\crm/
├── .env.example                                (novo arquivo de exemplo de ambiente)
├── LEIA-ME-NFSE-ETAPA3.txt                     (instruções diretas para a equipe)
├── app/
│   ├── Config/
│   │   ├── nfse.php                            (mapeamento ativo e regras de negócio)
│   │   └── routes.php                          (novas rotas de sync, lançamento e diagnóstico)
│   ├── Controllers/
│   │   └── NfseController.php                  (controller completo com ações AJAX e paginação)
│   ├── Core/
│   │   └── RmDatabase.php                      (suporte a RM_DB_DATABASE, USERNAME, DRIVER, etc.)
│   ├── Services/
│   │   └── Rm/
│   │       ├── RmConnection.php                (novo: testes de conexão e diagnóstico do banco)
│   │       ├── RmFinancialService.php          (novo: orquestrador de sincronização e persistência)
│   │       └── RmReceivableRepository.php      (novo: repositório de consultas e regras financeiras)
│   └── Views/
│       └── nfse/
│           └── index.php                       (painel completo com 6 indicadores, filtros e modal)
├── database/
│   ├── 004_nfse.sql                            (estrutura original das 8 tabelas NFS_*)
│   └── 006_nfse_etapa3_atualizacao.sql         (novo: índices otimizados para sincronização)
├── docs/
│   ├── campos-totvs-rm.md                      (dicionário completo dos campos de FLAN, FLANBAIXA e FCFO)
│   ├── configuracao-conexao-rm.md              (manual de configuração do SQL Server e variáveis .env)
│   └── relatorio-etapa3.md                     (este documento)
└── public/
    └── css/
        └── nfse.css                            (estilos do painel, filtros, cards, tabela e modal)
```
