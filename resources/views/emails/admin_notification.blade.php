<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nuevo contacto del portafolio</title></head>
<body>
    <h1>Nuevo contacto del portafolio</h1>
    <p><strong>Nombre:</strong> {{ $contact->name }}</p>
    <p><strong>Email:</strong> {{ $contact->email }}</p>
    <p><strong>Asunto:</strong> {{ $contact->subject ?: 'Consulta General' }}</p>
    <p style="white-space: pre-wrap">{{ $contact->message }}</p>
</body>
</html>
