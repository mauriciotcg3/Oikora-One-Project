<?php

namespace Tests\Fixtures\Contrato;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fixture da T-05: campo de projeção (sensível) que pode não vir. O contrato precisa marcá-lo como
 * opcional, para o tipo do front não prometer o que a API omite (OIK-ADR-0006 §8; plan.md §13).
 *
 * @property array{uuid: string, nome: string, cpf: string} $resource
 */
final class FichaProjetadaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource['uuid'],
            'nome' => $this->resource['nome'],
            'cpf' => $this->when($request->boolean('documentos'), fn (): string => $this->resource['cpf']),
        ];
    }
}
