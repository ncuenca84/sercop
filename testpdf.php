<?php
$contenido = file_get_contents('/home/brixs/public_html/sistema.brixs.cloud/resources/views/layouts/app.php');
header('Content-Type: text/plain; charset=utf-8');
echo $contenido;