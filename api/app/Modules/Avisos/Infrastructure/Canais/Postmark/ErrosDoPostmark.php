<?php

namespace App\Modules\Avisos\Infrastructure\Canais\Postmark;

use Illuminate\Http\Client\Response;

/** Classificação dos erros do Postmark, igual para códigos e avisos (OIK-ADR-0008 §3). */
trait ErrosDoPostmark
{
    /** ErrorCode do Postmark que não se resolvem com nova tentativa. */
    private const DEFINITIVOS = [
        10 => 'credencial_invalida',
        300 => 'email_invalido',
        406 => 'destinatario_inativo',
        412 => 'conta_restrita',
    ];

    public function provedor(): string
    {
        return 'postmark';
    }

    protected function classificar(Response $resposta): array
    {
        if ($this->statusTemporario($resposta)) {
            return ['postmark_indisponivel', true];
        }

        $codigo = (int) $resposta->json('ErrorCode');

        return [self::DEFINITIVOS[$codigo] ?? 'postmark_'.$codigo, false];
    }
}
