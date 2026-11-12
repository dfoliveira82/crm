# Integração NFS-e - arquitetura e implantação

## Etapa atual

Esta versão entrega a estrutura inicial de persistência e o painel de acompanhamento. A emissão fiscal e a sincronização automática ainda não estão habilitadas.

## Banco de dados

Execute `database/004_nfse.sql` no banco utilizado pela aplicação CRM/TotvsWEB. O script é idempotente para criação inicial e não modifica tabelas do TOTVS RM.

Tabelas criadas:

- `NFS_CONFIG`: parâmetros fiscais por coligada e ambiente.
- `NFS_SERVICOS`: serviços, item da lista, CNAE e alíquota validados pela contabilidade.
- `NFS_COBRANCA`: espelho da cobrança RM e valores apurados para emissão.
- `NFS_BAIXAS`: espelho individual das baixas/estornos RM.
- `NFS_FILA`: controle de revisão, autorização e emissão com chave de idempotência.
- `NFS_DOCUMENTO`: número, protocolo, XML e referência do PDF da NFS-e.
- `NFS_EVENTO`: trilha de auditoria do ciclo de vida.
- `NFS_LOG`: log técnico do módulo.

## Mapeamento RM

O arquivo `app/Config/nfse.php` contém o mapeamento explícito. Os campos estão vazios e `enabled` está `false` intencionalmente. Não ativar sem conferir o schema real e os vínculos da instalação.

Tabelas que deverão ser avaliadas com o dicionário de dados do RM:

- `FLAN`: lançamento financeiro (IDLAN, CODCOLIGADA, CODCFO, valor original e saldo).
- `FLANBAIXA`: baixas, pagamentos, descontos, juros, multas e estornos.
- `FCFO`: cadastro do tomador, documento e contato.

Os nomes acima são referências de tabelas comuns do RM, não uma confirmação de que todos os campos ou regras estejam iguais no ambiente.

## Regras que devem permanecer

1. Uma NFS-e por mensalidade/lançamento financeiro.
2. Não emitir com pagamento parcial.
3. Somar baixas válidas do lançamento, desconsiderando baixas estornadas/canceladas conforme o status real do RM.
4. Não duplicar notas: chave única de origem e chave de idempotência.
5. Não escrever nas tabelas do RM.
6. Exigir revisão/autorização do operador antes do envio fiscal.
7. Não assumir que valor original, valor pago ou valor líquido é automaticamente a base tributável. A regra deve ser aprovada pela contabilidade.

## Próximo insumo necessário

Para finalizar a leitura real do RM, precisamos validar o schema das tabelas `FLAN`, `FLANBAIXA` e `FCFO`, especialmente os campos de saldo, valor de baixa, status/estorno e identificação do cliente. Também precisamos confirmar a regra contábil para descontos, juros e multas e o item de serviço/aliquota municipal.
