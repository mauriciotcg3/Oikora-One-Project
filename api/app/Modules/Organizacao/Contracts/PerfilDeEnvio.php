<?php

namespace App\Modules\Organizacao\Contracts;

/**
 * O que o módulo Avisos precisa da organização para enviar (OIK-ADR-0008 §9, §14): o nome que assina
 * o remetente ("Nome via Oikora"), o contato que recebe as respostas e o fuso da janela diurna.
 */
final readonly class PerfilDeEnvio
{
    public function __construct(
        public string $uuid,
        public string $nome,
        public string $fuso,
        public ?string $responderPara,
    ) {}
}
