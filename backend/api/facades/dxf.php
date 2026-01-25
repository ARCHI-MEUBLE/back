<?php
/**
 * API: Générer et télécharger un fichier DXF pour une façade
 * GET /api/facades/dxf?facade_id=<id>
 *
 * Génère un fichier DXF au format AC1027 (compatible ezdxf)
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Vérifier l'authentification admin
$session = Session::getInstance();
if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $facadeId = $_GET['facade_id'] ?? null;

    if (!$facadeId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de façade requis']);
        exit;
    }

    // Récupérer les données de la façade
    $db = Database::getInstance();
    $facade = $db->queryOne(
        "SELECT id, config_data FROM order_facade_items WHERE id = ?",
        [$facadeId]
    );

    if (!$facade) {
        http_response_code(404);
        echo json_encode(['error' => 'Façade non trouvée']);
        exit;
    }

    // Parser les données de configuration
    $config = is_string($facade['config_data']) ? json_decode($facade['config_data'], true) : $facade['config_data'];

    if (!$config) {
        http_response_code(400);
        echo json_encode(['error' => 'Configuration de façade invalide']);
        exit;
    }

    // Dimensions en mm -> convertir en mètres pour le DXF
    $widthMm = floatval($config['width'] ?? 600);
    $heightMm = floatval($config['height'] ?? 800);
    $depthMm = floatval($config['depth'] ?? 19);
    $drillings = $config['drillings'] ?? [];
    $materialName = $config['material']['name'] ?? 'Materiau';

    // Convertir en mètres (comme le DXF de référence)
    $width = $widthMm / 1000;
    $height = $heightMm / 1000;
    $depth = $depthMm / 1000;

    // Générer le contenu DXF
    $dxf = generateFacadeDXF($width, $height, $depth, $drillings, $facadeId, $materialName);

    // Envoyer le fichier DXF
    header('Content-Type: application/dxf');
    header('Content-Disposition: attachment; filename="facade_' . $facadeId . '.dxf"');
    header('Content-Length: ' . strlen($dxf));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    echo $dxf;
    exit;

} catch (Exception $e) {
    error_log("Erreur DXF facade: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Générer un fichier DXF au format AC1027 (compatible ezdxf)
 */
