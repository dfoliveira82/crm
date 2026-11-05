<?php
declare(strict_types=1);
/*
 * Configure apenas com nomes confirmados no banco existente.
 * table: nome físico (pode incluir schema, ex. dbo.Tabela).
 * columns: alias lógico => coluna física.
 */
return [
    'quota' => ['table' => null, 'columns' => ['owner'=>null,'target'=>null,'actual'=>null,'period'=>null]],
    'deals' => ['table' => null, 'columns' => ['id'=>null,'owner'=>null,'title'=>null,'amount'=>null,'stage'=>null,'status'=>null,'close_date'=>null,'created_at'=>null,'updated_at'=>null]],
    'bookings' => ['table' => null, 'columns' => ['owner'=>null,'booked_at'=>null,'amount'=>null,'category'=>null]],
    'suggestions' => ['table' => null, 'columns' => ['id'=>null,'owner'=>null,'text'=>null,'priority'=>null,'status'=>null]],
    'events' => ['table' => null, 'columns' => ['id'=>null,'owner'=>null,'title'=>null,'starts_at'=>null,'ends_at'=>null]],
    'reps' => ['table' => null, 'columns' => ['code'=>null,'name'=>null,'owner'=>null]],
    'user' => ['table' => null, 'columns' => ['rm_code'=>null,'name'=>null,'email'=>null,'role'=>null,'company'=>null]],
];
