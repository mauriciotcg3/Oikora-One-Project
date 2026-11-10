<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Pessoas\Application\Consultas\Fichas;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use App\Modules\Pessoas\Infrastructure\PessoaContato;
use App\Modules\Pessoas\Infrastructure\PessoaEndereco;
use DateTimeInterface;

/** Tabelas filhas são lidas pela Pessoa já autorizada, nunca listadas por fora dela (OIK-ADR-0004 §5). */
final class FichasEloquent implements Fichas
{
    public function localizar(string $uuid): ?array
    {
        $pessoa = Pessoa::query()->select(['id', 'uuid', 'igreja_id'])->where('uuid', $uuid)->first();

        return $pessoa === null ? null : [
            'id' => (int) $pessoa->getKey(),
            'uuid' => $pessoa->uuid,
            'igreja_id' => $pessoa->igreja_id,
        ];
    }

    public function ler(int $id, array $colunas): array
    {
        $pessoa = Pessoa::query()->select($colunas)->whereKey($id)->firstOrFail();

        $valores = [];
        foreach ($colunas as $coluna) {
            $valor = $pessoa->getAttribute($coluna);
            $valores[$coluna] = $valor instanceof DateTimeInterface ? $valor->format('Y-m-d') : $valor;
        }

        return $valores;
    }

    public function contatos(int $id): array
    {
        return array_values(PessoaContato::query()
            ->where('pessoa_id', $id)
            ->orderByDesc('principal')
            ->orderBy('id')
            ->get(['uuid', 'tipo', 'valor', 'e_whatsapp', 'principal'])
            ->map(static fn (PessoaContato $contato): array => [
                'uuid' => (string) $contato->getAttribute('uuid'),
                'tipo' => (string) $contato->getAttribute('tipo'),
                'valor' => (string) $contato->getAttribute('valor'),
                'e_whatsapp' => (bool) $contato->getAttribute('e_whatsapp'),
                'principal' => (bool) $contato->getAttribute('principal'),
            ])
            ->all());
    }

    public function enderecos(int $id): array
    {
        $campos = ['uuid', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'pais'];

        return array_values(PessoaEndereco::query()
            ->where('pessoa_id', $id)
            ->orderBy('id')
            ->get($campos)
            ->map(static function (PessoaEndereco $endereco) use ($campos): array {
                $linha = [];
                foreach ($campos as $campo) {
                    $valor = $endereco->getAttribute($campo);
                    $linha[$campo] = $valor === null ? null : (string) $valor;
                }

                return $linha;
            })
            ->all());
    }
}
