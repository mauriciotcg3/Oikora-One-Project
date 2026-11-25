<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Pessoas\Application\Consultas\FiltroPessoas;
use App\Modules\Pessoas\Application\Consultas\ListaDePessoas;
use App\Modules\Pessoas\Infrastructure\Pessoa;

/** Lista pelo model Pessoa: o filtro global de alcance e a RLS se aplicam sempre. */
final class ListaDePessoasPostgres implements ListaDePessoas
{
    private const ORDEM = [
        'nome' => ['nome_busca', 'asc'],
        'nascimento' => ['nascimento', 'asc'],
        'entrada_em' => ['entrada_em', 'asc'],
        'cadastro' => ['created_at', 'asc'],
    ];

    public function __construct(private readonly FiltroPessoasPostgres $filtros) {}

    public function buscar(FiltroPessoas $filtro, bool $comContatos): array
    {
        if ($filtro->semResultado) {
            return ['itens' => [], 'total' => 0];
        }

        $consulta = Pessoa::query()->whereNull('pessoas.excluida_em');
        $this->filtros->aplicar($consulta, $filtro);
        $total = (clone $consulta)->count();

        $colunas = ['pessoas.id', 'pessoas.uuid', 'pessoas.nome', 'pessoas.igreja_id', 'pessoas.vinculo', 'pessoas.situacao',
            'pessoas.tipo_membro_id', 'pessoas.nascimento', 'pessoas.matricula', 'pessoas.entrada_em'];
        $consulta->select($colunas);
        if ($comContatos) {
            foreach (['celular', 'email'] as $tipo) {
                $consulta->selectSub(
                    "select c.valor from pessoa_contatos c where c.pessoa_id = pessoas.id and c.tipo = '{$tipo}' order by c.principal desc, c.id limit 1",
                    $tipo,
                );
            }
        }

        $descendente = str_starts_with($filtro->ordem, '-');
        [$coluna] = self::ORDEM[ltrim($filtro->ordem, '-')] ?? self::ORDEM['nome'];
        $consulta->orderByRaw("pessoas.{$coluna} ".($descendente ? 'desc' : 'asc').' nulls last')->orderBy('pessoas.id');

        $itens = array_values($consulta->forPage($filtro->pagina, $filtro->porPagina)->toBase()->get()
            ->map(static function (object $linha) use ($comContatos): array {
                $item = [
                    'id' => (int) $linha->id,
                    'uuid' => (string) $linha->uuid,
                    'nome' => (string) $linha->nome,
                    'igreja_id' => (int) $linha->igreja_id,
                    'vinculo' => $linha->vinculo === null ? null : (string) $linha->vinculo,
                    'situacao' => $linha->situacao === null ? null : (string) $linha->situacao,
                    'tipo_membro_id' => $linha->tipo_membro_id === null ? null : (int) $linha->tipo_membro_id,
                    'nascimento' => $linha->nascimento === null ? null : substr((string) $linha->nascimento, 0, 10),
                    'matricula' => $linha->matricula === null ? null : (string) $linha->matricula,
                    'entrada_em' => $linha->entrada_em === null ? null : substr((string) $linha->entrada_em, 0, 10),
                ];
                if ($comContatos) {
                    $item['celular'] = $linha->celular === null ? null : (string) $linha->celular;
                    $item['email'] = $linha->email === null ? null : (string) $linha->email;
                }

                return $item;
            })->all());

        return ['itens' => $itens, 'total' => $total];
    }
}
