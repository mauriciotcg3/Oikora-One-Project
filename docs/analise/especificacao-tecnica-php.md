# Especificação técnica em PHP: nova plataforma da igreja

**Base:** `analise-e-plano-nova-plataforma.md` (requisitos) e `github/backlog.json` (44 tarefas em 7 fases).
**Objetivo:** dizer *como* construir em PHP o que o plano define como *o quê*, com foco no módulo **Pessoas** e em **acessos**.

> Os exemplos de código são pontos de partida para a equipe revisar e testar. Confirme as versões exatas das bibliotecas no momento do `composer require`.

---

## 1. Stack recomendada

| Camada | Escolha | Por quê |
|---|---|---|
| Linguagem | **PHP 8.3+** | Enums, readonly, tipagem forte, desempenho |
| Framework | **Laravel** (versão estável mais recente) | Ecossistema maduro, filas, notificações, auth, testes, grande oferta de desenvolvedores no Brasil |
| Painel administrativo | **Filament** (sobre Livewire) | Já entrega tabela com colunas configuráveis, filtros, ações em massa, formulários e paginação de 25/50/100, que são exatamente os problemas de UX do inChurch |
| Banco de dados | **PostgreSQL 16+** (ou MySQL 8) | JSONB para campos personalizados, índices parciais, busca sem acento (`unaccent`) |
| Cache e filas | **Redis** + **Laravel Horizon** | Importações, exportações, push e e-mails em segundo plano |
| API (app e integrações) | **Laravel Sanctum** + API Resources, rotas `/api/v1` | Token por dispositivo para o app; API versionada |
| Permissões | **spatie/laravel-permission** + tabela própria de escopo | Papéis e permissões prontos; escopo por igreja ou regional no domínio |
| Auditoria | **spatie/laravel-activitylog** | Quem alterou o quê, inclusive exportações |
| Planilhas | **openspout/openspout** (ou maatwebsite/excel) | Leitura de XLSX e CSV em streaming, sem estourar memória |
| Webhooks | **spatie/laravel-webhook-server** | Assinatura HMAC, novas tentativas, log |
| Push | **laravel-notification-channels/fcm** | Firebase para Android e iOS |
| 2FA | **Laravel Fortify** (ou o MFA do Filament) | TOTP para administradores |
| Qualidade | **Pest**, **Larastan** (PHPStan), **Laravel Pint** | Testes, análise estática, padrão de código |
| CI/CD | **GitHub Actions** | Já integrado ao repositório e ao quadro de tarefas |
| Hospedagem | Docker em VPS/nuvem, ou Laravel Forge/Ploi | Deploy simples, filas e agendador gerenciados |

**App dos membros:** o backend Laravel expõe a API. O app pode ser feito em Flutter ou React Native, ou começar como **PWA** (Blade/Livewire responsivo) para reduzir o custo inicial.

---

## 2. Arquitetura

- **Monólito modular:** um único projeto Laravel, organizado por domínio. É mais simples de operar que microsserviços e atende igrejas de qualquer porte.
- **Multi-igreja num banco só:** cada registro tem `igreja_id` (ou `organizacao_id`). O isolamento é feito por **global scope** e **policies**.
- **Painéis Filament separados:** `/admin` (secretaria, pastores, tesouraria) e, no futuro, `/lider` (líderes de célula, voltado para celular).

```
app/
  Domain/
    Organizacao/      # Organizacao, Regional, Igreja
    Pessoas/          # Pessoa, Contato, Endereco, Vinculo, Relacionamento, Funcao...
      Models/  Enums/  Actions/  Events/  Listeners/  Policies/  Rules/  Imports/
    Acessos/          # Conta (User), Papel, AtribuicaoPapel
    Celulas/  Programacao/  Cuidado/  Kids/  Financeiro/  Conteudo/
    Migracao/         # Integração com a API do inChurch
  Filament/Admin/Resources/   # telas do painel
  Http/Controllers/Api/V1/    # API do app e de integrações
  Support/                    # helpers: normalização de CPF, telefone, texto
```

