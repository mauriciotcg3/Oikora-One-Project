<?php

namespace App\Modules\Pessoas\Domain;

/** Situação da pessoa (RN-06, RN-07): sem valor gravado, está ativa. A inativação não muda o vínculo. */
enum Situacao: string
{
    case Ativa = 'ativa';
    case Inativa = 'inativa';

    public static function daColuna(?string $valor): self
    {
        return $valor === null ? self::Ativa : self::from($valor);
    }

    /** Lista de motivos da mudança (U-28): inativação e reativação têm listas próprias. */
    public function listaDeMotivos(): ListaValores
    {
        return $this === self::Inativa ? ListaValores::MotivoInativacao : ListaValores::MotivoReativacao;
    }
}
