<?php

// Fortify headless (OIK-ADR-0003 §14). As rotas ficam em Modules/Identidade/Presentation/rotas.php,
// no caminho do contrato (plan.md §7). TOTP e confirmação de senha entram com a T-12.
return [
    'guard' => 'web',
    'passwords' => 'contas',
    'username' => 'identificador',
    'email' => 'email',
    'lowercase_usernames' => false,
    'home' => '/',
    'views' => false,
    'limiters' => [],
    'features' => [],
];
