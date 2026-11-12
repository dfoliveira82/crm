# Dicionário de Dados TOTVS RM - Módulo NFS-e (Etapa 3)

Este documento especifica todos os campos das tabelas do TOTVS RM consultados pelo módulo NFS-e, suas finalidades e a regra de negócio aplicada.

> **Importante:** A conexão com o banco do TOTVS RM é estritamente de **LEITURA (SELECT)**. Nenhuma operação de escrita (`INSERT`, `UPDATE`, `DELETE`) é realizada nas tabelas do ERP.

---

## 1. Tabela `dbo.FLAN` (Lançamentos Financeiros)

Representa os títulos financeiros gerados pelo RM (mensalidades escolares, taxas e parcelas).

| Campo | Tipo / Formato | Descrição no RM | Finalidade na Integração NFS-e |
|---|---|---|---|
| `CODCOLIGADA` | `SMALLINT` / `INT` | Código da Coligada da instituição | Identificação multiempresa; chave primária composta do lançamento. |
| `IDLAN` | `INT` / `BIGINT` | Identificador único do lançamento financeiro | Chave primária do título; mapeado para `IDLAN_RM` nas tabelas `NFS_COBRANCA` e `NFS_BAIXAS`. |
| `CODFILIAL` | `SMALLINT` | Código da filial | Identificação da unidade/campus de ensino prestador do serviço. |
| `PAGREC` | `SMALLINT` | Sentido financeiro (1 = Receber, 2 = Pagar) | **Filtro obrigatório**: A consulta filtra estritamente `PAGREC = 1` (Contas a Receber). |
| `STATUSLAN` | `VARCHAR` / `CHAR` | Situação do lançamento no RM | Informativo na tela e no mapa de diagnóstico. A elegibilidade usa as datas reais e somas de baixas. |
| `CODTDO` | `VARCHAR` | Código do Tipo de Documento | Identifica o tipo do título (ex.: Boleto, Mensalidade, Acordo). |
| `NUMERODOCUMENTO` | `VARCHAR` | Número do documento de cobrança | Apresentado na tabela e modal como referência documental da mensalidade. |
| `HISTORICO` | `VARCHAR` | Histórico / Descrição do lançamento | Descrição do serviço prestado (ex.: "Mensalidade referente ao mês 03/2026"). |
| `DATACRIACAO` | `DATETIME` | Data de inclusão do lançamento no RM | Ordenação cronológica e rastreabilidade da geração do título. |
| `DATAEMISSAO` | `DATETIME` | Data de emissão da cobrança | Data contábil/fiscal de referência. |
| `DATAVENCIMENTO` | `DATETIME` | Data de vencimento da mensalidade | Filtro por período, cálculo de competência e exibição no painel. |
| `DATABAIXA` | `DATETIME` | Data da última baixa registrada no título | Rastreamento da quitação no RM. |
| `DATACANCELAMENTO` | `DATETIME` | Data em que o título foi cancelado | **Regra de cancelamento**: se preenchido, define `SITUACAO_FINANCEIRA = 'CANCELADO'` e bloqueia emissão. |
| `DATACANCELBAIXA` | `DATETIME` | Data em que a baixa do título foi estornada/cancelada | **Regra de estorno**: se preenchido no título, classifica como cancelado/estornado. |
| `VALORORIGINAL` | `DECIMAL(15,4)` | Valor nominal original do título | Valor base da cobrança. Utilizado para comparação com o somatório das baixas. |
| `VALORBAIXADO` | `DECIMAL(15,4)` | Valor total baixado acumulado no RM | Campo informativo de conferência com a soma das baixas em `FLANBAIXA`. |
| `VALORDESCONTO` | `DECIMAL(15,4)` | Descontos concedidos no lançamento | Abatimentos concedidos ao aluno/responsável financeiro. |
| `VALOROP3` | `DECIMAL(15,4)` | Valor Opcional 3 (Customizado) | Campo opcional do RM utilizado pela instituição para Bolsas/Descontos. |
| `VALOROP5` | `DECIMAL(15,4)` | Valor Opcional 5 (Customizado) | Campo opcional do RM utilizado pela instituição para Bolsas/Descontos. |
| `VALOROP6` | `DECIMAL(15,4)` | Valor Opcional 6 (Customizado) | **Bolsa (Até vencimento)**: desconto pontualidade/bolsa aplicado no lançamento escolar. |
| `VALORJUROS` | `DECIMAL(15,4)` | Juros aplicados | Encargos por atraso. |
| `VALORMULTA` | `DECIMAL(15,4)` | Multa aplicada | Encargos contratuais. |
| `CODCOLCFO` | `SMALLINT` | Coligada do tomador (cliente) | Utilizado no `JOIN` com a tabela `FCFO`. |
| `CODCFO` | `VARCHAR` | Código do cliente/fornecedor | Identificador do aluno/responsável financeiro no RM. |

---

## 2. Tabela `dbo.FLANBAIXA` (Baixas dos Lançamentos Financeiros)

Registra cada evento de pagamento, parcial ou total, bem como cancelamentos e estornos.