**Padrões adotados**
- **Actions** (classes de caso de uso, por exemplo `CadastrarPessoaRapido`) concentram a regra de negócio. Painel, API, formulário público e importação chamam a **mesma Action**, e é isso que garante as mesmas regras em todos os canais.
- **Enums** do PHP para vínculos, status e motivos.
- **Events e Listeners** para a linha do tempo, auditoria, webhooks e notificações.
- **Jobs** para tudo que demora (importação, exportação, push em massa, migração).

---

## 3. Modelo de dados (migrations)

### 3.1 Organização
```php
Schema::create('organizacoes', function (Blueprint $t) {
    $t->id();
    $t->string('nome');
    $t->string('cnpj', 14)->nullable();
    $t->timestamps();
});

Schema::create('regionais', function (Blueprint $t) {
    $t->id();
    $t->foreignId('organizacao_id')->constrained('organizacoes');
    $t->string('nome');
    $t->timestamps();
});

Schema::create('igrejas', function (Blueprint $t) {
    $t->id();
    $t->foreignId('organizacao_id')->constrained('organizacoes');
    $t->foreignId('regional_id')->nullable()->constrained('regionais');
    $t->string('nome');
    $t->string('cnpj', 14)->nullable();
    $t->timestamps();
});
```

### 3.2 Pessoas: entidade única
```php
Schema::create('pessoas', function (Blueprint $t) {
    $t->id();
    $t->uuid('uuid')->unique();                       // identificador público (API, links)
    $t->foreignId('organizacao_id')->constrained('organizacoes');
    $t->foreignId('igreja_id')->constrained('igrejas');
    $t->string('nome', 150);
    $t->string('nome_busca', 150)->index();           // minúsculo, sem acento
    $t->string('apelido', 60)->nullable();
    $t->date('nascimento')->nullable();
    $t->string('sexo', 1)->nullable();                // F | M
    $t->string('estado_civil', 20)->nullable();
    $t->text('cpf')->nullable();                      // criptografado (cast 'encrypted')
    $t->string('cpf_hash', 64)->nullable()->index();  // HMAC, permite buscar duplicado sem expor o CPF
    $t->text('rg')->nullable();                       // criptografado
    $t->string('foto_path')->nullable();
    $t->jsonb('campos_extras')->default('{}');        // valores dos campos personalizados
    $t->unsignedTinyInteger('completude')->default(0);// % do perfil preenchido
    $t->foreignId('criado_por')->nullable()->constrained('users');
    $t->timestamps();
    $t->softDeletes();                                // lixeira de 30 dias
});

Schema::create('contatos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
    $t->string('tipo', 10);                           // celular | telefone | email
    $t->string('valor');                              // como foi digitado
    $t->string('valor_normalizado')->index();         // +5511999999999 | email@minusculo
    $t->boolean('whatsapp')->default(false);
    $t->boolean('principal')->default(false);
    $t->timestamp('verificado_em')->nullable();
    $t->timestamps();
});

Schema::create('enderecos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
    $t->string('cep', 8)->nullable();
    $t->string('logradouro')->nullable();
    $t->string('numero', 20)->nullable();
    $t->string('complemento', 100)->nullable();
    $t->string('bairro', 100)->nullable();
    $t->string('cidade', 100)->nullable();
    $t->unsignedInteger('cidade_ibge')->nullable();   // código IBGE, evita o bug da cidade "sumir"
    $t->string('uf', 2)->nullable();
    $t->string('pais', 2)->default('BR');
    $t->timestamps();
});

Schema::create('vinculos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
    $t->foreignId('igreja_id')->constrained('igrejas');
    $t->string('tipo', 20);                           // visitante | frequentador | membro
    $t->string('status', 10)->default('ativo');       // ativo | inativo
    $t->foreignId('tipo_membro_id')->nullable();      // Batizado, Professo...
    $t->string('matricula', 10)->nullable();
    $t->date('data_inicio')->nullable();
    $t->foreignId('motivo_entrada_id')->nullable();
    $t->date('data_fim')->nullable();
    $t->foreignId('motivo_saida_id')->nullable();
    $t->text('observacao')->nullable();
    $t->boolean('atual')->default(true);              // o histórico fica nas linhas antigas
    $t->timestamps();
    $t->unique(['igreja_id', 'matricula']);           // ou organizacao_id, conforme a configuração
    $t->index(['pessoa_id', 'atual']);
});

Schema::create('relacionamentos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
    $t->foreignId('relacionada_id')->nullable()->constrained('pessoas')->nullOnDelete();
    $t->string('nome_livre')->nullable();             // familiar que não tem cadastro
    $t->string('tipo', 20);                           // conjuge | pai | mae | filho | responsavel...
    $t->timestamps();
});

Schema::create('eventos_linha_do_tempo', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
    $t->string('modulo', 30);                         // membresia | evento | celula | cuidado
    $t->string('atividade', 60);                      // entrada | mudanca_vinculo | inativacao...
    $t->string('motivo')->nullable();
    $t->jsonb('antes')->nullable();
    $t->jsonb('depois')->nullable();
    $t->date('ocorrido_em');                          // pode ser retroativo
    $t->foreignId('autor_id')->nullable()->constrained('users');
    $t->timestamps();
});

Schema::create('alteracoes_pendentes', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
    $t->string('campo');
    $t->jsonb('valor_atual')->nullable();
    $t->jsonb('valor_proposto');
    $t->string('origem', 20);                         // app | formulario
    $t->string('status', 20)->default('pendente');    // pendente | aprovado | recusado
    $t->text('justificativa')->nullable();            // vai para o membro quando recusado
    $t->foreignId('revisado_por')->nullable()->constrained('users');
    $t->timestamps();
});

Schema::create('consentimentos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
    $t->string('finalidade');                         // cadastro, comunicacao, uso_imagem...
    $t->string('versao_termo', 20);
    $t->string('origem', 20);                         // formulario | app | painel
    $t->ipAddress('ip')->nullable();
    $t->timestamp('aceito_em');
    $t->timestamp('revogado_em')->nullable();
});
```

