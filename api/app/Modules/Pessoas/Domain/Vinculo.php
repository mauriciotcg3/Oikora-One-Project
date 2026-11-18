<?php

namespace App\Modules\Pessoas\Domain;

/**
 * Vínculos da pessoa com a igreja (RN-03) e a máquina de estados (RN-07; plan.md §6): só avança
 * Visitante → Frequentador → Membro, sem pular e sem voltar. Quem deixa de participar é inativado
 * (Situacao), e a reativação volta ao mesmo vínculo. Só `membro` tem matrícula.
 */
enum Vinculo: string
{
    case Visitante = 'visitante';
    case Frequentador = 'frequentador';
    case Membro = 'membro';

    public function proximo(): ?self
    {
        return match ($this) {
            self::Visitante => self::Frequentador,
            self::Frequentador => self::Membro,
            self::Membro => null,
        };
    }

    /** @throws TransicaoVinculoInvalida */
    public function avancarPara(self $destino): self
    {
        if ($this->proximo() === $destino) {
            return $destino;
        }

        throw new TransicaoVinculoInvalida(match (true) {
            $destino === $this => 'A pessoa já tem esse vínculo.',
            $this->ordem() > $destino->ordem() => 'O vínculo não volta de etapa: quem deixa de participar é inativado.',
            default => sprintf('Para chegar a %s, a pessoa precisa passar por %s.', $destino->rotulo(), $this->proximo()?->rotulo() ?? ''),
        }, $this->proximo());
    }

    public function temMatricula(): bool
    {
        return $this === self::Membro;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Visitante => 'Visitante',
            self::Frequentador => 'Frequentador',
            self::Membro => 'Membro',
        };
    }

    private function ordem(): int
    {
        return match ($this) {
            self::Visitante => 0,
            self::Frequentador => 1,
            self::Membro => 2,
        };
    }
}
