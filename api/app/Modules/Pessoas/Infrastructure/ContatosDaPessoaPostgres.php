<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use App\Modules\Pessoas\Domain\Contato;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rotina de sistema dos avisos, sob a RLS da organização em contexto: a Pessoa é lida pelo model com o
 * alcance de sistema (o filtro continua ligado) e Pessoa na lixeira não recebe nada.
 */
final readonly class ContatosDaPessoaPostgres implements ContatosDaPessoa
{
    public function __construct(private AlcanceAtual $alcance) {}

    public function endereco(int $pessoaId, string $canal, bool $respeitarDescadastro): ?string
    {
        if ($this->idAtivo(fn (Builder $consulta) => $consulta->whereKey($pessoaId)) === null) {
            return null;
        }

        $valor = $this->contatosDoCanal($canal)
            ->where('pessoa_id', $pessoaId)
            ->when($respeitarDescadastro, fn (Builder $consulta) => $consulta->whereNull('descadastrado_em'))
            ->orderByDesc('principal')
            ->orderBy('id')
            ->value('valor');

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    public function uuid(int $pessoaId): ?string
    {
        $uuid = $this->comoSistema(fn (): mixed => Pessoa::query()->whereKey($pessoaId)->value('uuid'));

        return is_string($uuid) ? $uuid : null;
    }

    public function descadastrar(string $pessoaUuid, string $canal): bool
    {
        $pessoaId = $this->idAtivo(fn (Builder $consulta) => $consulta->where('uuid', $pessoaUuid));

        return $pessoaId !== null && $this->contatosDoCanal($canal)
            ->where('pessoa_id', $pessoaId)
            ->whereNull('descadastrado_em')
            ->update(['descadastrado_em' => now(), 'updated_at' => now()]) > 0;
    }

    public function descadastrarWhatsApp(string $celularE164): int
    {
        $celular = Contato::celular($celularE164);

        return $celular === null ? 0 : $this->contatosDoCanal('whatsapp')
            ->where('valor', $celular)
            ->whereNull('descadastrado_em')
            ->update(['descadastrado_em' => now(), 'updated_at' => now()]);
    }

    /** @return Builder<PessoaContato> */
    private function contatosDoCanal(string $canal): Builder
    {
        return $canal === 'whatsapp'
            ? PessoaContato::query()->where('tipo', 'celular')->where('e_whatsapp', true)
            : PessoaContato::query()->where('tipo', 'email');
    }

    /** @param  Closure(Builder<Pessoa>): Builder<Pessoa>  $filtro */
    private function idAtivo(Closure $filtro): ?int
    {
        $id = $this->comoSistema(fn (): mixed => $filtro(Pessoa::query())->whereNull('excluida_em')->value('id'));

        return $id === null ? null : (int) $id;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $acao
     * @return T
     */
    private function comoSistema(Closure $acao): mixed
    {
        return $this->alcance->comoSistema((int) DB::scalar('select app_organizacao_atual()'), $acao);
    }
}