function generateFacadeDXF($width, $height, $depth, $drillings, $facadeId, $materialName) {
    $handleCounter = 0x100; // Compteur de handles hexadécimaux

    $dxf = "";

    // ===== HEADER SECTION =====
    $dxf .= "  0\nSECTION\n";
    $dxf .= "  2\nHEADER\n";
    $dxf .= "  9\n\$ACADVER\n  1\nAC1027\n";
    $dxf .= "  9\n\$ACADMAINTVER\n 70\n105\n";
    $dxf .= "  9\n\$DWGCODEPAGE\n  3\nANSI_1252\n";
    $dxf .= "  9\n\$LASTSAVEDBY\n  1\narchimeuble\n";
    $dxf .= "  9\n\$INSBASE\n 10\n0.0\n 20\n0.0\n 30\n0.0\n";
    $dxf .= "  9\n\$EXTMIN\n 10\n0.0\n 20\n0.0\n 30\n0.0\n";
    $dxf .= "  9\n\$EXTMAX\n 10\n" . $width . "\n 20\n" . $height . "\n 30\n0.0\n";
    $dxf .= "  9\n\$LIMMIN\n 10\n0.0\n 20\n0.0\n";
    $dxf .= "  9\n\$LIMMAX\n 10\n420.0\n 20\n297.0\n";
    $dxf .= "  9\n\$ORTHOMODE\n 70\n0\n";
    $dxf .= "  9\n\$REGENMODE\n 70\n1\n";
    $dxf .= "  9\n\$FILLMODE\n 70\n1\n";
    $dxf .= "  9\n\$QTEXTMODE\n 70\n0\n";
    $dxf .= "  9\n\$MIRRTEXT\n 70\n1\n";
    $dxf .= "  9\n\$LTSCALE\n 40\n1.0\n";
    $dxf .= "  9\n\$ATTMODE\n 70\n1\n";
    $dxf .= "  9\n\$TEXTSIZE\n 40\n2.5\n";
    $dxf .= "  9\n\$TEXTSTYLE\n  7\nStandard\n";
    $dxf .= "  9\n\$CLAYER\n  8\n0\n";
    $dxf .= "  9\n\$CELTYPE\n  6\nByLayer\n";
    $dxf .= "  9\n\$CECOLOR\n 62\n256\n";
    $dxf .= "  9\n\$MEASUREMENT\n 70\n1\n";
    $dxf .= "  9\n\$INSUNITS\n 70\n6\n"; // 6 = meters
    $dxf .= "  9\n\$HANDSEED\n  5\nFFF\n";
    $dxf .= "  0\nENDSEC\n";

    // ===== CLASSES SECTION =====
    $dxf .= "  0\nSECTION\n";
    $dxf .= "  2\nCLASSES\n";
    $dxf .= "  0\nENDSEC\n";

    // ===== TABLES SECTION =====
    $dxf .= "  0\nSECTION\n";
    $dxf .= "  2\nTABLES\n";

    // VPORT Table
    $dxf .= "  0\nTABLE\n  2\nVPORT\n  5\n8\n330\n0\n100\nAcDbSymbolTable\n 70\n1\n";
    $dxf .= "  0\nVPORT\n  5\n23\n330\n8\n100\nAcDbSymbolTableRecord\n100\nAcDbViewportTableRecord\n";
    $dxf .= "  2\n*Active\n 70\n0\n";
    $dxf .= " 10\n0.0\n 20\n0.0\n 11\n1.0\n 21\n1.0\n";
    $dxf .= " 12\n0.0\n 22\n0.0\n 13\n0.0\n 23\n0.0\n";
    $dxf .= " 14\n0.5\n 24\n0.5\n 15\n0.5\n 25\n0.5\n";
    $dxf .= " 16\n0.0\n 26\n0.0\n 36\n1.0\n";
    $dxf .= " 17\n0.0\n 27\n0.0\n 37\n0.0\n";
    $dxf .= " 40\n1000.0\n 41\n1.34\n 42\n50.0\n 43\n0.0\n 44\n0.0\n";
    $dxf .= " 50\n0.0\n 51\n0.0\n 71\n0\n 72\n1000\n 73\n1\n 74\n3\n 75\n0\n 76\n0\n 77\n0\n 78\n0\n";
    $dxf .= "281\n0\n 65\n0\n146\n0.0\n";
    $dxf .= "  0\nENDTAB\n";

    // LTYPE Table
    $dxf .= "  0\nTABLE\n  2\nLTYPE\n  5\n2\n330\n0\n100\nAcDbSymbolTable\n 70\n3\n";
    $dxf .= "  0\nLTYPE\n  5\n24\n330\n2\n100\nAcDbSymbolTableRecord\n100\nAcDbLinetypeTableRecord\n";
    $dxf .= "  2\nByBlock\n 70\n0\n  3\n\n 72\n65\n 73\n0\n 40\n0.0\n";
    $dxf .= "  0\nLTYPE\n  5\n25\n330\n2\n100\nAcDbSymbolTableRecord\n100\nAcDbLinetypeTableRecord\n";
    $dxf .= "  2\nByLayer\n 70\n0\n  3\n\n 72\n65\n 73\n0\n 40\n0.0\n";
    $dxf .= "  0\nLTYPE\n  5\n26\n330\n2\n100\nAcDbSymbolTableRecord\n100\nAcDbLinetypeTableRecord\n";
    $dxf .= "  2\nContinuous\n 70\n0\n  3\n\n 72\n65\n 73\n0\n 40\n0.0\n";
    $dxf .= "  0\nENDTAB\n";

    // LAYER Table
    $dxf .= "  0\nTABLE\n  2\nLAYER\n  5\n1\n330\n0\n100\nAcDbSymbolTable\n 70\n4\n";
    // Layer 0
    $dxf .= "  0\nLAYER\n  5\n27\n330\n1\n100\nAcDbSymbolTableRecord\n100\nAcDbLayerTableRecord\n";
    $dxf .= "  2\n0\n 70\n0\n 62\n7\n  6\nContinuous\n370\n-3\n390\n13\n";
    // Layer contour
    $dxf .= "  0\nLAYER\n  5\n28\n330\n1\n100\nAcDbSymbolTableRecord\n100\nAcDbLayerTableRecord\n";
    $dxf .= "  2\ncontour\n 70\n0\n 62\n1\n  6\nContinuous\n370\n-3\n390\n13\n";
    // Layer percages
    $dxf .= "  0\nLAYER\n  5\n29\n330\n1\n100\nAcDbSymbolTableRecord\n100\nAcDbLayerTableRecord\n";
    $dxf .= "  2\npercages\n 70\n0\n 62\n2\n  6\nContinuous\n370\n-3\n390\n13\n";
    // Layer texte
    $dxf .= "  0\nLAYER\n  5\n2A\n330\n1\n100\nAcDbSymbolTableRecord\n100\nAcDbLayerTableRecord\n";
    $dxf .= "  2\ntexte\n 70\n0\n 62\n9\n  6\nContinuous\n370\n-3\n390\n13\n";
    $dxf .= "  0\nENDTAB\n";

    // STYLE Table
    $dxf .= "  0\nTABLE\n  2\nSTYLE\n  5\n5\n330\n0\n100\nAcDbSymbolTable\n 70\n1\n";
    $dxf .= "  0\nSTYLE\n  5\n2B\n330\n5\n100\nAcDbSymbolTableRecord\n100\nAcDbTextStyleTableRecord\n";
    $dxf .= "  2\nStandard\n 70\n0\n 40\n0.0\n 41\n1.0\n 50\n0.0\n 71\n0\n 42\n2.5\n  3\ntxt\n  4\n\n";
    $dxf .= "  0\nENDTAB\n";

    // VIEW Table
    $dxf .= "  0\nTABLE\n  2\nVIEW\n  5\n7\n330\n0\n100\nAcDbSymbolTable\n 70\n0\n";
    $dxf .= "  0\nENDTAB\n";

    // UCS Table
    $dxf .= "  0\nTABLE\n  2\nUCS\n  5\n6\n330\n0\n100\nAcDbSymbolTable\n 70\n0\n";
    $dxf .= "  0\nENDTAB\n";

    // APPID Table
    $dxf .= "  0\nTABLE\n  2\nAPPID\n  5\n3\n330\n0\n100\nAcDbSymbolTable\n 70\n1\n";
    $dxf .= "  0\nAPPID\n  5\n2C\n330\n3\n100\nAcDbSymbolTableRecord\n100\nAcDbRegAppTableRecord\n";
    $dxf .= "  2\nACAD\n 70\n0\n";
    $dxf .= "  0\nENDTAB\n";

    // DIMSTYLE Table
    $dxf .= "  0\nTABLE\n  2\nDIMSTYLE\n  5\n4\n330\n0\n100\nAcDbSymbolTable\n 70\n1\n100\nAcDbDimStyleTable\n";
    $dxf .= "  0\nDIMSTYLE\n105\n2D\n330\n4\n100\nAcDbSymbolTableRecord\n100\nAcDbDimStyleTableRecord\n";
    $dxf .= "  2\nStandard\n 70\n0\n";
    $dxf .= "  0\nENDTAB\n";

    // BLOCK_RECORD Table
    $dxf .= "  0\nTABLE\n  2\nBLOCK_RECORD\n  5\n9\n330\n0\n100\nAcDbSymbolTable\n 70\n2\n";
    $dxf .= "  0\nBLOCK_RECORD\n  5\n17\n330\n9\n100\nAcDbSymbolTableRecord\n100\nAcDbBlockTableRecord\n";
    $dxf .= "  2\n*Model_Space\n340\n1A\n 70\n0\n280\n1\n281\n0\n";
    $dxf .= "  0\nBLOCK_RECORD\n  5\n1B\n330\n9\n100\nAcDbSymbolTableRecord\n100\nAcDbBlockTableRecord\n";
    $dxf .= "  2\n*Paper_Space\n340\n1E\n 70\n0\n280\n1\n281\n0\n";
    $dxf .= "  0\nENDTAB\n";

    $dxf .= "  0\nENDSEC\n";

    // ===== BLOCKS SECTION =====
    $dxf .= "  0\nSECTION\n";
    $dxf .= "  2\nBLOCKS\n";
    // Model_Space block
    $dxf .= "  0\nBLOCK\n  5\n18\n330\n17\n100\nAcDbEntity\n  8\n0\n100\nAcDbBlockBegin\n";
    $dxf .= "  2\n*Model_Space\n 70\n0\n 10\n0.0\n 20\n0.0\n 30\n0.0\n  3\n*Model_Space\n  1\n\n";
    $dxf .= "  0\nENDBLK\n  5\n19\n330\n17\n100\nAcDbEntity\n  8\n0\n100\nAcDbBlockEnd\n";
    // Paper_Space block
    $dxf .= "  0\nBLOCK\n  5\n1C\n330\n1B\n100\nAcDbEntity\n  8\n0\n100\nAcDbBlockBegin\n";
    $dxf .= "  2\n*Paper_Space\n 70\n0\n 10\n0.0\n 20\n0.0\n 30\n0.0\n  3\n*Paper_Space\n  1\n\n";
    $dxf .= "  0\nENDBLK\n  5\n1D\n330\n1B\n100\nAcDbEntity\n  8\n0\n100\nAcDbBlockEnd\n";
    $dxf .= "  0\nENDSEC\n";

    // ===== ENTITIES SECTION =====
    $dxf .= "  0\nSECTION\n";
    $dxf .= "  2\nENTITIES\n";

    // Rectangle de la façade (LWPOLYLINE fermée)
    $dxf .= "  0\nLWPOLYLINE\n";
    $dxf .= "  5\n" . dechex($handleCounter++) . "\n";
    $dxf .= "330\n17\n";
    $dxf .= "100\nAcDbEntity\n";
    $dxf .= "  8\ncontour\n"; // Layer
    $dxf .= "100\nAcDbPolyline\n";
    $dxf .= " 90\n4\n"; // 4 vertices
    $dxf .= " 70\n1\n"; // Closed
    $dxf .= " 10\n0.0\n 20\n0.0\n"; // Point 1 (bas-gauche)
    $dxf .= " 10\n" . sprintf("%.6f", $width) . "\n 20\n0.0\n"; // Point 2 (bas-droit)
    $dxf .= " 10\n" . sprintf("%.6f", $width) . "\n 20\n" . sprintf("%.6f", $height) . "\n"; // Point 3 (haut-droit)
    $dxf .= " 10\n0.0\n 20\n" . sprintf("%.6f", $height) . "\n"; // Point 4 (haut-gauche)

    // Perçages (CIRCLE)
    foreach ($drillings as $drilling) {
        // Position en pourcentage -> mètres
        $cx = ($drilling['x'] / 100) * $width;
        $cy = ($drilling['y'] / 100) * $height;
        // Diamètre en mm -> rayon en mètres
        $diameterMm = floatval($drilling['diameter'] ?? 26);
        $radius = ($diameterMm / 1000) / 2;

        $dxf .= "  0\nCIRCLE\n";
        $dxf .= "  5\n" . dechex($handleCounter++) . "\n";
        $dxf .= "330\n17\n";
        $dxf .= "100\nAcDbEntity\n";
        $dxf .= "  8\npercages\n"; // Layer
        $dxf .= "100\nAcDbCircle\n";
        $dxf .= " 10\n" . sprintf("%.6f", $cx) . "\n";
        $dxf .= " 20\n" . sprintf("%.6f", $cy) . "\n";
        $dxf .= " 30\n0.0\n";
        $dxf .= " 40\n" . sprintf("%.6f", $radius) . "\n";
    }

    // Texte d'information
    $textHeight = 0.02; // 20mm en mètres
    $textY = $height + 0.02; // Au-dessus de la façade
    $widthMm = $width * 1000;
    $heightMm = $height * 1000;
    $depthMm = $depth * 1000;
    $info = "Facade #$facadeId - " . round($widthMm) . "x" . round($heightMm) . "mm - Ep." . round($depthMm) . "mm";

    $dxf .= "  0\nTEXT\n";
    $dxf .= "  5\n" . dechex($handleCounter++) . "\n";
    $dxf .= "330\n17\n";
    $dxf .= "100\nAcDbEntity\n";
    $dxf .= "  8\ntexte\n"; // Layer
    $dxf .= "100\nAcDbText\n";
    $dxf .= " 10\n0.0\n";
    $dxf .= " 20\n" . sprintf("%.6f", $textY) . "\n";
    $dxf .= " 30\n0.0\n";
    $dxf .= " 40\n" . sprintf("%.6f", $textHeight) . "\n";
    $dxf .= "  1\n" . $info . "\n";
    $dxf .= "100\nAcDbText\n";

    // Texte du matériau
    $dxf .= "  0\nTEXT\n";
    $dxf .= "  5\n" . dechex($handleCounter++) . "\n";
    $dxf .= "330\n17\n";
    $dxf .= "100\nAcDbEntity\n";
    $dxf .= "  8\ntexte\n";
    $dxf .= "100\nAcDbText\n";
    $dxf .= " 10\n0.0\n";
    $dxf .= " 20\n" . sprintf("%.6f", $textY + 0.025) . "\n";
    $dxf .= " 30\n0.0\n";
    $dxf .= " 40\n" . sprintf("%.6f", $textHeight) . "\n";
    $dxf .= "  1\n" . $materialName . "\n";
    $dxf .= "100\nAcDbText\n";

    $dxf .= "  0\nENDSEC\n";

    // ===== OBJECTS SECTION =====
    $dxf .= "  0\nSECTION\n";
    $dxf .= "  2\nOBJECTS\n";

    // Root dictionary
    $dxf .= "  0\nDICTIONARY\n";
    $dxf .= "  5\nA\n330\n0\n100\nAcDbDictionary\n281\n1\n";
    $dxf .= "  3\nACAD_GROUP\n350\nB\n";
    $dxf .= "  3\nACAD_LAYOUT\n350\nC\n";

    // Group dictionary
    $dxf .= "  0\nDICTIONARY\n  5\nB\n330\nA\n100\nAcDbDictionary\n281\n1\n";

    // Layout dictionary
    $dxf .= "  0\nDICTIONARY\n  5\nC\n330\nA\n100\nAcDbDictionary\n281\n1\n";
    $dxf .= "  3\nModel\n350\n1A\n";
    $dxf .= "  3\nLayout1\n350\n1E\n";

    // Model layout
    $dxf .= "  0\nLAYOUT\n  5\n1A\n330\nC\n100\nAcDbPlotSettings\n";
    $dxf .= "  1\n\n  4\nA3\n  6\n\n";
    $dxf .= " 40\n7.5\n 41\n20.0\n 42\n7.5\n 43\n20.0\n";
    $dxf .= " 44\n420.0\n 45\n297.0\n";
    $dxf .= " 46\n0.0\n 47\n0.0\n 48\n0.0\n 49\n0.0\n";
    $dxf .= "140\n0.0\n141\n0.0\n142\n1.0\n143\n1.0\n";
    $dxf .= " 70\n1024\n 72\n1\n 73\n0\n 74\n5\n  7\n\n 75\n16\n 76\n0\n 77\n2\n 78\n300\n";
    $dxf .= "147\n1.0\n148\n0.0\n149\n0.0\n";
    $dxf .= "100\nAcDbLayout\n  1\nModel\n 70\n1\n 71\n0\n";
    $dxf .= " 10\n0.0\n 20\n0.0\n 11\n420.0\n 21\n297.0\n";
    $dxf .= " 12\n0.0\n 22\n0.0\n 32\n0.0\n";
    $dxf .= " 14\n1e+20\n 24\n1e+20\n 34\n1e+20\n";
    $dxf .= " 15\n-1e+20\n 25\n-1e+20\n 35\n-1e+20\n";
    $dxf .= "146\n0.0\n";
    $dxf .= " 13\n0.0\n 23\n0.0\n 33\n0.0\n";
    $dxf .= " 16\n1.0\n 26\n0.0\n 36\n0.0\n";
    $dxf .= " 17\n0.0\n 27\n1.0\n 37\n0.0\n";
    $dxf .= " 76\n1\n330\n17\n";

    // Paper layout
    $dxf .= "  0\nLAYOUT\n  5\n1E\n330\nC\n100\nAcDbPlotSettings\n";
    $dxf .= "  1\n\n  4\nA3\n  6\n\n";
    $dxf .= " 40\n7.5\n 41\n20.0\n 42\n7.5\n 43\n20.0\n";
    $dxf .= " 44\n420.0\n 45\n297.0\n";
    $dxf .= " 46\n0.0\n 47\n0.0\n 48\n0.0\n 49\n0.0\n";
    $dxf .= "140\n0.0\n141\n0.0\n142\n1.0\n143\n1.0\n";
    $dxf .= " 70\n0\n 72\n1\n 73\n0\n 74\n5\n  7\n\n 75\n16\n 76\n0\n 77\n2\n 78\n300\n";
    $dxf .= "147\n1.0\n148\n0.0\n149\n0.0\n";
    $dxf .= "100\nAcDbLayout\n  1\nLayout1\n 70\n1\n 71\n1\n";
    $dxf .= " 10\n0.0\n 20\n0.0\n 11\n420.0\n 21\n297.0\n";
    $dxf .= " 12\n0.0\n 22\n0.0\n 32\n0.0\n";
    $dxf .= " 14\n1e+20\n 24\n1e+20\n 34\n1e+20\n";
    $dxf .= " 15\n-1e+20\n 25\n-1e+20\n 35\n-1e+20\n";
    $dxf .= "146\n0.0\n";
    $dxf .= " 13\n0.0\n 23\n0.0\n 33\n0.0\n";
    $dxf .= " 16\n1.0\n 26\n0.0\n 36\n0.0\n";
    $dxf .= " 17\n0.0\n 27\n1.0\n 37\n0.0\n";
    $dxf .= " 76\n1\n330\n1B\n";

    $dxf .= "  0\nENDSEC\n";

    // ===== EOF =====
    $dxf .= "  0\nEOF\n";

    return $dxf;
}
