







<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Model Viewer</title>
    <script type="module" src="https://unpkg.com/@google/model-viewer@1.12.0/dist/model-viewer.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }
        model-viewer {
            background-color: #cccccc;
            width: 100%;
            max-width: 600px;
            height: 400px;
            margin: 20px auto;
            border: 1px solid #ccc;
            border-radius: 10px;
        }
        .download-button {
            display: inline-block;
            margin: 20px;
            padding: 10px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #007BFF;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        .button-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            width: 100%;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .button-container a, .button-container button {
            text-decoration: none;
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
            min-width: 150px;
        }
        .button-container a:hover, .button-container button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>3D Model Viewer</h1>

    <!-- Display meuble.glb -->
    <h2>Meuble</h2>
    <model-viewer 
        id="model-viewer-1" 
        src="meuble.glb" 
        alt="Visualisation du modèle Meuble" 
        ar 
        ar-modes="scene-viewer webxr quick-look" 
        environment-image="neutral" 
        auto-rotate 
        camera-controls 
        exposure="3">
    </model-viewer>

    <!-- Display meublep.glb -->
    <h2>Meuble P</h2>
    <model-viewer 
        id="model-viewer-2" 
        src="meublep.glb" 
        alt="Visualisation du modèle Meuble P" 
        ar 
        ar-modes="scene-viewer webxr quick-look" 
        environment-image="neutral" 
        auto-rotate 
        camera-controls 
        exposure="3">
    </model-viewer>

    <!-- Display meublen.glb -->
    <h2>Meuble N</h2>
    <model-viewer 
        id="model-viewer-3" 
        src="meublen.glb" 
        alt="Visualisation du modèle Meuble N" 
        ar 
        ar-modes="scene-viewer webxr quick-look" 
        environment-image="neutral" 
        auto-rotate 
        camera-controls 
        exposure="3">
    </model-viewer>

    <!-- Display meublenp.glb -->
    <h2>Meuble NP</h2>
    <model-viewer 
        id="model-viewer-4" 
        src="meublenp.glb" 
        alt="Visualisation du modèle Meuble NP" 
        ar 
        ar-modes="scene-viewer webxr quick-look" 
        environment-image="neutral" 
        auto-rotate 
        camera-controls 
        exposure="3">
    </model-viewer>

    <!-- Button to download all files -->
<div class="button-container">
    <a href="./pieces/piece_general.dxf" class="download-link" download>Télécharger DXF</a>
    <a href="./pieces/piece_general.svg" class="download-link" download>Télécharger SVG</a>
    <a href="./meublep.glb" class="download-link" download>Télécharger GLB</a>
    <a href="./SVGnest/index.html">Aller au nesting</a>
</div>
</body>
</html>