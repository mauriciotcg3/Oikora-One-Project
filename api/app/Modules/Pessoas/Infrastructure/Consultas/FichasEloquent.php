<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Pessoas\Application\Consultas\Fichas;
use App\Modules\Pessoas\Domain\CampoFicha;
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

    public function preenchidos(int $id, array $campos): array
    {
        $porTabela = ['pessoas' => [], 'pessoa_contatos' => [], 'pessoa_enderecos' => []];
        foreach ($campos as $chave) {
            $campo = CampoFicha::tryFrom($chave);
            if ($campo !== null) {
                $porTabela[$campo->tabela()][] = $campo;
            }
        }

        $preenchidos = [];

        // Colunas vêm só do catálogo (enum), nunca da requisição.
        if ($porTabela['pessoas'] !== []) {
            $selecao = implode(', ', array_map(
                static fn (CampoFicha $campo): string => "({$campo->value} is not null and {$campo->value}::text <> '') as {$campo->value}",
                $porTabela['pessoas'],
            ));
            $linha = Pessoa::query()->selectRaw($selecao)->whereKey($id)->first()?->getAttributes() ?? [];
            foreach ($porTabela['pessoas'] as $campo) {
                if (($linha[$campo->value] ?? false) === true) {
                    $preenchidos[] = $campo->value;
                }
            }
        }

        foreach ($porTabela['pessoa_contatos'] as $campo) {
            $consulta = PessoaContato::query()->where('pessoa_id', $id);
            $existe = $campo === CampoFicha::Whatsapp
                ? $consulta->where('tipo', 'celular')->where('e_whatsapp', true)->exists()
                : $consulta->where('tipo', $campo->value)->exists();
            if ($existe) {
                $preenchidos[] = $campo->value;
            }
        }

        foreach ($porTabela['pessoa_enderecos'] as $campo) {
            if (PessoaEndereco::query()->where('pessoa_id', $id)->whereNotNull($campo->value)->where($campo->value, '<>', '')->exists()) {
                $preenchidos[] = $campo->value;
            }
        }

        return $preenchidos;
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
