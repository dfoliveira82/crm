# Selvar CRM — Cadastro Facial no Dashboard

## O que foi integrado
- O atalho **Cadastro Facial dos Funcionários** aparece exclusivamente no dashboard inicial.
- A interface abre em um modal, sem rota/página independente e sem novo item no menu lateral.
- Pesquisa funcionários ativos no RM por nome ou chapa.
- Mantém a regra de exclusão de funções docentes configurável.
- Captura três descritores faciais no navegador usando `face-api.js`.
- Salva os descritores binários na tabela `dbo.PONTO_FACIAL` do SQL Server.
- Se o funcionário já tiver cadastro ativo, a nova captura atualiza a referência existente.

## Requisitos de banco
O CRM usa `App\Core\Database::connection()`. Portanto, o `.env` do CRM precisa apontar para o mesmo banco SQL Server que contém as tabelas RM `PFUNC`, `PPESSOA` e `PFUNCAO`, e a tabela `dbo.PONTO_FACIAL`.

Se `dbo.PONTO_FACIAL` ainda não existir nesse banco, execute uma vez:
`database/001_ponto_facial.sql`

Não execute uma migração destrutiva sobre uma tabela já existente. A migração é idempotente e cria a tabela somente se ela não existir.

## Configuração
Opcionalmente, no `.env`, configure os termos de funções que não podem receber cadastro:
`PONTO_FUNCOES_NAO_ADMINISTRATIVAS=PROFESSOR,DOCENTE`

## Câmera e HTTPS
O cadastro usa `navigator.mediaDevices.getUserMedia`, que exige contexto seguro (HTTPS ou localhost). O cabeçalho Permissions-Policy foi ajustado para permitir câmera na própria origem (`camera=(self)`).

A biblioteca face-api.js e os pesos dos modelos são carregados do jsDelivr. O navegador precisa conseguir acessar `cdn.jsdelivr.net`.

## Arquivos principais
- `app/Controllers/FacialEnrollmentController.php`
- `app/Models/FacialEnrollment.php`
- `app/Config/routes.php`
- `app/Views/dashboard/index.php`
- `public/js/facial-enrollment.js`
- `public/css/facial-enrollment.css`

## Privacidade
Os descritores são dados biométricos sensíveis. O sistema não armazena as fotografias, mas os vetores faciais continuam sendo dados pessoais sensíveis. Restrinja o acesso ao dashboard/cadastro, proteja backups e defina política de retenção e descarte conforme a LGPD e as regras da instituição.

## Observação
Esta entrega implementa o cadastro/enrollment. Não inclui login facial 1:N nem validação de vivacidade (anti-spoofing) no CRM.

## Certificado TLS do SQL Server
Em ambientes internos nos quais o SQL Server usa certificado próprio não instalado como confiável no servidor web, configure no `.env`:
`DB_ENCRYPT=yes`
`DB_TRUST_SERVER_CERTIFICATE=yes`

Isso mantém a conexão criptografada, mas faz o driver confiar no certificado apresentado pelo SQL Server sem validar sua cadeia. Para produção, prefira instalar um certificado emitido por uma autoridade confiável e voltar `DB_TRUST_SERVER_CERTIFICATE=no`.
