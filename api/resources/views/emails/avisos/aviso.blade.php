{{-- Aviso por e-mail (OIK-ADR-0008 §9). Só nome da organização e link: nada da ficha entra aqui. --}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f4;font-family:Arial,Helvetica,sans-serif;color:#1c1917;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
        <tr>
            <td style="padding:24px 24px 8px;font-size:14px;color:#57534e;">{{ $organizacao }}</td>
        </tr>
        <tr>
            <td style="padding:0 24px;">
                <h1 style="font-size:20px;margin:8px 0 16px;">{{ $titulo }}</h1>
                <p style="font-size:16px;line-height:1.5;margin:0 0 24px;">{{ $texto }}</p>
                @if ($link)
                    <p style="margin:0 0 24px;">
                        <a href="{{ $link }}" style="display:inline-block;padding:12px 20px;background:#1c1917;color:#ffffff;text-decoration:none;border-radius:6px;">{{ $acao }}</a>
                    </p>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px 24px;font-size:12px;color:#78716c;">
                Mensagem enviada pelo Oikora em nome de {{ $organizacao }}. Para falar com a organização, responda a este e-mail.
                @if ($descadastro)
                    <br><a href="{{ $descadastro }}" style="color:#78716c;">Não quero mais receber estes e-mails</a>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
