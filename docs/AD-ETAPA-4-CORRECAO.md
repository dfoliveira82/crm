# Integração AD — correção da etapa 4

## O que foi alterado

- OU acadêmica fixada no backend (não depende do valor antigo de AD_TARGET_OU no .env): `OU=FADIPA,OU=ALUNOS,OU=ACADEMICO,OU=FADIPA,DC=fadipa,DC=local`. A OU enviada pelo navegador não é mais confiável nem utilizada para criar objetos.
- Conta autenticada continua sendo a definida no servidor em `AD_BIND_DN` e `AD_BIND_PASSWORD`. O campo visual não escolhe a identidade de autenticação.
- LDAPS permanece obrigatório na configuração atual. A aplicação não desativa a validação do certificado TLS.
- A criação LDAP foi separada da definição de senha: nesta etapa o objeto é criado com `userAccountControl=514` (conta desabilitada), sem `unicodePwd`.
- A aplicação consulta o objeto após o `ldap_add` e inclui `ldap_errno` e `LDAP_OPT_DIAGNOSTIC_MESSAGE` nas falhas para ajudar a localizar o motivo do `Server is unwilling to perform`.
- `sAMAccountName` preserva o RA literalmente, inclusive zeros à esquerda; RA fora do padrão alfanumérico de até 20 caracteres é rejeitado, não transformado.
- `mail` é formado como `RA@fadipa.br`; UPN usa `RA@fadipa.local`.
- Nenhuma senha é gerada a partir do RA. Nenhum usuário é habilitado nesta etapa.
- Nenhuma atualização de `SALUNOCOMPL.EMAILAD` é feita nesta etapa. Isso fica condicionado à etapa 5, ao processo de credencial e à confirmação final no AD.
- Lote limitado a 25 registros nesta etapa.

## Configuração no servidor

O ZIP não inclui `.env`, pois o arquivo original continha uma credencial de AD. Ao atualizar a aplicação, preserve o `.env` que já existe no servidor e confira manualmente:

```ini
AD_ENVIRONMENT=producao
AD_ALLOW_PRODUCTION=false
AD_TARGET_OU=OU=FADIPA,OU=ALUNOS,OU=ACADEMICO,OU=FADIPA,DC=fadipa,DC=local
AD_LDAP_HOST=ldaps://SRVFADIPA08.fadipa.local
AD_LDAP_PORT=636
AD_BASE_DN=DC=fadipa,DC=local
AD_BIND_DN=<identidade atualmente utilizada>
AD_BIND_PASSWORD=<senha mantida somente no servidor>
AD_DEFAULT_DOMAIN=fadipa.local
```

Não copie literalmente os marcadores entre `< >`. Mantenha o valor real de `AD_BIND_DN` e a senha no `.env` local do servidor. Para este pacote, `AD_ALLOW_PRODUCTION=false` mantém a escrita em produção bloqueada por padrão. Somente altere para `true` após autorização formal e depois de validar o certificado LDAPS e a delegação de permissões na OU.

## Significado do resultado

`criado_desabilitado` significa que o objeto foi criado e relido no AD, mas permanece desabilitado e sem senha. Não é uma conta pronta para o aluno utilizar. Não atualize `SALUNOCOMPL.EMAILAD` com base nesse status.

`erro` inclui código e diagnóstico LDAP quando o DC os fornece. Se continuar `Server is unwilling to perform`, verifique o texto `diagnostic`, a delegação de permissões Create Child (user) na OU e os eventos de Directory Service no controlador de domínio.

## Testes não executados

A validação estática de sintaxe PHP pode ser executada localmente. Não foi feita conexão com o domínio, não foi criado nenhum objeto e não foi testada a permissão real da conta de serviço.
