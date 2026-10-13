@php
    $isEnglish = ($contact->locale ?? 'es') === 'en';
    $isAdmin = $kind === 'admin';
    $theme = $contact->theme;
    $isDark = $theme === 'dark';
    $scheme = $theme ?? 'light dark';
    $background = $isDark ? '#141414' : '#f8fafc';
    $card = $isDark ? '#080d15' : '#ffffff';
    $panel = $isDark ? '#101829' : '#f1f5f9';
    $border = $isDark ? '#233044' : '#e2e8f0';
    $text = $isDark ? '#e2e8f0' : '#0f172a';
    $bodyText = $isDark ? '#d1d5db' : '#475569';
    $muted = $isDark ? '#a6adb9' : '#64748b';
    $badgeText = $isAdmin ? '#ffc107' : ($isDark ? '#60a5fa' : '#2563eb');
    $badgeBackground = $isAdmin ? ($isDark ? '#292310' : '#fffbeb') : ($isDark ? '#101e35' : '#eff6ff');
    $badgeBorder = $isAdmin ? ($isDark ? '#755913' : '#fcd34d') : ($isDark ? '#254b78' : '#bfdbfe');
    $subject = $contact->subject ?: ($isEnglish ? 'General Inquiry' : 'Consulta General');
    $title = $isAdmin
        ? ($isEnglish ? 'New message received' : 'Nuevo mensaje recibido')
        : ($isEnglish ? 'Thank you for your message!' : '¡Gracias por tu mensaje!');
    $rows = $isAdmin ? [
        [$isEnglish ? 'Sender' : 'Remitente', $contact->name],
        ['Email', $contact->email],
        [$isEnglish ? 'Category' : 'Categoría', $subject],
        [$isEnglish ? 'Origin IP' : 'IP Origen', $contact->ip_address ?? '—'],
    ] : [
        [$isEnglish ? 'Name' : 'Nombre', $contact->name],
        [$isEnglish ? 'Email' : 'Correo', $contact->email],
        [$isEnglish ? 'Subject' : 'Asunto', $subject],
        [$isEnglish ? 'Date' : 'Fecha', $contact->created_at?->copy()->utc()->format('d/m/Y H:i').' UTC'],
    ];
    $buttonUrl = $isAdmin
        ? 'mailto:'.$contact->email.'?subject='.rawurlencode('Re: '.$subject)
        : 'https://felixsaucedo.com';
