<?php

use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Presentation\CadastroRapidoController;
use App\Modules\Pessoas\Presentation\Configuracao\ListasController;
use App\Modules\Pessoas\Presentation\Configuracao\ObrigatoriosController;
use App\Modules\Pessoas\Presentation\PessoasController;
use Illuminate\Support\Facades\Route;

Route::get('pessoas/duplicidade', [CadastroRapidoController::class, 'duplicidade']);
Route::post('pessoas', [CadastroRapidoController::class, 'cadastrar']);
Route::get('pessoas/{pessoa}', [PessoasController::class, 'mostrar'])->whereUuid('pessoa');
Route::get('busca', [PessoasController::class, 'buscar']);

$listas = array_map(static fn (ListaValores $lista): string => $lista->value, ListaValores::cases());
Route::get('listas/{lista}', [ListasController::class, 'listar'])->whereIn('lista', $listas);
Route::post('listas/{lista}', [ListasController::class, 'criar'])->whereIn('lista', $listas);
Route::put('listas/{lista}/{item}', [ListasController::class, 'editar'])->whereIn('lista', $listas)->whereUuid('item');

Route::get('configuracao/obrigatorios', [ObrigatoriosController::class, 'mostrar']);
Route::put('configuracao/obrigatorios', [ObrigatoriosController::class, 'definir']);
