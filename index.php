<?php
/**
 * PESTO PDF - Protocollo 2.5
 * Feature: Recursive Folder Merging
 * Security: Zero-Footprint
 */

session_start();
$tmp_dir = __DIR__ . '/pesto_tmp/';
if (!file_exists($tmp_dir)) mkdir($tmp_dir, 0777, true);

// 1. FUNZIONE DI SCANSIONE RICORSIVA
function get_all_pdfs($main_path) {
    $pdf_list = [];
    if (!is_dir($main_path)) return [];

    $it = new RecursiveDirectoryIterator($main_path);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if (strtolower($file->getExtension()) === 'pdf') {
            $pdf_list[] = $file->getPathname();
        }
    }
    // Ordina alfabeticamente per garantire coerenza nell'unione
    sort($pdf_list);
    return $pdf_list;
}

// 2. AZIONE: SCANSIONE E MERGE
if (isset($_POST['scan_folder'])) {
    require_once('fpdf/fpdf.php');
    require_once('fpdi/src/autoload.php');

    // Percorso da scansionare (es: 'documenti/2024')
    $target_path = __DIR__ . '/' . rtrim($_POST['scan_folder'], '/');
    $all_pdfs = get_all_pdfs($target_path);

    if (empty($all_pdfs)) {
        echo json_encode(['success' => false, 'error' => 'Nessun PDF trovato nella cartella specificata.']);
        exit;
    }

    $session_id = uniqid('pesto_crawl_');
    $pdf = new \setasign\Fpdi\Fpdi();

    try {
        foreach ($all_pdfs as $file_path) {
            $pageCount = $pdf->setSourceFile($file_path);
            for ($n = 1; $n <= $pageCount; $n++) {
                $tplIdx = $pdf->importPage($n);
                $specs = $pdf->getTemplateSize($tplIdx);
                $pdf->AddPage($specs['orientation'], [$specs['width'], $specs['height']]);
                $pdf->useTemplate($tplIdx);
            }
        }

        $output_path = $tmp_dir . $session_id . '_merged.pdf';
        $pdf->Output('F', $output_path);

        echo json_encode(['success' => true, 'file' => basename($output_path), 'count' => count($all_pdfs)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. DOWNLOAD (Invariato)
if (isset($_GET['download'])) {
    $file = $tmp_dir . basename($_GET['download']);
    if (file_exists($file) && strpos($file, 'pesto_') !== false) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Pesto_Crawler_Export.pdf"');
        readfile($file);
        @unlink($file);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Pesto PDF Crawler</title>
    <style>
        :root { --bg: #0a0a0a; --surface: #121212; --primary: #00e676; --border: #222; }
        body { background: var(--bg); color: #ececec; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: var(--surface); border: 1px solid var(--border); padding: 40px; border-radius: 20px; width: 100%; max-width: 500px; text-align: center; }
        h1 { color: var(--primary); font-size: 1.2rem; letter-spacing: 3px; margin-bottom: 30px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        label { display: block; font-size: 0.7rem; color: #666; margin-bottom: 8px; text-transform: uppercase; }
        input[type="text"] { width: 100%; background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 8px; box-sizing: border-box; }
        .btn-action { background: var(--primary); color: #000; border: none; width: 100%; padding: 15px; border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-action:hover { opacity: 0.8; }
        #status { margin-top: 20px; font-size: 0.8rem; color: #888; }
        .loader { display: none; margin: 20px auto; border: 3px solid #333; border-top: 3px solid var(--primary); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="card">
    <h1>PESTO CRAWLER</h1>
    
    <div class="input-group">
        <label>Cartella sorgente (relativa a questo file)</label>
        <input type="text" id="folder_path" placeholder="es: archivio/pdf_2024">
    </div>

    <button class="btn-action" onclick="startCrawl()">AVVIA SCANSIONE E MERGE</button>

    <div class="loader" id="loader"></div>
    <div id="status"></div>
    
    <a href="#" id="dl_link" style="display:none; color:var(--primary); text-decoration:none; margin-top:20px; font-weight:bold;">SCARICA PDF UNITO</a>
</div>

<script>
    async function startCrawl() {
        const folder = document.getElementById('folder_path').value;
        if(!folder) return alert("Inserisci un percorso");

        document.getElementById('loader').style.display = 'block';
        document.getElementById('status').innerText = "Scansione sub-cartelle in corso...";
        document.getElementById('dl_link').style.display = 'none';

        const fd = new FormData();
        fd.append('scan_folder', folder);

        try {
            const resp = await fetch('', { method: 'POST', body: fd });
            const res = await resp.json();
            
            document.getElementById('loader').style.display = 'none';

            if (res.success) {
                document.getElementById('status').innerText = "Trovati e uniti " + res.count + " file PDF.";
                document.getElementById('dl_link').href = "?download=" + res.file;
                document.getElementById('dl_link').style.display = 'block';
            } else {
                document.getElementById('status').innerText = "Errore: " + res.error;
            }
        } catch (e) {
            alert("Errore di connessione.");
        }
    }
</script>

</body>
</html>