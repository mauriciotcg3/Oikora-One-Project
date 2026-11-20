<?php

namespace App\Modules\Pessoas\Domain;

enum CampoFicha: string
{
    case Nome = 'nome';
    case Nascimento = 'nascimento';
    case Sexo = 'sexo';
    case Foto = 'foto_path';
    case EstadoCivil = 'estado_civil';
    case DataCasamento = 'data_casamento';
    case Cpf = 'cpf';
    case Rg = 'rg';
    case PaisDocumento = 'rg_pais';
    case Celular = 'celular';
    case Whatsapp = 'e_whatsapp';
    case Telefone = 'telefone';
    case Email = 'email';
    case Cep = 'cep';
    case Logradouro = 'logradouro';
    case Numero = 'numero';
    case Complemento = 'complemento';
    case Bairro = 'bairro';
    case Cidade = 'cidade';
    case Estado = 'estado';
    case Pais = 'pais';
    case NecessidadesEspeciais = 'necessidades_especiais';
    case TipoSanguineo = 'tipo_sanguineo';
    case AceitouJesus = 'aceitou_jesus';
    case ConversaoData = 'conversao_data';
    case ConversaoCanal = 'conversao_canal';
    case BatismoData = 'batismo_data';
    case BatizadoPor = 'batizado_por';
    case IgrejaOrigem = 'igreja_origem';
    case Lider = 'e_lider';
    case Pastor = 'e_pastor';
    case NovoConvertido = 'novo_convertido';
    case Escolaridade = 'escolaridade';
    case Profissao = 'profissao';
    case Nacionalidade = 'nacionalidade';
    case Naturalidade = 'naturalidade';

    public function categoria(): string
    {
        return match ($this) {
            self::Cpf, self::Rg, self::PaisDocumento => 'documentos',
            self::Celular, self::Whatsapp, self::Telefone, self::Email,
            self::Cep, self::Logradouro, self::Numero, self::Complemento,
            self::Bairro, self::Cidade, self::Estado, self::Pais => 'contatos',
            self::NecessidadesEspeciais, self::TipoSanguineo => 'saude',
            self::AceitouJesus, self::ConversaoData, self::ConversaoCanal,
            self::BatismoData, self::BatizadoPor, self::IgrejaOrigem,
            self::Lider, self::Pastor, self::NovoConvertido => 'fe',
            default => 'geral',
        };
    }

    /** Onde o campo é guardado: contatos e endereço ficam nas tabelas filhas, lidas pela Pessoa. */
    public function tabela(): string
    {
        return match ($this) {
            self::Celular, self::Whatsapp, self::Telefone, self::Email => 'pessoa_contatos',
            self::Cep, self::Logradouro, self::Numero, self::Complemento,
            self::Bairro, self::Cidade, self::Estado, self::Pais => 'pessoa_enderecos',
            default => 'pessoas',
        };
    }

    public function secao(): string
    {
        return match ($this) {
            self::Cpf, self::Rg, self::PaisDocumento => 'documentos',
            self::Celular, self::Whatsapp, self::Telefone, self::Email => 'contatos',
            self::Cep, self::Logradouro, self::Numero, self::Complemento,
            self::Bairro, self::Cidade, self::Estado, self::Pais => 'endereco',
            self::NecessidadesEspeciais, self::TipoSanguineo => 'saude',
            self::AceitouJesus, self::ConversaoData, self::ConversaoCanal,
            self::BatismoData, self::BatizadoPor, self::IgrejaOrigem,
            self::Lider, self::Pastor, self::NovoConvertido => 'eclesiasticos',
            self::Escolaridade, self::Profissao, self::Nacionalidade, self::Naturalidade => 'adicionais',
            default => 'pessoais',
        };
    }
}
