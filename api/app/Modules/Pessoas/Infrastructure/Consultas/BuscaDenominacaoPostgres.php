<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\ContextoDeAlcanceAusente;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\CriteriosDuplicidade;
use App\Modules\Pessoas\Infrastructure\Escopos\PessoaEscopoAlcance;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

/** Implementação da BuscaDenominacao: o único lugar autorizado a remover o PessoaEscopoAlcance. */
final readonly class BuscaDenominacaoPostgres implements BuscaDenominacao
{
    /** Colunas que saem da organização inteira — nada além disso entra no SELECT. */
    private const COLUNAS = ['uuid', 'nome', 'igreja_id', 'vinculo'];

    public function __construct(private AlcanceAtual $alcance) {}

    public function buscar(string $termo, int $limite = 20): array
    {
        $consulta = $this->semAlcance()->select(self::COLUNAS)->whereNull('excluida_em');
        $digitos = preg_replace('/\D+/', '', $termo) ?? '';

        $consulta->where(function (Builder $filtro) use ($termo, $digitos): void {
            if (str_contains($termo, '@')) {
                $filtro->whereExists(fn ($contato) => $contato->selectRaw('1')->from('pessoa_contatos')
                    ->whereColumn('pessoa_contatos.pessoa_id', 'pessoas.id')
                    ->whereRaw('lower(pessoa_contatos.valor) = lower(?)', [trim($termo)]));

                return;
            }

            if (strlen($digitos) >= 10 && strlen($digitos) <= 13) {
                if (strlen($digitos) === 11) {
                    $filtro->orWhere('cpf_hash', $this->hashDoCpf($digitos));
                }
                $filtro->orWhereExists(fn ($contato) => $contato->selectRaw('1')->from('pessoa_contatos')
                    ->whereColumn('pessoa_contatos.pessoa_id', 'pessoas.id')
                    ->whereIn('pessoa_contatos.tipo', ['celular', 'telefone'])
                    ->whereRaw("regexp_replace(pessoa_contatos.valor, '\\D', '', 'g') like ?", ['%'.$digitos]));

                return;
            }

            $nome = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], Str::lower(Str::ascii(trim($termo))));
            $filtro->where('nome_busca', 'like', '%'.$nome.'%');
        });

        return array_values($consulta->orderBy('nome')->limit($limite)->get()->map(static fn (Pessoa $pessoa): array => [
            'uuid' => $pessoa->uuid,
            'nome' => $pessoa->nome,
            'igreja_id' => $pessoa->igreja_id,
            'vinculo' => $pessoa->vinculo,
        ])->all());
    }

    public function existe(string $uuid): bool
    {
        return $this->semAlcance()->where('uuid', $uuid)->exists();
    }

    public function idPorUuid(string $uuid): ?int
    {
        $id = $this->semAlcance()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function cpfEmUso(string $cpf): bool
    {
        return $this->semAlcance()->where('cpf_hash', $this->hashDoCpf(preg_replace('/\D+/', '', $cpf) ?? ''))->exists();
    }

    public function duplicados(CriteriosDuplicidade $criterios, ?int $pessoaId = null, int $limite = 10): array
    {
        /** @var array<'nome_nascimento'|'cpf'|'email', array{0: literal-string, 1: list<string>}> $motivos */
        $motivos = [];

        if ($criterios->nome !== null && $criterios->nascimento !== null) {
            // Mesma normalização do `saving` de Pessoa: minúsculo e sem acento.
            $motivos['nome_nascimento'] = ['(nome_busca = ? and nascimento = ?)', [Str::lower(Str::ascii($criterios->nome)), $criterios->nascimento]];
        }
        if ($criterios->cpf !== null) {
            $motivos['cpf'] = ['(cpf_hash is not null and cpf_hash = ?)', [$this->hashDoCpf(preg_replace('/\D+/', '', $criterios->cpf) ?? '')]];
        }
        if ($criterios->email !== null) {
            $motivos['email'] = [
                "exists (select 1 from pessoa_contatos c where c.organizacao_id = pessoas.organizacao_id and c.pessoa_id = pessoas.id and c.tipo = 'email' and lower(c.valor) = ?)",
                [$criterios->email],
            ];
        }

        if ($motivos === []) {
            return [];
        }

        // Inclui a lixeira (U-20): o detector oferece recuperar a ficha.
        $consulta = $this->semAlcance()->select(self::COLUNAS)->selectRaw('excluida_em is not null as na_lixeira');

        foreach ($motivos as $nome => [$sql, $valores]) {
            $consulta->selectRaw("{$sql} as motivo_{$nome}", $valores);
        }

        $consulta->where(function (Builder $filtro) use ($motivos): void {
            foreach ($motivos as [$sql, $valores]) {
                $filtro->orWhereRaw($sql, $valores);
            }
        });

        if ($pessoaId !== null) {
            $consulta->where('id', '<>', $pessoaId)->whereNotExists(fn ($par) => $par->selectRaw('1')->from('pares_nao_duplicados as par')
                ->whereColumn('par.organizacao_id', 'pessoas.organizacao_id')
                ->whereRaw('(par.pessoa_a_id = least(pessoas.id, ?) and par.pessoa_b_id = greatest(pessoas.id, ?))', [$pessoaId, $pessoaId]));
        }

        return array_values($consulta->orderBy('nome')->limit($limite)->get()->map(static fn (Pessoa $pessoa): array => [
            'uuid' => (string) $pessoa->uuid,
            'nome' => (string) $pessoa->nome,
            'igreja_id' => (int) $pessoa->igreja_id,
            'vinculo' => $pessoa->vinculo,
            'na_lixeira' => (bool) $pessoa->getAttribute('na_lixeira'),
            'motivos' => array_values(array_filter(
                array_keys($motivos),
                static fn (string $nome): bool => (bool) $pessoa->getAttribute('motivo_'.$nome),
            )),
        ])->all());
    }

    /** @return Builder<Pessoa> */
    private function semAlcance(): Builder
    {
        if (! $this->alcance->definido()) {
            throw new ContextoDeAlcanceAusente;
        }

        return Pessoa::query()->withoutGlobalScope(PessoaEscopoAlcance::class);
    }

    private function hashDoCpf(string $digitos): string
    {
        $chave = (string) config('oikora.cifra.cpf.chave_hmac');
        if ($chave === '') {
            throw new LogicException('OIKORA_CHAVE_HMAC_CPF não foi configurada.');
        }

        return hash_hmac('sha256', $digitos, $chave);
    }
}
