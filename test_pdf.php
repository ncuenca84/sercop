<?php

require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

function convertirHTML($texto){
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $texto = nl2br($texto);
    return $texto;
}

echo "<h2>Secciones detectadas</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        echo "<p style='color:red;'>Debe seleccionar un archivo PDF válido.</p>";
    } else {

        $tmp = $_FILES['pdf']['tmp_name'];
        $nombre = $_FILES['pdf']['name'];

        echo "<p><strong>Archivo cargado:</strong> " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</p>";

        $parser = new Parser();
        $pdf = $parser->parseFile($tmp);
        $text = $pdf->getText();

        $partes = preg_split('/(\n\d+\.-\s+[A-ZÁÉÍÓÚÑ ]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($partes) < 3) {
            echo "<p style='color:orange;'>No se detectaron secciones con el patrón esperado.</p>";
            echo "<pre>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</pre>";
        } else {
            for ($i = 1; $i < count($partes); $i += 2) {
                $titulo = trim($partes[$i]);
                $contenido = isset($partes[$i+1]) ? convertirHTML(trim($partes[$i+1])) : '';

                echo "<h3>" . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . "</h3>";
                echo "<textarea class='editor'>" . $contenido . "</textarea><br><br>";
            }
        }
    }
}
?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="pdf" accept=".pdf,application/pdf" required>
    <br><br>
    <button type="submit">Analizar PDF</button>
</form>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '.editor',
    height: 260,
    menubar: false
});
</script>