<?php
// Exécution silencieuse de la mise à jour du devis
$json_file = 'devis.json';
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
    if ($data !== null) {
        if (isset($data['DevisInfo'])) {
            $data['DevisInfo']['DateDevis'] = date('Y-m-d');
        } else {
            $data['DevisInfo'] = [
                'DateDevis' => date('Y-m-d'),
            ];
        }
        $timestamp = date('YmdHis');
        $data['DevisInfo']['NumeroDevis'] = 'DEV-' . $timestamp;
        file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#024F82">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        /* Tous les styles CSS restent identiques */
        :root {
            --primary-color: #024F82;
            --secondary-color: #28a745;
            --danger-color: #dc3545;
            --background-color: #f8f9fa;
            --text-color: #333;
            --card-bg: #ffffff;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --spacing-unit: clamp(0.5rem, 2vw, 1rem);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            touch-action: manipulation;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 30px;
            font-size: 2rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .output {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            background-color: var(--card-bg);
            border: 2px solid var(--primary-color);
            border-radius: var(--border-radius);
            padding: 15px;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            word-break: break-all;
            box-shadow: var(--box-shadow);
        }

        .chain-content {
            flex-grow: 1;
            margin-right: 15px;
            cursor: text;
            user-select: none;
            padding: 10px;
        }

        .chain-char {
            display: inline-block;
            padding: 0 1px;
            transition: background-color 0.2s;
        }

        .chain-char:hover {
            background-color: rgba(2, 79, 130, 0.1);
        }

        .vh-pattern-0 { color: #FF7F7F; font-weight: bold; }
        .vh-pattern-1 { color: #7FFF7F; font-weight: bold; }
        .vh-pattern-2 { color: #7F7FFF; font-weight: bold; }
        .vh-pattern-3 { color: #FFB27F; font-weight: bold; }
        .vh-pattern-4 { color: #7FFFB2; font-weight: bold; }
        .vh-pattern-5 { color: #B27FFF; font-weight: bold; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
        }

        .btn-primary {
            background: var(--primary-color);
        }

        .btn-success {
            background: var(--secondary-color);
        }

        .btn-danger {
            background: var(--danger-color);
        }

        .paste-section {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
            align-items: center;
        }

        #pasteInput {
            flex-grow: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            box-shadow: var(--box-shadow);
        }

        .controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .navigation-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .button-sections {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .button-section {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 15px;
        }

        .flex-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 8px;
        }

        .button-grid button {
            background-color: var(--card-bg);
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            min-height: 40px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .button-grid button:hover {
            background-color: var(--primary-color);
            color: white;
        }

        #numbers {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .furniture-section {
            margin-top: 20px;
        }

        .furniture-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .furniture-item {
            border-radius: var(--border-radius);
            overflow: hidden;
            aspect-ratio: 1;
            box-shadow: var(--box-shadow);
        }

        .furniture-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .history-section {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 15px;
            margin-top: 20px;
        }

        .history-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .history-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .history-item:hover {
            background-color: rgba(2, 79, 130, 0.1);
        }

        .cursor {
            color: var(--primary-color);
            font-weight: bold;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .structure-visualization {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 15px;
            margin: 15px 0;
            box-shadow: var(--box-shadow);
        }

        .structure-line {
            padding: 4px 0;
            transition: background-color 0.2s;
        }

        .structure-line:hover {
            background-color: rgba(2, 79, 130, 0.1);
        }

        @media screen and (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .flex-section {
                grid-template-columns: 1fr;
            }

            .controls {
                grid-template-columns: 1fr;
            }

            .furniture-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Configurateur de Meuble</h1>
        <form method="post" action="02.php">
            <div class="output" id="output">
                <div class="chain-content"><span class="cursor">|</span></div>
                <button type="button" onclick="execute()" class="btn btn-success">OK</button>
            </div>
            
            <div class="paste-section">
                <input type="text" id="pasteInput" placeholder="Envoyez votre chaîne par ici">
                <button type="button" onclick="pasteChain()" class="btn btn-primary">Envoyer</button>
            </div>
            
            <div class="controls">
                <button type="button" class="btn btn-danger" onclick="clearChain()">Effacer tout</button>
                <button type="button" class="btn btn-danger" onclick="removeLastChar()">Supprimer</button>
                <div class="navigation-group">
                    <button type="button" class="btn btn-primary" onclick="moveCursorLeft()">&lt;</button>
                    <button id="toggle-structure" class="btn btn-primary" type="button" onclick="toggleStructure()">
                        Afficher l'indentation
                    </button>
                    <button type="button" class="btn btn-primary" onclick="moveCursorRight()">&gt;</button>
                </div>
            </div>

            <div class="structure-visualization" id="structure-viz" style="display: none;">
                <div id="structure-content"></div>
            </div>

            <input type="hidden" id="inputString" name="inputString" value="">
            
            <div id="other-buttons" class="button-sections">
                <div class="button-section">
                    <div class="button-grid" id="letters"></div>
                </div>
                <div class="flex-section">
                    <div class="button-section">
                        <div class="button-grid" id="symbols"></div>
                    </div>
                    <div class="button-section">
                        <div id="numbers"></div>
                    </div>
                </div>
            </div>

            <div class="button-section furniture-section">
                <h2 class="section-title">Aperçu des Meubles</h2>
                <div class="furniture-grid">
                    <div class="furniture-item">
                        <img src="meuble1.png" alt="Meuble 1">
                    </div>
                    <div class="furniture-item">
                        <img src="meuble2.png" alt="Meuble 2">
                    </div>
                    <div class="furniture-item">
                        <img src="meuble3.png" alt="Meuble 3">
                    </div>
                </div>
            </div>

            <div class="history-section">
                <div class="history-title">Historique des 5 derniers codes</div>
                <div id="history-list"></div>
            </div>
        </form>
    </div>

<script>
    let history = JSON.parse(localStorage.getItem('chainHistory') || '[]');
    const letters = ['M', 'C', 'E', 'R', 'P', 'V', 'H', 'T', 'D', 'S', 'F', 'I', 'd', 'g', 'h', 'b'];
    const symbols = ['(', ')', '[', ']', ','];
    const numbers = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'];

    let chain = '';
    let cursorPos = 0;

    function updateOutput() {
        const chars = [];
        const coloredRanges = new Map();
        
        const pattern = /(?:^|,|\()((?:[a-zA-Z]*[VvHhPpSsIi]\[\d+(?:,\d+)*\]))/g;
        let match;
        let colorIndex = 0;
        let lastIndex = 0;
        
        while ((match = pattern.exec(chain)) !== null) {
            const fullMatch = match[1];
            const start = match.index + (match[0].length - fullMatch.length);
            const end = start + fullMatch.length;
            
            for (let i = start; i < end; i++) {
                coloredRanges.set(i, `vh-pattern-${colorIndex % 6}`);
            }
            colorIndex++;
            lastIndex = pattern.lastIndex;
        }
        
        for (let i = 0; i < chain.length; i++) {
            const char = chain[i];
            let spanClass = 'chain-char';
            
            if (coloredRanges.has(i)) {
                spanClass += ` ${coloredRanges.get(i)}`;
            }
            
            chars.push(`<span class="${spanClass}" data-pos="${i}">${char}</span>`);
        }

        chars.splice(cursorPos, 0, '<span class="cursor">|</span>');

        document.getElementById('output').innerHTML = 
            `<div class="chain-content" onclick="handleChainClick(event)">${chars.join('')}</div>
             <button type="button" onclick="execute()" class="btn btn-success">OK</button>`;
        
        document.getElementById('inputString').value = chain;

        if (document.getElementById('structure-viz').style.display !== 'none') {
            document.getElementById('structure-content').innerHTML = generateStructureHtml(chain);
        }
    }
    
    function execute() {
        document.getElementById('inputString').value = chain;
        
        let history = JSON.parse(localStorage.getItem('chainHistory') || '[]');
        if (chain && !history.includes(chain)) {
            history.unshift(chain);
            history = history.slice(0, 5);
            localStorage.setItem('chainHistory', JSON.stringify(history));
            updateHistory();
        }
        
        document.querySelector('form').submit();
    }

    function pasteChain() {
        const pasteInput = document.getElementById('pasteInput');
        const newText = pasteInput.value.trim();
        
        if (newText) {
            chain = chain.slice(0, cursorPos) + newText + chain.slice(cursorPos);
            cursorPos += newText.length;
            updateOutput();
            pasteInput.value = '';
        }
    }

    function clearChain() {
        chain = '';
        cursorPos = 0;
        updateOutput();
    }

    function handleChainClick(event) {
        if (!event.target.classList.contains('chain-char')) {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX - rect.left;
            
            const chars = event.currentTarget.querySelectorAll('.chain-char');
            let minDistance = Infinity;
            let closestPos = 0;

            chars.forEach(char => {
                const charRect = char.getBoundingClientRect();
                const charCenter = charRect.left + charRect.width / 2;
                const distance = Math.abs(event.clientX - charCenter);
                
                if (distance < minDistance) {
                    minDistance = distance;
                    closestPos = parseInt(char.dataset.pos);
                }
            });

            cursorPos = closestPos;
            if (event.clientX > rect.left + rect.width - 10) {
                cursorPos = chain.length;
            }
        } else {
            cursorPos = parseInt(event.target.dataset.pos);
        }
        
        updateOutput();
    }

    function createButton(char, container) {
        const button = document.createElement('button');
        button.textContent = char;
        button.onclick = (e) => {
            e.preventDefault();
            addToChain(char);
        };
        container.appendChild(button);
    }

    function initializeButtons() {
        const lettersContainer = document.getElementById('letters');
        const symbolsContainer = document.getElementById('symbols');
        const numbersContainer = document.getElementById('numbers');

        letters.forEach(char => createButton(char, lettersContainer));
        symbols.forEach(char => createButton(char, symbolsContainer));
        numbers.forEach(num => createButton(num, numbersContainer));
    }

    function updateHistory() {
        const historyList = document.getElementById('history-list');
        const history = JSON.parse(localStorage.getItem('chainHistory') || '[]');
        historyList.innerHTML = '';
        
        history.slice(0, 5).forEach(item => {
            const div = document.createElement('div');
            div.className = 'history-item';
            div.textContent = item;
            div.onclick = () => {
                chain = item;
                cursorPos = item.length;
                updateOutput();
            };
            historyList.appendChild(div);
        });
    }

    function addToChain(char) {
        const pairs = {
            '(': ')',
            '[': ']'
        };
        
        if (pairs[char]) {
            chain = chain.slice(0, cursorPos) + char + pairs[char] + chain.slice(cursorPos);
            cursorPos++;
        } else {
            chain = chain.slice(0, cursorPos) + char + chain.slice(cursorPos);
            cursorPos++;
        }
        updateOutput();
    }

    function removeLastChar() {
        if (cursorPos > 0) {
            chain = chain.slice(0, cursorPos - 1) + chain.slice(cursorPos);
            cursorPos--;
            updateOutput();
        }
    }

    function moveCursorLeft() {
        if (cursorPos > 0) {
            cursorPos--;
            updateOutput();
        }
    }

    function moveCursorRight() {
        if (cursorPos < chain.length) {
            cursorPos++;
            updateOutput();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initializeButtons();
        updateOutput();
        updateHistory();
    });
</script>
</body>
</html>
