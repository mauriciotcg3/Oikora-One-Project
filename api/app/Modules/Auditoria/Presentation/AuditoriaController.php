<?php

namespace App\Modules\Auditoria\Presentation;

use App\Modules\Auditoria\Application\Consultas\ConsultarAuditoria;
use App\Modules\Auditoria\Application\Consultas\FiltroAuditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /painel/auditoria` (plan.md §7; T-46): paginada (25, 50 ou 100), do mais recente para o mais
 * antigo, com filtros de período (datas no fuso da organização, `ate` inclusivo), evento, ator, igreja,
 * busca segura em evento/alvo, resultado e alvo (`alvo_tipo` e `alvo`).
 */
final class AuditoriaController
{
    public function listar(
        Request $request,
        ConsultarAuditoria $consultar,
        AcessosDaOrganizacao $acessos,
        ContatosDaPessoa $pessoas,
        CatalogoIgrejas $igrejas,
        ConsultaOrganizacoes $organizacoes,
    ): JsonResponse {
        $dados = $request->validate([
            'de' => ['sometimes', 'date_format:Y-m-d'],
            'ate' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:de'],
            'evento' => ['sometimes', Rule::enum(EventoAuditoria::class)],
            'ator' => ['sometimes', 'uuid'],
            'igreja' => ['sometimes', 'uuid'],
            'alvo_tipo' => ['sometimes', 'string', 'regex:/^[a-z][a-z0-9_]{0,79}$/'],
            'alvo' => ['sometimes', 'string', 'max:80'],
            'busca' => ['sometimes', 'string', 'min:2', 'max:80', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
            'resultado' => ['sometimes', Rule::in(['sucesso', 'falha'])],
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([25, 50, 100])],
        ]);

        if (! $consultar->permitido()) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        $organizacaoId = (int) $request->attributes->get('organizacao_id');
        $fuso = new DateTimeZone($organizacoes->perfilDeEnvio($organizacaoId)->fuso);
        $app = new DateTimeZone((string) config('app.timezone'));
        $dia = static fn (string $data, string $ajuste = 'today'): DateTimeImmutable => (new DateTimeImmutable($data, $fuso))
            ->modify($ajuste)->setTimezone($app);

        // Ator que não existe na organização: nenhum evento, sem dizer se o uuid existe fora dela.
        $ator = isset($dados['ator']) ? ($acessos->porUuid($dados['ator'])['id'] ?? 0) : null;
        $igreja = isset($dados['igreja']) ? ($igrejas->igrejaPorUuid($dados['igreja']) ?? 0) : null;
        $filtro = new FiltroAuditoria(
            de: isset($dados['de']) ? $dia($dados['de']) : null,
            ate: isset($dados['ate']) ? $dia($dados['ate'], 'tomorrow') : null,
            evento: $dados['evento'] ?? null,
            atorAcessoId: $ator,
            igrejaId: $igreja,
            alvoTipo: $dados['alvo_tipo'] ?? null,
            alvoId: $dados['alvo'] ?? null,
            busca: $dados['busca'] ?? null,
            resultado: $dados['resultado'] ?? null,
            pagina: (int) ($dados['pagina'] ?? 1),
            porPagina: (int) ($dados['por_pagina'] ?? 25),
        );

        $resultado = $consultar->executar($filtro);
        $itens = $resultado['itens'];
        $atoresIds = array_values(array_unique(array_filter(array_column($itens, 'acesso_id'), 'is_int')));
        $atores = $acessos->uuids($atoresIds);
        $pessoasDosAtores = $acessos->pessoas($atoresIds);
        $nomesDosAtores = $pessoas->nomes(array_values(array_unique($pessoasDosAtores)));
        $resumo = $igrejas->resumo(array_values(array_unique(array_filter(array_column($itens, 'igreja_id'), 'is_int'))));

        if ($filtro->resultado === 'falha') {
            Log::info('auditoria.consultada', ['resultado' => 'falha', 'total' => $resultado['total']]);
        }

        return new JsonResponse([
            'data' => array_map(static fn (array $linha): array => [
                'ocorrido_em' => (new DateTimeImmutable($linha['ocorrido_em']))->format(DATE_ATOM),
                'evento' => $linha['evento'],
                'ator' => [
                    'acesso' => $linha['acesso_id'] === null ? null : ($atores[$linha['acesso_id']] ?? null),
                    'nome' => $linha['acesso_id'] === null ? null : ($nomesDosAtores[$pessoasDosAtores[$linha['acesso_id']] ?? 0] ?? null),
                    'plataforma' => $linha['operador_id'] !== null,
                ],
                'alvo' => ['tipo' => $linha['alvo_tipo'], 'id' => $linha['alvo_id']],
                'igreja' => $linha['igreja_id'] === null ? null : ($resumo[$linha['igreja_id']] ?? null),
                'campos' => $linha['campos'],
                'metadados' => $linha['metadados'] === [] ? new stdClass : $linha['metadados'],
                'canal' => $linha['canal'],
                'request_id' => $linha['request_id'],
                'resultado' => $linha['resultado'],
                'codigo_falha' => $linha['codigo_falha'],
                'trace_id' => $linha['trace_id'],
            ], $itens),
            'meta' => ['pagina' => $filtro->pagina, 'por_pagina' => $filtro->porPagina, 'total' => $resultado['total']],
        ]);
    }
}