**Campos personalizados:** a definição fica na tabela `campos_personalizados` (`chave`, `rotulo`, `tipo`, `opcoes` jsonb, `obrigatorio_para` jsonb com a lista de vínculos, `visivel_ao_membro`, `sensivel`, `ativo`). Os valores ficam em `pessoas.campos_extras` (jsonb), com índice GIN quando houver filtro por eles.

### 3.3 Conta de acesso ligada por ID (não por e-mail)
```php
Schema::table('users', function (Blueprint $t) {
    $t->foreignId('pessoa_id')->nullable()->unique()->constrained('pessoas')->nullOnDelete();
    $t->timestamp('convite_expira_em')->nullable();
    $t->timestamp('ultimo_acesso_em')->nullable();
});

Schema::create('atribuicoes_papel', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('role_id')->constrained('roles');   // tabela do spatie/laravel-permission
    $t->string('escopo_tipo', 20);                    // organizacao | regional | igreja | grupo | celula
    $t->unsignedBigInteger('escopo_id');
    $t->timestamps();
    $t->unique(['user_id', 'role_id', 'escopo_tipo', 'escopo_id']);
});
```
Isso resolve o problema central do inChurch: a conta aponta para a pessoa por `pessoa_id`. Trocar o e-mail não desfaz o vínculo, e "desvincular" nunca apaga a ficha.

---

## 4. Domínio Pessoas: código de referência

### 4.1 Enums
```php
namespace App\Domain\Pessoas\Enums;

enum TipoVinculo: string
{
    case Visitante    = 'visitante';
    case Frequentador = 'frequentador';
    case Membro       = 'membro';

    public function label(): string
    {
        return match ($this) {
            self::Visitante    => 'Visitante',
            self::Frequentador => 'Frequentador',
            self::Membro       => 'Membro',
        };
    }
}
```

