<?php

return [
    'versao' => env('OIKORA_VERSAO', 'dev'),

    // Segredos próprios dos campos sensíveis (OIK-ADR-0004 §8), nunca derivados da APP_KEY.
    'cifra' => [
        'campos' => [
            'chave_atual' => env('OIKORA_CHAVE_CAMPOS'),
            'chaves_anteriores' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('OIKORA_CHAVES_CAMPOS_ANTERIORES', '')),
            ))),
        ],
        'cpf' => [
            'chave_hmac' => env('OIKORA_CHAVE_HMAC_CPF'),
        ],
    ],

    // Chave do HMAC que resume identificador e IP em `eventos_autenticacao` e nos limites.
    'chave_resumo' => env('OIKORA_CHAVE_RESUMO', env('APP_KEY')),

    // OpenTelemetry (OIK-ADR-0010 §9): desligado por padrão; ligar com OTEL_SDK_DISABLED=false e o
    // endpoint OTLP/HTTP do coletor (Grafana Alloy).
    'otel' => [
        'ligado' => ! filter_var(env('OTEL_SDK_DISABLED', true), FILTER_VALIDATE_BOOL),
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://alloy:4318'),
    ],

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

    // Validade e SMS vêm dos ADRs 0003 §7 e 0008 §11; tentativas, reenvios e desafio são provisórios (U-22).
    'codigos' => [
        'validade_minutos' => (int) env('OIKORA_CODIGO_VALIDADE', 10),
        'tentativas' => (int) env('OIKORA_CODIGO_TENTATIVAS', 5),
        'reenvios' => (int) env('OIKORA_CODIGO_REENVIOS', 4),
        'desafio_minutos' => (int) env('OIKORA_DESAFIO_VALIDADE', 30),
        'sms_por_hora' => (int) env('OIKORA_SMS_POR_HORA', 3),
        'sms_por_dia' => (int) env('OIKORA_SMS_POR_DIA', 10),
    ],

    // Regra das 24h do OIK-ADR-0003 §7.
    'dois_fatores' => [
        'canal_bloqueado_horas' => (int) env('OIKORA_2FA_CANAL_BLOQUEADO_HORAS', 24),
    ],

    // Validade do OIK-ADR-0003 §11; o limite de tentativas é provisório (U-22).
    'senha' => [
        'confirmacao_minutos' => (int) env('OIKORA_SENHA_CONFIRMACAO', 15),
        'tentativas_de_confirmacao' => (int) env('OIKORA_SENHA_TENTATIVAS_CONFIRMACAO', 5),
        'janela_segundos' => (int) env('OIKORA_SENHA_JANELA', 900),
    ],

    // Valores provisórios (U-22).
    'verificacao' => [
        'pedidos' => (int) env('OIKORA_VERIFICACAO_PEDIDOS', 5),
        'janela_segundos' => (int) env('OIKORA_VERIFICACAO_JANELA', 900),
    ],

    // Validade do OIK-ADR-0003 §8; os limites de pedidos são provisórios (U-22).
    'recuperacao' => [
        'validade_minutos' => (int) env('OIKORA_RECUPERACAO_VALIDADE', 60),
        'janela_segundos' => (int) env('OIKORA_RECUPERACAO_JANELA', 900),
        'pedidos_por_identificador' => (int) env('OIKORA_RECUPERACAO_PEDIDOS_IDENTIFICADOR', 5),
        'pedidos_por_ip' => (int) env('OIKORA_RECUPERACAO_PEDIDOS_IP', 30),
    ],
];
