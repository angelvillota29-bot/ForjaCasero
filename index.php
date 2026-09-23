<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forja Casero — Panel de bots</title>
<link rel="stylesheet" href="assets/styles.css?v=<?= filemtime(__DIR__ . '/assets/styles.css') ?>">
<script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

<div id="loginScreen" class="hidden">
  <div class="login-card">
    <div class="login-mark">FC</div>
    <h1>Forja Casero</h1>
    <p>Crea la cantidad de bots que necesites, sin límite. Cada uno con su propio nombre,
    tipo de negocio y descripción de qué hace y cómo lo hace. Todos pueden usar la misma
    llave API o una propia, a tu gusto.</p>
    <div class="login-features">
      <div><span class="dot">●</span> Bots ilimitados, uno por negocio o canal</div>
      <div><span class="dot">●</span> Llave API compartida o independiente por bot</div>
      <div><span class="dot">●</span> Acceso solo con tu cuenta de Google, sin contraseñas</div>
    </div>
    <div id="googleBtnHost"></div>
    <div id="loginError" class="login-error hidden"></div>
    <div id="loginConfigNote" class="login-config-note"></div>
  </div>
</div>

<div id="app" class="hidden"></div>

<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
