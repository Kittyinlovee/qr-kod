<?php
// ==========================================
// 1. ARKA PLAN (PHP) KODLARI
// ==========================================
require 'vendor/autoload.php';

use Endroid\QrCode\QrCode; // qr ana hat yapısıdır 
use Endroid\QrCode\Encoding\Encoding;// metnin karakter kodlmasını belirler
use Endroid\QrCode\ErrorCorrectionLevel; // qr hata düzeltme olması için 
use Endroid\QrCode\Color\Color;// qr renkleri için 
use Endroid\QrCode\Writer\PngWriter;// qr png formatında çıktı alabilmek için
use Endroid\QrCode\Writer\SvgWriter;//

if (isset($_REQUEST['action'])) {
    $link = !empty($_REQUEST['link']) ? $_REQUEST['link'] : 'https://github.com';
    $format = isset($_REQUEST['format']) ? $_REQUEST['format'] : 'png';
    $qrColorHex = isset($_REQUEST['qr_color']) ? $_REQUEST['qr_color'] : '#000000';
    $bgColorHex = isset($_REQUEST['bg_color']) ? $_REQUEST['bg_color'] : '#ffffff';
    
    // Kullanıcının girdiği milimetrik boyutları alıyoruz (Boşsa varsayılan 200mm yapıyoruz)
    $pdf_width = !empty($_REQUEST['pdf_width']) ? (int)$_REQUEST['pdf_width'] : 200;
    $pdf_height = !empty($_REQUEST['pdf_height']) ? (int)$_REQUEST['pdf_height'] : 200;


    // pdf için çözünürlük ayarlamak için 
    if ($_REQUEST['action'] === 'download' && $format === 'pdf') {
        $size = 1200; // PDF kalitesi için yüksek çözünürlük sabit kalıyor
    } else {
        $size = isset($_REQUEST['size']) ? (int)$_REQUEST['size'] : 300;
    }

    list($r, $g, $b) = sscanf($qrColorHex, "#%02x%02x%02x");
    list($bg_r, $bg_g, $bg_b) = sscanf($bgColorHex, "#%02x%02x%02x");

    $qrCode = new QrCode(
        data: $link,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::Low,
        size: $size,
        margin: 0,
        foregroundColor: new Color($r, $g, $b),
        backgroundColor: new Color($bg_r, $bg_g, $bg_b)
    );

    if ($_REQUEST['action'] === 'preview') {
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        header('Content-Type: image/png');
        echo $result->getString();
        exit;
    }

    if ($_REQUEST['action'] === 'download') {
        if ($format === 'png') {
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="qrcode_'.time().'.png"');
            echo $result->getString();
            exit;
        } 
        elseif ($format === 'svg') {
            $writer = new SvgWriter();
            $result = $writer->write($qrCode);
            header('Content-Type: image/svg+xml');
            header('Content-Disposition: attachment; filename="qrcode_'.time().'.svg"');
            echo $result->getString();
            exit;
        } 
        elseif ($format === 'pdf') {
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            file_put_contents($tempFile, $result->getString());

            // TARAYICI CACHE (HAFIZA) ENGELLEME BAŞLIKLARI
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');

            // Sayfa boyutunu kullanıcının girdiği değerlere (mm) göre ayarlıyoruz
            $pdf = new \FPDF('P', 'mm', array($pdf_width, $pdf_height));
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->AddPage();
            
            // Kare formu bozmamak için girilen boyutların en küçüğünü temel alıp QR boyutunu belirliyoruz
            $min_side = min($pdf_width, $pdf_height);
            $qr_display_size = $min_side - 30; // Kenarlardan dengeli boşluk kalması için -30 yaptık
            
            // DÜZENLEME: Hem X hem Y ekseninde tam matematiksel ortalamayı kuruyoruz
            $x_pos = ($pdf_width - $qr_display_size) / 2;
            $y_pos = ($pdf_height - $qr_display_size) / 2; // Artık altında yazı olmayacağı için tam merkezde!
            
            // QR Kodu basıyoruz
            $pdf->Image($tempFile, $x_pos, $y_pos, $qr_display_size, $qr_display_size, 'PNG');
            
            // Altındaki FPDF font, renk ve cell (yazı) komutlarını tamamen uçurduk!
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="qrcode_'.time().'.pdf"');
            $pdf->Output('I', 'qrcode_'.time().'.pdf');
            unlink($tempFile);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gelişmiş QR Kod Stüdyosu</title>
    <style>
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #ffdee9 0%, #b5fffc 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .app-workspace {
            display: flex;
            background-color: #ffffff;
            width: 90vw;
            max-width: 1000px;
            height: 600px;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .preview-side {
            flex: 1.2;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            border-right: 1px solid #e8e8e8;
            position: relative;
        }
        .qr-card-box {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .qr-card-box img {
            max-width: 260px;
            height: auto;
            border-radius: 8px;
        }
        .control-side {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }
        .control-side h2 {
            margin-top: 0;
            color: #2d3748;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .tabs-nav {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        .tab-btn {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            background-color: #e2e8f0;
            color: #1a202c;
            font-weight: bold;
            border-color: #cbd5e1;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 14px;
        }
        input[type="text"]:focus, input[type="number"]:focus, select:focus {
            outline: none;
            border-color: #ffb6c1;
        }
        .color-pickers, .size-inputs {
            display: flex;
            gap: 15px;
        }
        .color-box, .size-box {
            flex: 1;
        }
        input[type="color"] {
            width: 100%;
            height: 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            background: none;
            padding: 0;
        }
        .download-btn {
            width: 100%;
            background: linear-gradient(135deg, #ffb6c1 0%, #87ceeb 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-top: auto;
            transition: transform 0.2s ease;
        }
        .download-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="app-workspace">
    <div class="preview-side">
        <div class="qr-card-box">
            <img id="qrPreview" src="index.php?action=preview&link=https://github.com&qr_color=%23000000&bg_color=%23ffffff&size=300" alt="QR Kod Önizleme">
        </div>
    </div>

    <div class="control-side">
        <h2>QR Kodu Düzenle ✨</h2>
        
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-link')">Bağlantı</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-renk')">Renk</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-boyut')">Boyut</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-dosyalar')">Dosyalar</button>
        </div>

        <form id="qrForm" action="index.php" method="GET" style="display: flex; flex-direction: column; flex: 1;">
            <input type="hidden" name="action" value="download">
            <input type="hidden" id="size" name="size" value="300">

            <div id="tab-link" class="tab-content active">
                <div class="form-group">
                    <label for="link">URL girin veya bulun:</label>
                    <input type="text" id="link" name="link" value="https://github.com" placeholder="https://example.com" oninput="updatePreview()">
                    <p style="font-size:12px; color:#a0aec0; margin-top:5px;">QR kodunuz bu URL'ye yönlenecektir.</p>
                </div>
            </div>

            <div id="tab-renk" class="tab-content">
                <div class="color-pickers">
                    <div class="color-box">
                        <label for="qr_color">QR Çizgi Rengi:</label>
                        <input type="color" id="qr_color" name="qr_color" value="#000000" onchange="updatePreview()">
                    </div>
                    <div class="color-box">
                        <label for="bg_color">Arka Plan Rengi:</label>
                        <input type="color" id="bg_color" name="bg_color" value="#ffffff" onchange="updatePreview()">
                    </div>
                </div>
            </div>

            <!-- DÜZENLENEN KISIM: Select menüsü yerine yan yana iki dinamik input kutusu -->
            <div id="tab-boyut" class="tab-content">
                <div class="form-group">
                    <label>PDF Sayfa Boyutları (Milimetre - mm):</label>
                    <div class="size-inputs">
                        <div class="size-box">
                            <label for="pdf_width" style="font-size: 11px; color: #718096;">Genişlik (W):</label>
                            <input type="number" id="pdf_width" name="pdf_width" value="200" min="50" max="500" oninput="updatePreview()">
                        </div>
                        <div class="size-box">
                            <label for="pdf_height" style="font-size: 11px; color: #718096;">Yükseklik (H):</label>
                            <input type="number" id="pdf_height" name="pdf_height" value="200" min="50" max="500" oninput="updatePreview()">
                        </div>
                    </div>
                    <p style="font-size:12px; color:#a0aec0; margin-top:8px;">Girilen ölçüler sadece PDF çıktısı için geçerlidir.</p>
                </div>
            </div>

            <div id="tab-dosyalar" class="tab-content">
                <div class="form-group">
                    <label for="format">Dosya Tipi Seçin:</label>
                    <select id="format" name="format">
                        <option value="png">PNG (Görsel Resim)</option>
                        <option value="svg">SVG (Vektörel Çizim)</option>
                        <option value="pdf" selected>PDF Belgesi</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="download-btn">Tasarımı İndir 🚀</button>
        </form>
    </div>
</div>

<script>
    function switchTab(evt, tabId) {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function updatePreview() {
        const link = encodeURIComponent(document.getElementById('link').value);
        const qrColor = encodeURIComponent(document.getElementById('qr_color').value);
        const bgColor = encodeURIComponent(document.getElementById('bg_color').value);
        const size = document.getElementById('size').value;

        const newSrc = `index.php?action=preview&link=${link}&qr_color=${qrColor}&bg_color=${bgColor}&size=${size}&t=${new Date().getTime()}`;
        document.getElementById('qrPreview').src = newSrc;
    }
</script>

</body>
</html>