### 4.2 Model Pessoa
```php
namespace App\Domain\Pessoas\Models;

use App\Domain\Pessoas\Observers\PessoaObserver;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PessoaObserver::class)]
class Pessoa extends Model
{
    use SoftDeletes, Prunable;

    protected $fillable = ['igreja_id', 'organizacao_id', 'nome', 'apelido', 'nascimento',
        'sexo', 'estado_civil', 'cpf', 'rg', 'campos_extras'];

    protected function casts(): array
    {
        return [
            'nascimento'    => 'date',
            'cpf'           => 'encrypted',   // LGPD: criptografado no banco
            'rg'            => 'encrypted',
            'campos_extras' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Domain\Organizacao\Scopes\EscopoDoUsuario);

        static::saving(function (Pessoa $p) {
            $p->nome_busca = Texto::paraBusca($p->nome);
            $p->cpf_hash   = $p->cpf ? hash_hmac('sha256', preg_replace('/\D/', '', $p->cpf), config('app.key')) : null;
        });
    }

    /** Lixeira: apaga de vez 30 dias depois da exclusão. */
    public function prunable()
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
    }

    public function contatos()        { return $this->hasMany(Contato::class); }
    public function endereco()        { return $this->hasOne(Endereco::class); }
    public function vinculos()        { return $this->hasMany(Vinculo::class); }
    public function vinculoAtual()    { return $this->hasOne(Vinculo::class)->where('atual', true); }
    public function relacionamentos() { return $this->hasMany(Relacionamento::class); }
    public function linhaDoTempo()    { return $this->hasMany(EventoLinhaDoTempo::class)->latest('ocorrido_em'); }
    public function conta()           { return $this->hasOne(\App\Models\User::class); }
}
```

### 4.3 Validação de CPF (Rule)
```php
namespace App\Domain\Pessoas\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D/', '', (string) $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('CPF inválido.');
            return;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                $fail('CPF inválido.');
                return;
            }
        }
    }
}
```

### 4.4 Busca de duplicados (usada no cadastro rápido, na importação e na API)
```php
namespace App\Domain\Pessoas\Actions;

use App\Domain\Pessoas\Models\Pessoa;
use App\Support\Telefone;
use App\Support\Texto;
use Illuminate\Support\Collection;

class BuscarPossiveisDuplicados
{
    /**
     * Regras padrão (recomendação da Central de Ajuda do inChurch):
     * (nome E nascimento) OU CPF OU e-mail, mais celular.
     */
    public function __invoke(array $dados): Collection
    {
        $email   = isset($dados['email'])   ? mb_strtolower(trim($dados['email'])) : null;
        $celular = isset($dados['celular']) ? Telefone::paraE164($dados['celular']) : null;
        $cpf     = isset($dados['cpf'])     ? preg_replace('/\D/', '', $dados['cpf']) : null;

        return Pessoa::query()
            ->with('vinculoAtual')
            ->where(function ($q) use ($dados, $email, $celular, $cpf) {
                if (!empty($dados['nome']) && !empty($dados['nascimento'])) {
                    $q->orWhere(fn ($q) => $q
                        ->where('nome_busca', Texto::paraBusca($dados['nome']))
                        ->whereDate('nascimento', $dados['nascimento']));
                }
                if ($cpf) {
                    $q->orWhere('cpf_hash', hash_hmac('sha256', $cpf, config('app.key')));
                }
                foreach (array_filter([$email, $celular]) as $valor) {
                    $q->orWhereHas('contatos', fn ($c) => $c->where('valor_normalizado', $valor));
                }
            })
            ->limit(5)
            ->get();
    }
}
```
No painel, o campo dispara essa busca enquanto o usuário digita (Livewire `->live(debounce: 500)`) e mostra o aviso "Já existe … → Abrir ficha / É outra pessoa".

