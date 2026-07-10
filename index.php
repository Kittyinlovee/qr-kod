<?php
// 1. Composer kütüphanelerini dahil ediyoruz
require 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

// Arka planda indirme işlemi veya API önizleme isteği tetiklendiğinde:
if (isset($_REQUEST['action'])) {
    $link = !empty($_REQUEST['link']) ? $_REQUEST['link'] : 'https://github.com';
    $format = isset($_REQUEST['format']) ? $_REQUEST['format'] : 'png';
    $qrColorHex = isset($_REQUEST['qr_color']) ? $_REQUEST['qr_color'] : '#000000';
    $bgColorHex = isset($_REQUEST['bg_color']) ? $_REQUEST['bg_color'] : '#ffffff';
    $size = isset($_REQUEST['size']) ? (int)$_REQUEST['size'] : 300;

    // Hex renklerini RGB'ye çeviriyoruz
    list($r, $g, $b) = sscanf($qrColorHex, "#%02x%02x%02x");
    list($bg_r, $bg_g, $bg_b) = sscanf($bgColorHex, "#%02x%02x%02x");

    // QR Kodu ayarları
    $qrCode = new QrCode(
        data: $link,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: $size,
        margin: 15,
        foregroundColor: new Color($r, $g, $b),
        backgroundColor: new Color($bg_r, $bg_g, $bg_b)
    );

    // CANLI ÖNİZLEME İSTEĞİ (JavaScript burayı çağırır)
    if ($_REQUEST['action'] === 'preview') {
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        header('Content-Type: image/png');
        echo $result->getString();
        exit;
    }

    // DOSYA İNDİRME İSTEKLERİ
    if ($_REQUEST['action'] === 'download') {
        if ($format === 'png') {
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="qrcode.png"');
            echo $result->getString();
            exit;
        } 
        elseif ($format === 'svg') {
            $writer = new SvgWriter();
            $result = $writer->write($qrCode);
            header('Content-Type: image/svg+xml');
            header('Content-Disposition: attachment; filename="qrcode.svg"');
            echo $result->getString();
            exit;
        } 
        elseif ($format === 'pdf') {
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            file_put_contents($tempFile, $result->getString());

            $pdf = new \FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'Tasarlanmis QR Kod', 0, 1, 'C');
            $pdf->Ln(20);
            $pdf->Image($tempFile, 55, 40, 100, 100, 'PNG');
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="qrcode.pdf"');
            $pdf->Output('I', 'qrcode.pdf');
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
        /* İki Bölmeli Ana Panel Kutusu */
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
        /* SOL TARAF: Canlı Önizleme Alanı (Adobe Tarzı Gri Alan) */
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
        /* SAĞ TARAF: Kontrol Ve Özelleştirme Paneli */
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
        /* Sekme Butonları (Bağlantı, Stil, Renk, Dosyalar) */
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
        /* Sekme İçerikleri */
        .tab-content {
            display: none;
            flex: 1;
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
        input[type="text"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 14px;
        }
        input[type="text"]:focus, select:focus {
            outline: none;
            border-color: #ffb6c1;
        }
        .color-pickers {
            display: flex;
            gap: 15px;
        }
        .color-box {
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
        /* Pembe & Mavi Geçişli Sihirli İndirme Butonu */
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
    <!-- SOL BÖLÜM: Canlı QR Kod Önizleme Ekranı -->
    <div class="preview-side">
        <div class="qr-card-box">
            <!-- JavaScript buradaki resmi anlık olarak güncelleyecek -->
            <img id="qrPreview" src="index.php?action=preview&link=https://github.com" alt="QR Kod Önizleme">
        </div>
    </div>

    <!-- SAĞ BÖLÜM: Özelleştirme ve Sekme Alanı -->
    <div class="control-side">
        <h2>QR Kodu Düzenle ✨</h2>
        
        <!-- Sekme Menüsü -->
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab('tab-link')">Bağlantı</button>
            <button class="tab-btn" onclick="switchTab('tab-renk')">Renk</button>
            <button class="tab-btn" onclick="switchTab('tab-boyut')">Boyut</button>
            <button class="tab-btn" onclick="switchTab('tab-dosyalar')">Dosyalar</button>
        </div>

        <!-- Form Elemanları -->
        <form id="qrForm" action="index.php" method="GET">
            <input type="hidden" name="action" value="download">

            <!-- 1. SEKME: BAĞLANTI -->
            <div id="tab-link" class="tab-content active">
                <div class="form-group">
                    <label for="link">URL girin veya bulun:</label>
                    <input type="text" id="link" name="link" value="https://github.com" placeholder="https://example.com" oninput="updatePreview()">
                    <p style="font-size:12px; color:#a0aec0; margin-top:5px;">QR kodunuz bu URL'ye yönlenecektir.</p>
                </div>
            </div>

            <!-- 2. SEKME: RENK -->
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

            <!-- 3. SEKME: BOYUT -->
            <div id="tab-boyut" class="tab-content">
                <div class="form-group">
                    <label for="size">QR Kod Çözünürlüğü (Piksel):</label>
                    <select id="size" name="size" onchange="updatePreview()">
                        <option value="200">200 x 200 (Küçük)</option>
                        <option value="300" selected>300 x 300 (Standart)</option>
                        <option value="500">500 x 500 (Yüksek Kalite)</option>
                    </select>
                </div>
            </div>

            <!-- 4. SEKME: DOSYALAR (İNDİRME FORMATI) -->
            <div id="tab-dosyalar" class="tab-content">
                <div class="form-group">
                    <label for="format">Dosya Tipi Seçin:</label>
                    <select id="format" name="format">
                        <option value="png">PNG (Görsel Resim)</option>
                        <option value="svg">SVG (Vektörel Çizim)</option>
                        <option value="pdf">PDF Belgesi</option>
                    </select>
                </div>
            </div>

            <!-- Master İndirme Butonu -->
            <button type="submit" class="download-btn">Tasarımı İndir 🚀</button>
        </form>
    </div>
</div>

<script>
    // Sekmeler arası geçişi sağlayan fonksiyon
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    // Renk, link veya boyut değiştiğinde sol taraftaki resmi anlık güncelleyen sihirli fonksiyon
    function updatePreview() {
        const link = encodeURIComponent(document.getElementById('link').value);
        const qrColor = encodeURIComponent(document.getElementById('qr_color').value);
        const bgColor = encodeURIComponent(document.getElementById('bg_color').value);
        const size = document.getElementById('size').value;

        // Resmi arkadaki PHP API önizleme moduna bağlayıp cache sorununu çözmek için zaman ekliyoruz
        const newSrc = `index.php?action=preview&link=${link}&qr_color=${qrColor}&bg_color=${bgColor}&size=${size}&t=${new Date().getTime()}`;
        document.getElementById('qrPreview').src = newSrc;
    }
</script>

</body>
</html>