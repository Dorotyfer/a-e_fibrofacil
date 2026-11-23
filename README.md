# Fibrofácil

Sistema interno de taller para XAMPP. Requiere PHP 8.2 con las extensiones `pdo_mysql`, `mbstring` y `fileinfo`, y MySQL o MariaDB.

## Instalación

1. Inicia Apache y MySQL en XAMPP.
2. Desde la carpeta del proyecto, ejecuta en PowerShell:

   ```powershell
   $env:FIBRO_ADMIN_PASSWORD = Read-Host 'Contraseña inicial (12 a 72 bytes)' -MaskInput
   & C:/xampp/php/php.exe install.php admin
   Remove-Item Env:FIBRO_ADMIN_PASSWORD
   ```

   Si tu versión de PowerShell no admite `-MaskInput`, usa PowerShell 7 o carga la contraseña en la variable de entorno desde tu gestor de secretos.

3. Abre [Fibrofácil en XAMPP](http://localhost/a-e_fibrofacil/) e ingresa con el usuario creado. El instalador conserva los datos y la cuenta existentes si se vuelve a ejecutar.
4. Para usar una conexión diferente, configura `FIBRO_DB_HOST`, `FIBRO_DB_PORT`, `FIBRO_DB_NAME`, `FIBRO_DB_USER` y `FIBRO_DB_PASSWORD` tanto en el entorno de Apache como en el de instalación. Por defecto la aplicación usa la base local `ae_fibrofacil`. Deja el puerto local y el usuario root sin contraseña solo en un XAMPP de desarrollo aislado.
5. Para aceptar comprobantes de hasta 5 MB, establece `upload_max_filesize = 5M` y `post_max_size = 8M` en el `php.ini` de Apache y reinicia Apache si cambiaste esos valores. La base de datos contiene los archivos, comprobantes e historial. Inclúyela completa en los respaldos.

## Operación

- Registra el espesor y la compra de MDF, y una compra de pegamento si lo usas. Las dimensiones de la compra se ingresan en centímetros. El costo de la compra corresponde al lote completo.
- Exporta un producto por PLT en HP-GL con contornos cerrados convertidos a polilíneas, sin escalado personalizado. La vista previa admite `IN`, `SP`, `VS`, `FS`, `PW`, `PA`, `PR`, `PU` y `PD` en el subconjunto descrito por el importador. Confirma la escala con un diseño de dimensiones conocidas. Los contornos dentro de otros alternan entre agujeros y piezas. Los trazos abiertos quedan visibles, sin consumo asignado. Ajusta existencias para reflejar desperdicios reales.
- Crea las cuentas de los vendedores y sus salarios. Cada vendedor registra sus pedidos y adjunta la seña. El administrador la revisa y el sistema registra el consumo al aprobarla, siempre que alcance el material. Si faltó material, repón y reintenta desde el pedido.
- El vendedor registra la entrega con el comprobante del saldo. El reporte mensual imputa cada pago al mes de su aprobación por el administrador.

## Verificación

`C:/xampp/php/php.exe tests/domain.php` verifica geometría, comisiones y redondeo de pagos.