### 4.5 Cadastro rápido: uma Action para todos os canais
```php
namespace App\Domain\Pessoas\Actions;

use App\Domain\Pessoas\Enums\TipoVinculo;
use App\Domain\Pessoas\Events\PessoaCadastrada;
use App\Domain\Pessoas\Models\Pessoa;
use App\Support\Telefone;
use Illuminate\Support\Facades\DB;

class CadastrarPessoaRapido
{
    public function __invoke(array $dados, string $origem): Pessoa
    {
        return DB::transaction(function () use ($dados, $origem) {
            $pessoa = Pessoa::create([
                'organizacao_id' => $dados['organizacao_id'],
                'igreja_id'      => $dados['igreja_id'],
                'nome'           => $dados['nome'],
                'nascimento'     => $dados['nascimento'] ?? null,
            ]);

            if (!empty($dados['celular'])) {
                $pessoa->contatos()->create([
                    'tipo' => 'celular', 'valor' => $dados['celular'],
                    'valor_normalizado' => Telefone::paraE164($dados['celular']),
                    'whatsapp' => $dados['whatsapp'] ?? false, 'principal' => true,
                ]);
            }
            if (!empty($dados['email'])) {
                $pessoa->contatos()->create([
                    'tipo' => 'email', 'valor' => $dados['email'],
                    'valor_normalizado' => mb_strtolower(trim($dados['email'])),
                    'principal' => empty($dados['celular']),
                ]);
            }

            $pessoa->vinculos()->create([
                'igreja_id'   => $dados['igreja_id'],
                'tipo'        => TipoVinculo::from($dados['vinculo'])->value,
                'data_inicio' => now()->toDateString(),
                'atual'       => true,
            ]);

            PessoaCadastrada::dispatch($pessoa, $origem);   // linha do tempo, webhook, e-mail de boas-vindas
            return $pessoa;
        });
    }
}
```
O **FormRequest** (painel e API) define as regras: `nome` obrigatório; `celular` obrigatório se não houver `email`, e vice-versa. As regras extras **por vínculo** vêm da configuração de campos obrigatórios, a mesma em todos os canais.

### 4.6 Mudança de vínculo com motivo e linha do tempo
```php
class AlterarVinculo
{
    public function __invoke(Pessoa $pessoa, TipoVinculo $novo, string $status, int $motivoId, string $data): void
    {
        DB::transaction(function () use ($pessoa, $novo, $status, $motivoId, $data) {
            $anterior = $pessoa->vinculoAtual;
            $anterior?->update(['atual' => false, 'data_fim' => $data, 'motivo_saida_id' => $motivoId]);

            $pessoa->vinculos()->create([
                'igreja_id' => $anterior?->igreja_id ?? $pessoa->igreja_id,
                'tipo' => $novo->value, 'status' => $status,
                'data_inicio' => $data, 'motivo_entrada_id' => $motivoId, 'atual' => true,
                'matricula' => $novo === TipoVinculo::Membro ? ($anterior?->matricula ?? app(GerarMatricula::class)($pessoa)) : null,
            ]);

            $pessoa->linhaDoTempo()->create([
                'modulo' => 'membresia', 'atividade' => 'mudanca_vinculo',
                'antes'  => ['tipo' => $anterior?->tipo, 'status' => $anterior?->status],
                'depois' => ['tipo' => $novo->value, 'status' => $status],
                'motivo' => \App\Domain\Pessoas\Models\Motivo::find($motivoId)?->nome,
                'ocorrido_em' => $data, 'autor_id' => auth()->id(),
            ]);
        });
    }
}
```

### 4.7 Completude do perfil
Um Listener recalcula `pessoas.completude` a cada alteração, comparando os campos preenchidos com os **campos esperados para o vínculo atual**. Esse percentual aparece na ficha, no app do membro e vira filtro na lista ("perfis abaixo de 50%").

---

## 5. Acessos: papéis, escopo e dados sensíveis

### 5.1 Nomes de permissão
Padrão `recurso.acao`. Por exemplo: `pessoas.ver`, `pessoas.criar`, `pessoas.editar`, `pessoas.excluir`, `pessoas.exportar`, `pessoas.sensiveis.ver`, `financeiro.contas.ver`, `cuidado.atendimentos.ver_proprios`.

### 5.2 Seeder dos papéis padrão
```php
$papeis = [
    'Administrador geral' => ['*'],
    'Secretaria'  => ['pessoas.ver', 'pessoas.criar', 'pessoas.editar', 'pessoas.exportar',
                      'pessoas.importar', 'pessoas.aprovar_alteracoes', 'datas.ver'],
    'Pastor'      => ['pessoas.ver', 'pessoas.sensiveis.ver', 'cuidado.*', 'celulas.ver'],
    'Tesouraria'  => ['financeiro.*'],
    'Líder de célula' => ['celulas.propria.ver', 'celulas.propria.relatorio', 'pessoas.ver_da_celula'],
    'Intercessão' => ['cuidado.oracao.ver', 'cuidado.oracao.responder'],
    'Kids'        => ['kids.*', 'pessoas.sensiveis.saude.ver'],
    'Comunicação' => ['conteudo.*', 'push.enviar'],
];
```