@endphp
<!DOCTYPE html>
<html lang="{{ $isEnglish ? 'en' : 'es' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="{{ $scheme }}">
  <meta name="supported-color-schemes" content="{{ $scheme }}">
  <title>{{ $title }} — Félix Saucedo</title>
  <style>
    :root { color-scheme: {{ $scheme }}; supported-color-schemes: {{ $scheme }}; }
    body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table { border-collapse: separate; }
    .detail-value { overflow-wrap: anywhere; word-break: break-word; }
    @media only screen and (max-width: 480px) {
      .outer-padding { padding: 12px 10px !important; }
      .card-padding { padding: 24px 18px !important; }
      .detail-padding { padding: 16px 12px !important; }
      .detail-label, .detail-value { font-size: 12px !important; }
      .detail-label { width: 30% !important; }
    }
    @if($theme === null)
    @media (prefers-color-scheme: dark) {
      .bg-main { background-color: #141414 !important; }
      .card-box { background-color: #080d15 !important; border-color: #233044 !important; }
      .detail-box { background-color: #101829 !important; border-color: #233044 !important; }
      .text-title, .detail-value { color: #e2e8f0 !important; }
      .text-body { color: #d1d5db !important; }
      .text-muted, .detail-label { color: #a6adb9 !important; }
      .divider { border-color: #233044 !important; }
      .badge-user { background-color: #101e35 !important; border-color: #254b78 !important; color: #60a5fa !important; }
      .badge-admin { background-color: #292310 !important; border-color: #755913 !important; color: #ffc107 !important; }
    }
    [data-ogsc] .bg-main { background-color: #141414 !important; }
    [data-ogsc] .card-box { background-color: #080d15 !important; border-color: #233044 !important; }
    [data-ogsc] .detail-box { background-color: #101829 !important; border-color: #233044 !important; }
    [data-ogsc] .text-title, [data-ogsc] .detail-value { color: #e2e8f0 !important; }
    [data-ogsc] .text-body { color: #d1d5db !important; }
    [data-ogsc] .text-muted, [data-ogsc] .detail-label { color: #a6adb9 !important; }
    [data-ogsc] .divider { border-color: #233044 !important; }
    [data-ogsc] .badge-user { background-color: #101e35 !important; border-color: #254b78 !important; color: #60a5fa !important; }
    [data-ogsc] .badge-admin { background-color: #292310 !important; border-color: #755913 !important; color: #ffc107 !important; }
    @endif
  </style>
</head>
<body class="bg-main" style="margin: 0; padding: 0; background-color: {{ $background }}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="bg-main" bgcolor="{{ $background }}" style="background-color: {{ $background }};">
    <tr>
      <td align="center" class="outer-padding" style="padding: 12px 20px;">
        <table role="presentation" class="card-box" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="{{ $card }}" style="max-width: 540px; background-color: {{ $card }}; border: 1px solid {{ $border }}; border-radius: 12px;">
          <tr>
            <td class="card-padding" style="padding: 24px;">
              <span class="{{ $isAdmin ? 'badge-admin' : 'badge-user' }}" style="display: inline-block; padding: 4px 10px; background-color: {{ $badgeBackground }}; border: 1px solid {{ $badgeBorder }}; border-radius: 20px; color: {{ $badgeText }}; font-size: 12px; font-weight: 600; line-height: 1.2;">
                {{ $isAdmin ? ($isEnglish ? 'Admin Notification' : 'Notificación de Admin') : ($isEnglish ? 'Contact Confirmed' : 'Contacto Confirmado') }}
              </span>
              <h1 class="text-title" style="margin: 14px 0 12px; color: {{ $text }}; font-size: 20px; font-weight: 700; line-height: 1.3; letter-spacing: -0.4px;">{{ $title }}</h1>
              <p class="text-body" style="margin: 0 0 16px; color: {{ $bodyText }}; font-size: 14px; line-height: 1.6;">
                @if($isAdmin)
                  <strong>{{ $isEnglish ? 'System Alert:' : 'Alerta de Sistema:' }}</strong>
                  {{ $isEnglish ? 'You have received a message through the portfolio contact form.' : 'Has recibido un mensaje desde el formulario de contacto del portafolio.' }}
                @else
                  <strong>{{ $isEnglish ? 'Hello' : 'Hola' }} {{ $contact->name }},</strong>
                  {{ $isEnglish ? 'I have received your message through the contact form. I will reply as soon as possible, usually within 24 to 48 hours.' : 'He recibido tu mensaje correctamente a través del formulario de contacto. Te responderé lo antes posible, normalmente en un plazo de 24 a 48 horas.' }}
                @endif
              </p>
              <table role="presentation" class="detail-box" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="{{ $panel }}" style="background-color: {{ $panel }}; border: 1px solid {{ $border }}; border-radius: 8px;">
                <tr>
                  <td class="detail-padding" style="padding: 16px;">
                    <p class="text-title" style="margin: 0 0 8px; color: {{ $text }}; font-size: 13px; font-weight: 700; line-height: 1.5;">
                      {{ $isAdmin ? ($isEnglish ? 'Message Details:' : 'Detalles del Mensaje:') : ($isEnglish ? 'Submission Summary:' : 'Resumen de tu envío:') }}
                    </p>
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; font-size: 13px;">
                      @foreach($rows as [$label, $value])
                        <tr>
                          <td class="detail-label {{ $loop->last ? '' : 'divider' }}" valign="top" style="width: 32%; padding: 6px 0; color: {{ $muted }}; line-height: 1.3; {{ $loop->last ? '' : 'border-bottom: 1px solid '.$border.';' }}">{{ $label }}</td>
                          <td class="detail-value {{ $loop->last ? '' : 'divider' }}" align="right" valign="top" style="padding: 6px 0 6px 8px; color: {{ $text }}; font-family: 'Courier New', Courier, monospace; font-weight: 700; line-height: 1.3; {{ $loop->last ? '' : 'border-bottom: 1px solid '.$border.';' }}">{{ $value }}</td>
                        </tr>
                      @endforeach
                    </table>
                    @if($isAdmin)
                      <p class="text-body divider" style="margin: 8px 0 0; padding-top: 12px; border-top: 1px solid {{ $border }}; color: {{ $bodyText }}; font-size: 13px; font-style: italic; line-height: 1.5; overflow-wrap: anywhere;">&quot;{!! nl2br(e($contact->message)) !!}&quot;</p>
                    @endif
                  </td>
                </tr>
              </table>
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td align="center" style="padding: 28px 0 24px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td bgcolor="#2f86ff" style="background-color: #2f86ff; border-radius: 24px; mso-padding-alt: 10px 20px;">
                          <a href="{{ $buttonUrl }}" style="display: inline-block; padding: 10px 20px; border-radius: 24px; color: #ffffff; font-size: 14px; font-weight: 700; line-height: 18px; text-decoration: none;">
                            {{ $isAdmin ? ($isEnglish ? 'Reply to ' : 'Responder a ').$contact->name : ($isEnglish ? 'Back to Website' : 'Volver al Sitio Web') }}
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
              <p class="text-muted divider" style="margin: 0; padding-top: 18px; border-top: 1px solid {{ $border }}; color: {{ $muted }}; text-align: center; font-size: 12px; line-height: 1.3;">
                @if($isAdmin)
                  {{ $isEnglish ? 'Félix Saucedo Portfolio' : 'Portafolio de Félix Saucedo' }} · {{ $isEnglish ? 'Environment' : 'Entorno' }}: {{ app()->isProduction() ? ($isEnglish ? 'Production' : 'Producción') : ($isEnglish ? 'Development' : 'Desarrollo') }}
                @else
                  {{ $isEnglish ? 'This is an automatic email sent from the professional portfolio. Please do not reply directly to this address.' : 'Este es un correo automático enviado desde el portafolio profesional. Por favor no respondas directamente a esta dirección.' }}
                @endif
                <br><br>
                <a href="https://felixsaucedo.com" style="color: {{ $muted }}; text-decoration: none;">felixsaucedo.com</a>
                · <a href="mailto:contacto@felixsaucedo.com" style="color: {{ $muted }}; text-decoration: none;">contacto@felixsaucedo.com</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
