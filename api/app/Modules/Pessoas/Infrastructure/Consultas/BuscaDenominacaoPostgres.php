<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\ContextoDeAlcanceAusente;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
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