### 5.3 Escopo
O usuário recebe um **papel** num **escopo** (organização, regional, igreja, grupo ou célula), registrado na tabela `atribuicoes_papel`.
- O **global scope** `EscopoDoUsuario` filtra as consultas pelas igrejas que o usuário enxerga.
- As **Policies** verificam permissão e escopo antes de cada ação. Exemplo:
```php
class PessoaPolicy
{
    public function update(User $user, Pessoa $pessoa): bool
    {
        return $user->can('pessoas.editar') && $user->alcancaIgreja($pessoa->igreja_id);
    }
}
```
- **Restrição dentro da área**, que o inChurch não tem: por exemplo, `financeiro.contas.ver` com escopo `conta` e `escopo_id` igual à conta específica.

### 5.4 Convite em vez de senha definida pelo administrador
1. Na ficha da pessoa, a ação "Dar acesso ao painel" cria um `User` com `pessoa_id` e sem senha, mais a `AtribuicaoPapel`.
2. O sistema envia um e-mail com link assinado (`URL::temporarySignedRoute`, válido por 72h).
3. A pessoa define a própria senha. Se o papel for administrativo, o 2FA é obrigatório.
4. Revogar o acesso remove as atribuições e invalida os tokens do Sanctum na hora.

### 5.5 Dados sensíveis (LGPD)
- CPF e RG com cast `encrypted`; tipo sanguíneo, saúde e observações pastorais em colunas criptografadas.
- Campos e telas sensíveis exigem a permissão `*.sensiveis.ver`.
- **Atendimento pastoral:** a policy só permite ler as observações ao autor (ou a um papel explicitamente autorizado).
- Exportações registradas no `activitylog`, com a contagem de registros e o filtro usado.

---

## 6. Painel administrativo (Filament)

Exemplo de lista de pessoas que já resolve vários problemas de UX do inChurch (paginação de 6 em 6, lixeira solta na lista, ausência de ações em massa):

```php
// Exemplo em Filament 3. Na versão 4, alguns métodos de ações mudam de nome; confira a documentação.
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\ImageColumn::make('foto_path')->circular()->label(''),
            Tables\Columns\TextColumn::make('nome')->searchable(query: fn ($q, $s) =>
                $q->where('nome_busca', 'like', '%' . \App\Support\Texto::paraBusca($s) . '%'))->sortable(),
            Tables\Columns\TextColumn::make('vinculoAtual.tipo')->badge()->label('Vínculo'),
            Tables\Columns\TextColumn::make('vinculoAtual.matricula')->label('Matrícula')->toggleable(),
            Tables\Columns\TextColumn::make('celular')->toggleable(),
            Tables\Columns\TextColumn::make('nascimento')->date('d/m')->label('Aniversário')->toggleable(),
            Tables\Columns\TextColumn::make('completude')->suffix('%')->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('vinculo')->relationship('vinculoAtual', 'tipo'),
            Tables\Filters\TernaryFilter::make('sem_celula')->label('Sem célula')
                ->queries(true: fn ($q) => $q->doesntHave('participacoesCelula'), false: fn ($q) => $q->has('participacoesCelula')),
            Tables\Filters\Filter::make('aniversariantes_mes')
                ->query(fn ($q) => $q->whereMonth('nascimento', now()->month)),
            Tables\Filters\TrashedFilter::make(),   // lixeira
        ])
        ->paginated([25, 50, 100])
        ->persistFiltersInSession()
        ->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()])
        ->bulkActions([
            Tables\Actions\BulkAction::make('adicionarCelula')->label('Adicionar a célula')->form([/* ... */]),
            Tables\Actions\ExportBulkAction::make(),     // registrado na auditoria
            Tables\Actions\DeleteBulkAction::make(),     // vai para a lixeira (soft delete)
        ]);
}
```

