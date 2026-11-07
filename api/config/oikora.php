<?php

return [
    'versao' => env('OIKORA_VERSAO', 'dev'),

    // Chave do HMAC que resume identificador e IP em `eventos_autenticacao` e nos limites.
    'chave_resumo' => env('OIKORA_CHAVE_RESUMO', env('APP_KEY')),

    // Filas conhecidas (plan.md §1), mostradas em GET /api/v1/saude mesmo quando vazias.
    'filas' => ['codigos', 'avisos', 'webhooks', 'default', 'pesadas'],

    // Valores provisórios: os números definitivos dependem da Spec de operação (U-22).
    'login' => [
        'janela_segundos' => (int) env('OIKORA_LOGIN_JANELA', 900),
        'tentativas_por_identificador' => (int) env('OIKORA_LOGIN_TENTATIVAS_IDENTIFICADOR', 5),
        'tentativas_por_ip' => (int) env('OIKORA_LOGIN_TENTATIVAS_IP', 30),
        'bloqueio_inicial_segundos' => (int) env('OIKORA_LOGIN_BLOQUEIO_INICIAL', 60),
        'bloqueio_maximo_segundos' => (int) env('OIKORA_LOGIN_BLOQUEIO_MAXIMO', 3600),
        'memoria_violacoes_segundos' => (int) env('OIKORA_LOGIN_MEMORIA_VIOLACOES', 86400),
    ],
];
