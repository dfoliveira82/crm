
# Selvar CRM — SGU / FADIPA

Aplicação PHP 8.2+ sem framework, arquitetura MVC, autenticação delegada ao TOTVS RM e leitura de SQL Server via PDO `pdo_sqlsrv`.

## Estado da integração

O documento de requisitos não informa o endpoint real, o contrato JSON da autenticação RM nem o schema do banco. Por segurança, esses dados **não foram inventados**. Configure `.env` e `app/Config/tables.php` antes de usar dados reais. O modo fake só funciona com `APP_ENV=local` e `RM_FAKE=true`.

## Requisitos

- PHP 8.2 ou superior
- Extensões `pdo_sqlsrv`, `sqlsrv`, `curl`, `mbstring`, `openssl`
- Composer 2
- Apache com `mod_rewrite` ou IIS

## Instalação

1. Copie a pasta `crm` para o servidor.
2. Execute `composer install` na raiz.
3. Copie `.env.example` para `.env` e configure as variáveis.
4. Configure `app/Config/tables.php` com os nomes reais, seguindo `docs/mapeamento-tabelas.md`.
5. Configure o contrato de autenticação em `app/Services/Rm/RmAuthClient.php` conforme a API efetivamente publicada no seu RM.
6. Aponte o document root do Apache/IIS para `public/`.
7. Para teste local: `php -S localhost:8000 -t public`.

## Driver pdo_sqlsrv

### Windows
- Instale o Microsoft ODBC Driver for SQL Server compatível com a versão do Windows.
- Baixe as extensões `sqlsrv` e `pdo_sqlsrv` correspondentes à versão do PHP, arquitetura e Thread Safety.
- Copie os DLLs para `extension_dir` e habilite no `php.ini`:
  `extension=sqlsrv` e `extension=pdo_sqlsrv`.
- Reinicie Apache/IIS e confirme com `php -m`.

### Linux (Ubuntu/Debian)
- Instale o Microsoft ODBC Driver 18 e ferramentas do repositório oficial Microsoft.
- Instale os pacotes de desenvolvimento PHP e compile/instale as extensões `sqlsrv` e `pdo_sqlsrv` via PECL, usando a versão compatível com o PHP instalado.
- Habilite `extension=sqlsrv` e `extension=pdo_sqlsrv`; reinicie PHP-FPM/Apache.
- Consulte a documentação oficial Microsoft para comandos atualizados da sua distribuição.

## Configuração RM

`RM_AUTH_METHOD=basic`: envia Basic Auth ao `RM_AUTH_ENDPOINT`; sucesso HTTP 2xx.  
`RM_AUTH_METHOD=token`: envia `grant_type=password`, `username` e `password` em JSON para `/api/connect/token/`, conforme a documentação oficial da Linha RM. O `access_token` e o `refresh_token` são armazenados somente na sessão do servidor. O token é guardado somente na sessão do servidor.

O modo fake simula login **somente** em ambiente local. Não habilite em produção.

## Mapeamento SQL

Todas as referências a tabelas e colunas ficam em `app/Config/tables.php`. Não há nomes presumidos de tabelas do cliente. Veja `docs/mapeamento-tabelas.md`. Enquanto as chaves não forem configuradas, os widgets mostram estado vazio/indisponível em vez de inventar números.

## Segurança

- Nenhuma tabela de usuário ou senha é criada.
- Consultas com prepared statements; identificadores validados por regex.
- SQL de leitura apenas. Não existem métodos genéricos de escrita.
- A única operação de descarte de sugestão tenta UPDATE somente quando um mapeamento explícito de status estiver configurado; caso contrário, usa sessão.
- Copilot action é simulada e não altera dados.
- Configure usuário SQL com permissões mínimas.
- Configure HTTPS em produção; `RM_VERIFY_SSL=true`.

## Rotas

| Método | Caminho | Acesso |
|---|---|---|
| GET | `/login` | Visitante |
| POST | `/login` | Visitante + CSRF |
| POST | `/logout` | Autenticado + CSRF |
| GET | `/` | Autenticado |
| GET | `/pipeline` | Autenticado |
| GET | `/leads`, `/copilot`, `/calendar`, `/contacts`, `/reports`, `/team` | Autenticado |
| GET | `/api/bookings?range=weeks|months` | Autenticado |
| GET | `/api/copilot/suggestions` | Autenticado |
| DELETE | `/api/copilot/suggestions/{id}` | Autenticado + CSRF |
| POST | `/api/copilot/suggestions/{id}/action` | Autenticado + CSRF |
| POST | `/api/copilot/chat` | Autenticado + CSRF |
| GET | `/api/deals/attention` | Autenticado |

## Testes

- Fake: `APP_ENV=local`, `RM_FAKE=true`. Entre com qualquer usuário não vazio e senha com pelo menos 1 caractere. Nunca use essa configuração em produção.
- RM real: `RM_FAKE=false`, configure endpoint, método, TLS e contrato de resposta. Teste com conta de homologação.
- Banco: configure `.env` e `tables.php`; valide primeiro com usuário SQL somente leitura.

## Módulo NFS-e (etapa de leitura do RM)

- Acesse `/backoffice/gestao-financeira/nfse` pelo cartão em Gestão Financeira.
- A conexão com o RM é independente da conexão principal do CRM e usa `RM_DB_HOST`, `RM_DB_PORT`, `RM_DB_NAME`, `RM_DB_USER`, `RM_DB_PASS`, `RM_DB_ENCRYPT` e `RM_DB_TRUST_SERVER_CERTIFICATE`.
- Configure um login SQL dedicado com permissão somente de `SELECT` em `dbo.FLAN`, `dbo.FLANBAIXA` e `dbo.FCFO`. Não reutilize um usuário com permissão de escrita.
- O painel consulta os 100 lançamentos mais recentes e soma baixas apenas para exibição exploratória quando `DATACANCELBAIXA IS NULL`. Isso **não** classifica quitação integral nem define base de cálculo da NFS-e.
- Os códigos `PAGREC`, `STATUSLAN`, `STATUS` e `TIPOBAIXA` são apresentados sem interpretação automática. Precisam ser validados para esta instalação antes de automatizar qualquer emissão.
- O script `database/004_nfse_rm_diagnostico.sql` contém a consulta de conferência e `database/005_nfse_codigos_rm.sql` agrupa os códigos encontrados. Ambos são somente leitura.
- Nenhuma tabela financeira do RM é alterada e nenhuma nota é transmitida nesta etapa.