Telas do Filament previstas para Pessoas:
- **Lista** (acima), com filtros salvos.
- **Cadastro rápido** (modal) com aviso de duplicado em tempo real.
- **Ficha** (Infolist): seções editáveis no lugar, barra de completude, linha do tempo e ações "Mudar vínculo", "Inativar" e "Dar acesso".
- **Pipeline** em kanban (Visitante → Frequentador → Integração → Membro).
- **Alterações pendentes**, com aprovação ou recusa campo a campo.
- **Importação** em assistente (upload → mapeamento → pré-visualização → confirmar).
- **Configurações:** campos obrigatórios por vínculo, campos personalizados, motivos, tipos de membro, regras de duplicidade, matrícula.

---

## 7. Importação segura (sem as armadilhas do inChurch)

```
upload ─▶ Job LerPlanilha ─▶ importacao_linhas (status: novo | atualizar | conflito | erro)
                                       │
                         tela de pré-visualização (filtros por status)
                                       │  usuário confirma
                                       ▼
                       Job AplicarImportacao (em lotes, com transação)
                       guarda snapshot "antes" de cada registro alterado
                                       │
                                       ▼
                       relatório na tela + botão "Desfazer importação"
```
- A leitura é feita em streaming (OpenSpout), o que aguenta planilhas grandes.
- O **mapeamento de colunas** ignora maiúsculas e acentos ("Sim", "S" e "sim" valem o mesmo).
- A deduplicação usa a mesma `BuscarPossiveisDuplicados`. Os conflitos ficam para decisão humana; nada é sobrescrito sem confirmação.
- **Desfazer:** apaga os registros criados e restaura os snapshots dos registros atualizados.

---

## 8. API e integrações

- **Rotas** `/api/v1/...` com **Sanctum**: tokens com *abilities* iguais aos nomes das permissões.
- **Recursos (API Resources):** `PessoaResource` nunca devolve CPF ou campos sensíveis sem a ability correspondente.
- **Endpoints do app do membro:** perfil, `PATCH /me` (cria `alteracoes_pendentes`), "Esse perfil é seu?", células, eventos, doações, pedidos de oração.
- **Webhooks** (spatie/laravel-webhook-server): `pessoa.criada`, `pessoa.atualizada`, `vinculo.alterado`, `inscricao.paga`… com assinatura HMAC e novas tentativas.
- **Limite de requisições** (`RateLimiter`) por token e por IP. **Documentação OpenAPI** gerada automaticamente (por exemplo com Scramble ou Scribe).

---

## 9. Migração a partir do inChurch

Comando Artisan idempotente (pode rodar várias vezes sem duplicar):

```php
// php artisan inchurch:importar pessoas --desde=2020-01-01
class ImportarDoInchurch extends Command
{
    protected $signature = 'inchurch:importar {entidade} {--desde=}';

    public function handle(): int
    {
        $cliente = Http::baseUrl(config('services.inchurch.url'))
            ->withHeaders([
                // conferir em docs.inchurch.com.br o nome exato dos cabeçalhos de API Key e Secret
                'X-Api-Key'    => config('services.inchurch.key'),
                'X-Api-Secret' => config('services.inchurch.secret'),
            ])
            ->retry(3, 2000)
            ->timeout(60);

        $pagina = 1;
        do {
            $resp = $cliente->get('/' . $this->argument('entidade'), ['page' => $pagina])->throw()->json();
            foreach ($resp['data'] ?? [] as $registro) {
                // 1) grava em staging (migracao_inchurch_registros) com o JSON original
                // 2) usa updateOrCreate pelo id do inChurch, o que torna o comando idempotente
            }
            $pagina++;
        } while (!empty($resp['data']));

        return self::SUCCESS;
    }
}
```

Etapas:
1. Criar no inChurch um **cliente de API só de leitura** (Mais › API › Clientes da API).
2. Extrair membros, visitantes, líderes, grupos, eventos, inscrições, financeiro e células para tabelas de **staging**.
3. Rodar o **job de unificação**, que cruza por e-mail, CPF, celular e nome + nascimento e gera um relatório de conflitos.
4. A secretaria revisa os conflitos na tela de conflitos do Filament.
5. **Carga final** pelas mesmas Actions do sistema (as regras valem para os dados migrados também).
6. **Conferir as contagens** (`php artisan migracao:conferir`) com uma definição única por indicador.

A **matrícula**, as datas (entrada, batismo, conversão), as funções com datas e a linha do tempo são preservadas. Atendimentos pastorais migram com o autor original e a mesma regra de visibilidade.

