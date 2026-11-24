<?php

return [
    // Modo da exclusão LGPD (OIK-ADR-0005 §8; RN-19; OPEN-013): `anonimizar` ou `apagar`, sem padrão. Em
    // produção, sem o modo, o removedor de Pessoas recusa executar (`lgpd.erasure.blocked_no_policy`).
    'modo_exclusao' => env('OIKORA_LGPD_MODO_EXCLUSAO'),

    // Dias que a Pessoa excluída fica na lixeira e pode ser recuperada (RN-19).
    'dias_lixeira' => 30,
];
