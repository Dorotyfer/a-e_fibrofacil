<section class="panel login">
  <div class="brand"><span class="brand-mark" aria-hidden="true">F</span> Fibrofácil</div>
  <h1>Iniciar sesión</h1>
  <form method="post" class="stack">
    <?php hidden('login'); ?>
    <label>Usuario<input name="username" autocomplete="username" required autofocus maxlength="80"></label>
    <label>Contraseña<input name="password" type="password" autocomplete="current-password" required maxlength="72"></label>
    <button>Ingresar</button>
  </form>
</section>