---

## 10. Qualidade, segurança e operação

| Tema | Prática |
|---|---|
| Testes | Pest: testes de Actions (cadastro, duplicados, mudança de vínculo, importação), de Policies (escopo e sensíveis) e da API |
| Análise estática | Larastan, nível 6 ou maior, no CI |
| Padrão de código | Laravel Pint, no CI |
| CI | GitHub Actions: `composer install` → Pint → Larastan → Pest (com PostgreSQL de serviço) |
| Segurança | HTTPS, cabeçalhos de segurança, CSRF (padrão do Laravel), 2FA para administradores, rate limit, `encrypted` casts, backups criptografados |
| LGPD | Consentimentos versionados, fluxo de exclusão, auditoria de exportações, retenção configurável |
| Filas | Horizon com filas separadas: `importacao`, `notificacoes`, `webhooks`, `default` |
| Agendador | `schedule:run`: limpar lixeira (`model:prune`), lembretes de relatório de célula, e-mail semanal de novos convertidos, aniversários |
| Observabilidade | Logs estruturados, Laravel Pulse e alerta de erros (por exemplo Sentry) |
| Backups | Diário, com teste de restauração mensal |

**Exemplo de CI (`.github/workflows/ci.yml`):**
```yaml
name: CI
on: [push, pull_request]
jobs:
  testes:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env: { POSTGRES_PASSWORD: postgres, POSTGRES_DB: testes }
        ports: ['5432:5432']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: 'pdo_pgsql, intl, zip', coverage: none }
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: vendor/bin/pint --test
      - run: vendor/bin/phpstan analyse --memory-limit=1G
      - run: vendor/bin/pest
        env: { DB_CONNECTION: pgsql, DB_HOST: 127.0.0.1, DB_DATABASE: testes, DB_USERNAME: postgres, DB_PASSWORD: postgres }
```

---

## 11. Como as fases do backlog viram trabalho em PHP

| Fase (milestone no GitHub) | Entregas técnicas principais |
|---|---|
| **0 – Descoberta** | Decisão da stack (ADR), cliente de API do inChurch e amostra extraída, matriz de permissões em planilha → seeder |
| **1 – Fundação** | Projeto Laravel + Filament, Docker, CI, migrations de Organização, `users` com `pessoa_id`, spatie/permission + `atribuicoes_papel`, convite + 2FA, activitylog, design system no tema do Filament |
| **2 – Pessoas (MVP)** | Migrations de Pessoas, Actions (cadastro rápido, duplicados, vínculo, completude), Rules (CPF, telefone, CEP), Resources do Filament (lista, ficha, pipeline, pendentes, importação), formulário público (Livewire), `/api/v1` do membro, comando de migração de pessoas |
| **3 – Células e programação** | Domínios Celulas e Programacao; tela mobile do líder (painel `/lider`); eventos recorrentes; tickets e pagamentos (integração com o gateway escolhido); check-in por QR |
| **4 – Cuidado e Kids** | Caixa de cuidado, atendimento pastoral com policy restrita, trilhas; Kids com crianças como `pessoas` + `relacionamentos`, etiqueta (PDF/ZPL) e check-in por QR |
| **5 – Financeiro** | Contas, categorias e DRE, centros de custo, lançamentos, importação CSV, conciliação OFX, relatórios |
| **6 – Conteúdo, app e integrações** | Mídia, push via FCM, webhooks, documentação OpenAPI, cutover |

---

## 12. Decisões que a equipe precisa confirmar
1. **PostgreSQL ou MySQL.** Recomendo PostgreSQL pelo JSONB e pela busca sem acento.
2. **Filament** para o painel ou front separado (Inertia + Vue/React). O Filament acelera muito um sistema administrativo como este.
3. **App nativo** (Flutter ou React Native) **ou PWA** no início.
4. **Gateway de pagamento** para doações e eventos (Asaas, Pagar.me, Mercado Pago, Stripe…).
5. **Hospedagem** (VPS com Docker, Forge/Ploi ou nuvem gerenciada) e onde os dados ficam armazenados (LGPD: preferir datacenter no Brasil).
