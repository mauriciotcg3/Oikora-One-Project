<?php

namespace App\Modules\Identidade\Contracts;

/**
 * Convite do primeiro administrador, emitido pelo provisionamento (plan.md §3.3, §4): papel
 * Administrador, escopo `organizacao`, sem emissor (a plataforma). Sai pelo aviso `convite.painel`;
 * com a organização em migração, o aviso fica registrado e o envio é suprimido (OIK-ADR-0009 §5).
 */
interface ConviteDoProvisionamento
{
    /** @return array{uuid: string, token: string} o token só existe aqui; o banco guarda o hash */
    public function convidarAdministrador(int $organizacaoId, int $pessoaId, int $papelAdministradorId): array;

    /** Há convite válido ou acesso para a Pessoa: rodar o provisionamento de novo não emite outro. */
    public function jaConvidada(int $pessoaId): bool;
}
