{{ $organizacao }}

{{ $titulo }}

{{ $texto }}
@if ($link)

{{ $acao }}: {{ $link }}
@endif

Mensagem enviada pelo Oikora em nome de {{ $organizacao }}. Para falar com a organização, responda a este e-mail.
@if ($descadastro)
Não quero mais receber estes e-mails: {{ $descadastro }}
@endif
