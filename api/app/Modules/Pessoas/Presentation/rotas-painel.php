<?php

use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Presentation\CadastroRapidoController;
use App\Modules\Pessoas\Presentation\Configuracao\ListasController;
use App\Modules\Pessoas\Presentation\Configuracao\ObrigatoriosController;
use App\Modules\Pessoas\Presentation\DatasDaFeController;
use App\Modules\Pessoas\Presentation\DuplicadosController;
use App\Modules\Pessoas\Presentation\ExportacoesController;
use App\Modules\Pessoas\Presentation\FichaController;
use App\Modules\Pessoas\Presentation\ListaPessoasController;
use App\Modules\Pessoas\Presentation\LixeiraController;
use App\Modules\Pessoas\Presentation\PessoasController;
use App\Modules\Pessoas\Presentation\RegistrosController;
use App\Modules\Pessoas\Presentation\TransferenciasController;
use App\Modules\Pessoas\Presentation\VinculoController;
use Illuminate\Support\Facades\Route;

Route::get('pessoas', ListaPessoasController::class);
Route::get('novos-convertidos', [DatasDaFeController::class, 'novosConvertidos']);
Route::get('aniversarios', [DatasDaFeController::class, 'aniversarios']);
Route::post('pessoas/{pessoa}/conversao', [DatasDaFeController::class, 'registrarConversao'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/batismo', [DatasDaFeController::class, 'registrarBatismo'])->whereUuid('pessoa');
Route::post('exportacoes', [ExportacoesController::class, 'criar']);
Route::get('exportacoes/{exportacao}', [ExportacoesController::class, 'mostrar'])->whereUuid('exportacao');
Route::get('pessoas/duplicidade', [CadastroRapidoController::class, 'duplicidade']);
Route::post('pessoas', [CadastroRapidoController::class, 'cadastrar']);
Route::get('pessoas/{pessoa}', [PessoasController::class, 'mostrar'])->whereUuid('pessoa');
Route::patch('pessoas/{pessoa}', [FichaController::class, 'editar'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/foto', [FichaController::class, 'foto'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/vinculo', [VinculoController::class, 'vinculo'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/situacao', [VinculoController::class, 'situacao'])->whereUuid('pessoa');
Route::get('pessoas/{pessoa}/linha-do-tempo', [RegistrosController::class, 'linhaDoTempo'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/linha-do-tempo', [RegistrosController::class, 'registrar'])->whereUuid('pessoa');
Route::get('pessoas/{pessoa}/relacionamentos', [RegistrosController::class, 'relacionamentos'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/relacionamentos', [RegistrosController::class, 'relacionar'])->whereUuid('pessoa');
Route::delete('pessoas/{pessoa}/relacionamentos/{relacionamento}', [RegistrosController::class, 'removerRelacionamento'])->whereUuid(['pessoa', 'relacionamento']);
Route::get('pessoas/{pessoa}/funcoes', [RegistrosController::class, 'funcoes'])->whereUuid('pessoa');
Route::post('pessoas/{pessoa}/funcoes', [RegistrosController::class, 'atribuirFuncao'])->whereUuid('pessoa');
Route::patch('pessoas/{pessoa}/funcoes/{funcao}', [RegistrosController::class, 'encerrarFuncao'])->whereUuid(['pessoa', 'funcao']);
Route::put('pessoas/{pessoa}/tipo-membro', [RegistrosController::class, 'tipoMembro'])->whereUuid('pessoa');
Route::get('busca', [PessoasController::class, 'buscar']);

Route::delete('pessoas/{pessoa}', [LixeiraController::class, 'excluir'])->whereUuid('pessoa');
Route::get('lixeira', [LixeiraController::class, 'listar']);
Route::post('lixeira/{pessoa}/recuperar', [LixeiraController::class, 'recuperar'])->whereUuid('pessoa');

Route::get('duplicados', [DuplicadosController::class, 'listar']);
Route::post('duplicados/nao-duplicados', [DuplicadosController::class, 'naoDuplicadas']);
Route::post('mesclagens', [DuplicadosController::class, 'mesclar']);
Route::post('mesclagens/{mesclagem}/desfazer', [DuplicadosController::class, 'desfazer'])->whereUuid('mesclagem');

Route::get('transferencias', [TransferenciasController::class, 'listar']);
Route::post('transferencias', [TransferenciasController::class, 'solicitar']);
Route::post('transferencias/{transferencia}/aprovar', [TransferenciasController::class, 'aprovar'])->whereUuid('transferencia');
Route::post('transferencias/{transferencia}/recusar', [TransferenciasController::class, 'recusar'])->whereUuid('transferencia');

$listas = array_map(static fn (ListaValores $lista): string => $lista->value, ListaValores::cases());
Route::get('listas/{lista}', [ListasController::class, 'listar'])->whereIn('lista', $listas);
Route::post('listas/{lista}', [ListasController::class, 'criar'])->whereIn('lista', $listas);
Route::put('listas/{lista}/{item}', [ListasController::class, 'editar'])->whereIn('lista', $listas)->whereUuid('item');

Route::get('configuracao/obrigatorios', [ObrigatoriosController::class, 'mostrar']);
Route::put('configuracao/obrigatorios', [ObrigatoriosController::class, 'definir']);
