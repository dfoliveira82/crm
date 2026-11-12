# Guia de Configuração da Conexão com o TOTVS RM (SQL Server)

Este documento orienta a configuração da conexão real, exclusiva de leitura, entre a aplicação CRM/TotvsWEB e o banco de dados do TOTVS RM (SQL Server).

---

## 1. Variáveis de Ambiente (.env)

A conexão utiliza variáveis de ambiente centralizadas no arquivo `.env` da raiz do projeto. Nenhuma credencial fica gravada no código-fonte.

Exemplo de configuração:

```env
# ==============================================================================
# CONEXÃO REAL COM O BANCO DE DADOS DO TOTVS RM (ETAPA 3 - LEITURA FISCAL)
# ==============================================================================
RM_DB_HOST=130.130.130.2
RM_DB_PORT=1433
RM_DB_DATABASE=CorporeRM
RM_DB_USERNAME=rm
RM_DB_PASSWORD=sua_senha_segura
RM_DB_DRIVER=sqlsrv
RM_DB_ENCRYPT=true
RM_DB_TRUST_SERVER_CERTIFICATE=yes
```

### Descrição dos Parâmetros

| Variável | Obrigatório | Padrão | Descrição |
|---|---|---|---|
| `RM_DB_HOST` | **Sim** | — | Endereço IP ou hostname do servidor SQL Server do TOTVS RM. |
| `RM_DB_PORT` | Não | `1433` | Porta TCP do serviço SQL Server. |
| `RM_DB_DATABASE` | **Sim** | `CorporeRM` | Nome da base de dados do TOTVS RM (ex.: `CorporeRM`). Também aceita `RM_DB_NAME`. |
| `RM_DB_USERNAME` | **Sim** | — | Usuário SQL com privilégio exclusivo de leitura. Também aceita `RM_DB_USER`. |
| `RM_DB_PASSWORD` | **Sim** | — | Senha do usuário SQL. Também aceita `RM_DB_PASS`. |
| `RM_DB_DRIVER` | Não | `sqlsrv` | Driver PDO: `sqlsrv` (recomendado no Windows) ou `dblib` (Linux/FreeTDS). |
| `RM_DB_ENCRYPT` | Não | `true` | Habilita criptografia SSL na comunicação com o banco. |
| `RM_DB_TRUST_SERVER_CERTIFICATE` | Não | `yes` | Aceita certificado autoassinado do SQL Server na rede local. |

---

## 2. Criação do Usuário de Leitura no SQL Server (DBA)

Recomendamos a criação de um usuário SQL específico para a integração, com permissão **exclusiva de SELECT** nas tabelas necessárias:

```sql
USE [CorporeRM];
GO

-- 1. Criar o login no servidor SQL Server
CREATE LOGIN [usr_crm_nfse] WITH PASSWORD = 'DefinaUmaSenhaSegura!2026', CHECK_POLICY = ON;
GO

-- 2. Criar o usuário no banco de dados CorporeRM
CREATE USER [usr_crm_nfse] FOR LOGIN [usr_crm_nfse];
GO

-- 3. Conceder permissão de SELECT apenas nas 3 tabelas necessárias
GRANT SELECT ON dbo.FLAN TO [usr_crm_nfse];
GRANT SELECT ON dbo.FLANBAIXA TO [usr_crm_nfse];
GRANT SELECT ON dbo.FCFO TO [usr_crm_nfse];
GO

-- 4. Garantir explicitamente que nenhuma escrita possa ocorrer
DENY INSERT, UPDATE, DELETE, ALTER ON dbo.FLAN TO [usr_crm_nfse];
DENY INSERT, UPDATE, DELETE, ALTER ON dbo.FLANBAIXA TO [usr_crm_nfse];
DENY INSERT, UPDATE, DELETE, ALTER ON dbo.FCFO TO [usr_crm_nfse];
GO
```

---

## 3. Pré-requisitos do PHP no Servidor IIS / Apache

Certifique-se de que a extensão `pdo_sqlsrv` esteja habilitada no `php.ini`:

```ini
extension=php_pdo_sqlsrv.dll
extension=php_sqlsrv.dll
```

Para verificar se a extensão está carregada:

```powershell
php -m | Select-String "sqlsrv"
```

---

## 4. Teste de Conectividade Automático

A aplicação possui um endpoint de diagnóstico e verificação automática da conexão:

1. Acesse o sistema e navegue até:
   **Backoffice → Gestão Financeira → Notas Fiscais de Serviço**
2. Se a conexão estiver bem-sucedida, o painel exibirá:
   - Indicador verde: `Conectado a CorporeRM`
   - Hora oficial do servidor SQL Server
   - Quantidade de lançamentos encontrados em `dbo.FLAN`
3. Em caso de falha de conexão, um banner de aviso apresentará o motivo sem expor dados confidenciais aos usuários não autorizados.

Para testar via API:
- `GET /api/nfse/diagnostico` (requer sessão autenticada)
  Retorna JSON com status da conexão e presença das tabelas `FLAN`, `FLANBAIXA` e `FCFO`.
