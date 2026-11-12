
# Mapeamento de tabelas

Edite exclusivamente `app/Config/tables.php`. Todos os identificadores são validados antes de compor SQL. Valores sempre usam parâmetros PDO.

## Formato

Cada entidade possui `table`, `columns` (chave lógica => coluna física) e, quando necessário, `filters`. Use `null` para indicar integração ainda não configurada. Os nomes abaixo são chaves lógicas da aplicação, não nomes presumidos do seu banco.

- `user`: chave de ligação do usuário RM aos registros (ex.: código do vendedor). Defina a coluna real em `columns.owner`.
- `quota`: meta, período, realizado e responsável.
- `deals`: negócio, valor, estágio, data, responsável e status.
- `bookings`: data, valor e categoria para série temporal.
- `suggestions`: texto, prioridade, identificador e status. Sem `status` não há UPDATE; a dispensa fica na sessão.
- `events`: título, início, fim, local e responsável.
- `reps`: nome/código do vendedor para ranking.

Cada model ignora entidades não configuradas e retorna coleção vazia ou `null`. Para implementar filtros de coligada/perfil, acrescente-os no model correspondente usando o `SessionUser`; nunca aceite o identificador do proprietário enviado pelo navegador.

## Consultas esperadas

- Quota: SELECT de meta e realizado do usuário/período.
- KPIs: agregações de negócios ganhos, negócios médios, ciclo e pipeline aberto.
- Bookings: soma de valores agrupada por semana ou mês e categoria.
- Deals: negócios abertos agrupados por estágio; lista de atenção por prazo/risco.
- Events: agenda do usuário no dia.
- Reps: ranking por realizado/meta.
- Suggestions: sugestões ativas do Copilot.

Não configure nomes até conferir o schema real e os vínculos com o código de usuário do TOTVS RM.