| Campo | Tipo / Formato | Descrição no RM | Finalidade na Integração NFS-e |
|---|---|---|---|
| `CODCOLIGADA` | `SMALLINT` | Código da Coligada | Chave estrangeira composta para `FLAN`. |
| `IDLAN` | `INT` / `BIGINT` | Identificador do lançamento | Chave de relacionamento com `FLAN.IDLAN`. |
| `IDBAIXA` | `INT` / `BIGINT` | Identificador sequencial da baixa | Chave primária da baixa; gravada em `NFS_BAIXAS.IDBAIXA_RM`. |
| `DATABAIXA` | `DATETIME` | Data em que o pagamento ocorreu | Registrada no histórico de baixas; data base para a competência fiscal. |
| `DATACANCELBAIXA` | `DATETIME` | Data de cancelamento / estorno da baixa | **Validação crítica**: se `IS NULL`, a baixa é **ATIVA e VÁLIDA**. Se preenchido, a baixa é desconsiderada do somatório de pagamento. |
| `VALORBAIXA` | `DECIMAL(15,4)` | Valor líquido recebido em caixa/banco | Montante financeiro efetivamente pago pelo tomador nesta baixa. |
| `VALORORIGINAL` | `DECIMAL(15,4)` | Parcela do principal amortizada por esta baixa | Utilizado para amortização do principal da mensalidade. |
| `VALORDESCONTO` | `DECIMAL(15,4)` | Desconto concedido nesta baixa | Considerado no cálculo de quitação quando houve desconto pontualidade/bolsa. |
| `VALORJUROS` | `DECIMAL(15,4)` | Juros recebidos nesta baixa | Acréscimo financeiro pago no evento. |
| `VALORMULTA` | `DECIMAL(15,4)` | Multa recebida nesta baixa | Acréscimo financeiro pago no evento. |
| `STATUS` | `VARCHAR` | Situação da baixa no RM | Exibido nos detalhes e no mapa de códigos de situação. |
| `TIPOBAIXA` | `VARCHAR` | Tipo de baixa (ex.: Caixa, Banco, Retorno Bancário) | Identificação do canal de pagamento. |
| `HISTORICO` | `VARCHAR` | Observações da baixa | Detalhamento do crédito bancário ou estorno. |

---

## 3. Tabela `dbo.FCFO` (Cadastro de Clientes / Tomadores)

Fornece os dados cadastrais do tomador do serviço para preenchimento da NFS-e.

| Campo | Tipo / Formato | Descrição no RM | Finalidade na Integração NFS-e |
|---|---|---|---|
| `CODCOLIGADA` | `SMALLINT` | Código da Coligada do cadastro | Chave composta de amarração com `FLAN.CODCOLCFO`. |
| `CODCFO` | `VARCHAR` | Código único do cliente/fornecedor | Identificador do aluno ou responsável financeiro. |
| `NOME` | `VARCHAR(100)` | Razão Social ou Nome Completo | Nome do tomador na NFS-e. |
| `NOMEFANTASIA` | `VARCHAR(100)` | Nome Fantasia / Apelido | Fallback quando `NOME` estiver em branco. |
| `CGCCFO` | `VARCHAR(20)` | CPF ou CNPJ (somente números ou formatado) | Identificação fiscal obrigatória do tomador junto à Prefeitura de Ipatinga. |
| `EMAIL` | `VARCHAR(100)` | E-mail do cliente | Destinatário da notificação e do PDF da NFS-e emitida. |
| `RUA` | `VARCHAR(100)` | Logradouro do endereço | Endereço do tomador exigido pelo padrão ABRASF / IPM / Betha. |
| `NUMERO` | `VARCHAR(20)` | Número do endereço | Componente do endereço. |
| `COMPLEMENTO` | `VARCHAR(60)` | Complemento do endereço | Apartamento, bloco, sala, etc. |
| `BAIRRO` | `VARCHAR(60)` | Bairro | Componente obrigatório do endereço do tomador. |
| `CIDADE` | `VARCHAR(60)` | Município | Identificação de tomador estabelecido ou não no município emissor. |
| `CODETD` | `VARCHAR(2)` | Unidade Federativa (UF) | Sigla do Estado (MG, SP, etc.). |
| `CEP` | `VARCHAR(10)` | Código de Endereçamento Postal | CEP do tomador. |

---

## 4. Regras Lógicas de Classificação e Elegibilidade

1. **Baixas Válidas vs. Baixas Canceladas:**
   Uma baixa é válida e compõe o total pago **somente** quando:
   $$\text{FLANBAIXA.DATACANCELBAIXA IS NULL}$$

2. **Somatório de Múltiplas Baixas:**
   O valor pago total da mensalidade é a soma de todas as baixas ativas:
   $$\text{SOMA\_PAGO} = \sum (\text{FLANBAIXA.VALORBAIXA}) \quad \text{onde } \text{DATACANCELBAIXA IS NULL}$$

3. **Amortização do Principal (com descontos):**
   $$\text{AMORTIZADO} = \sum (\text{FLANBAIXA.VALORORIGINAL}) \quad \text{ou} \quad (\text{SOMA\_PAGO} + \text{SOMA\_DESCONTOS})$$

4. **Saldo Restante:**
   $$\text{SALDO} = \max(0.00, \; \text{FLAN.VALORORIGINAL} - \text{AMORTIZADO})$$

5. **Classificação de Situação Financeira:**
   - Se `FLAN.DATACANCELAMENTO IS NOT NULL` ou `FLAN.DATACANCELBAIXA IS NOT NULL`: **`CANCELADO`**
   - Senão, se $\text{SOMA\_PAGO} \le 0$: **`PENDENTE`**
   - Senão, se $\text{SALDO} \le 0.01$ (tolerância de R$ 0,01): **`QUITADO`**
   - Senão: **`PARCIAL`**

6. **Elegibilidade para Emissão da NFS-e (`ELEGIVEL_EMISSAO`):**
   $$\text{Elegível} = (\text{SITUACAO\_FINANCEIRA} = \text{'QUITADO'})$$
   - Pagamentos parciais têm `ELEGIVEL_EMISSAO = FALSE`.
   - Cancelados têm `ELEGIVEL_EMISSAO = FALSE`.
   - Pendentes têm `ELEGIVEL_EMISSAO = FALSE`.
