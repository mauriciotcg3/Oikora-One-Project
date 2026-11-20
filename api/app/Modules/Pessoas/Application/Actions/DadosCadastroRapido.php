<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Pessoas\Domain\Vinculo;

/** Campos do cadastro rápido (CA-13): nome, celular ou e-mail, vínculo, igreja e "como chegou". */
final readonly class DadosCadastroRapido
{
    public function __construct(
        public string $nome,
        public int $igrejaId,
        public Vinculo $vinculo,
        public ?string $celular,
        public ?string $email,
        public ?int $comoChegouId,
        public ?string $nascimento = null,
        public ?string $cpf = null,
    ) {}

    /** @return list<string> nomes dos campos preenchidos, para a auditoria (sem valores) */
    public function camposPreenchidos(): array
    {
        return array_keys(array_filter([
            'nome' => true,
            'igreja_id' => true,
            'vinculo' => true,
            'celular' => $this->celular !== null,
            'email' => $this->email !== null,
            'como_chegou_id' => $this->comoChegouId !== null,
            'nascimento' => $this->nascimento !== null,
            'cpf' => $this->cpf !== null,
        ]));
    }
}
