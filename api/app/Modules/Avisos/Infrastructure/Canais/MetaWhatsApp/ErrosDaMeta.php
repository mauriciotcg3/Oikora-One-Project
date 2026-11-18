<?php

namespace App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp;

use Illuminate\Http\Client\Response;

/** Classificação dos erros da Cloud API da Meta, igual para códigos e avisos (OIK-ADR-0008 §3). */
trait ErrosDaMeta
{
    /** Códigos de erro da Meta que pedem nova tentativa (limite de taxa ou instabilidade). */
    private const TEMPORARIOS = [1, 2, 4, 80007, 130429, 131000, 131048, 131056, 133004];

    /** Códigos de erro com motivo próprio; os demais definitivos viram `meta_<código>`. */
    private const MOTIVOS = [
        131026 => 'numero_sem_whatsapp',
        133010 => 'numero_sem_whatsapp',
        131030 => 'numero_nao_permitido',
        131042 => 'conta_sem_pagamento',
        131031 => 'conta_bloqueada',
        190 => 'credencial_invalida',
        132000 => 'template_rejeitado',
        132001 => 'template_rejeitado',
        132015 => 'template_rejeitado',
        132016 => 'template_rejeitado',
    ];

    public function provedor(): string
    {
        return 'meta';
    }

    protected function classificar(Response $resposta): array
    {
        $codigo = (int) $resposta->json('error.code');

        if (in_array($codigo, self::TEMPORARIOS, true) || ($codigo === 0 && $this->statusTemporario($resposta))) {
            return ['meta_indisponivel', true];
        }

        return [self::MOTIVOS[$codigo] ?? 'meta_'.$codigo, false];
    }
}
