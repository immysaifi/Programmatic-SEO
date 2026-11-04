<?php
// Bulletproof Session Starter & Security Tokens
@session_start();

if(isset($_GET['ref'])) { $_SESSION['referred_by_code'] = $_GET['ref']; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: " . strtok($_SERVER['REQUEST_URI'], '?')); exit; }

if (empty($_SESSION['bukkry_bulk_token'])) { $_SESSION['bukkry_bulk_token'] = md5(time() . rand()); }
if (empty($_SESSION['bukkry_cipher_key'])) { $_SESSION['bukkry_cipher_key'] = substr(md5(time() . rand()), 0, 16); }
if (empty($_SESSION['phantom_engine_token'])) { $_SESSION['phantom_engine_token'] = bin2hex(random_bytes(32)); } 

// Force PHP to save the session instantly
session_write_close();

// Extreme Security Headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("X-Frame-Options: DENY"); 
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: no-referrer");
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <link rel="icon" type="image/png" href="https://ai.bukkry.in/image/AI-Bukkry-Logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no, maximum-scale=1.0, user-scalable=no">
    <title>AI Programmatic SEO | Bukkry</title>
    
    <meta name="csrf-token" content="<?php echo $_SESSION['bukkry_bulk_token']; ?>">
    <meta name="cipher-token" content="<?php echo $_SESSION['bukkry_cipher_key']; ?>">
    <meta name="phantom-token" content="<?php echo $_SESSION['phantom_engine_token']; ?>">
    
    <!-- 🚫 ABSOLUTE ANTI-THEFT & ANTI-INSPECT 🚫 -->
    <script>
        document.onkeydown = function(e) {
            if (e.ctrlKey && (e.key === 'u' || e.key === 'U')) { e.preventDefault(); return false; }
            if (e.ctrlKey && (e.key === 's' || e.key === 'S')) { e.preventDefault(); return false; }
            if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) { e.preventDefault(); return false; }
            if (e.key === 'F12') { e.preventDefault(); return false; }
        };
        document.oncontextmenu = function(e) { e.preventDefault(); return false; };
        
        setInterval(function() {
            let before = new Date().getTime();
            debugger;
            let after = new Date().getTime();
            if (after - before > 100) {
                document.body.innerHTML = "<div style='text-align:center; margin-top:20%; font-family:sans-serif; color:red;'><h1>Security Violation Detected</h1><p>Inspection tools are strictly prohibited.</p></div>";
                window.location.replace("about:blank");
            }
        }, 50);

        console.log = function() {}; console.warn = function() {}; console.error = function() {};
    </script>

    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    
    <style>
        body { background-color: #f8fafc; color: #0f172a; -webkit-user-select: none; user-select: none; display: flex; flex-direction: column; min-height: 100vh; }
        .glass-ui { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .font-code { font-family: 'Courier New', Courier, monospace; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
        .mask-edges { -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent); mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent); }
        .interactive { pointer-events: auto !important; }
        img, svg { -webkit-user-drag: none; pointer-events: none; }
        textarea, input { pointer-events: auto !important; user-select: text !important; -webkit-user-select: text !important; }
    </style>
</head>
<body class="bg-slate-50 antialiased selection:bg-indigo-200 selection:text-indigo-900 flex flex-col min-h-screen text-slate-800">
    <?php include 'navbar.php'; ?> 
    <!-- EMPTY MOUNT DIV: Protects code from Ctrl+U -->
    <div id="secure-app-mount" class="flex-grow flex flex-col w-full h-full relative z-10">
        <div class="flex-grow flex items-center justify-center w-full h-full mt-20">
            <div class="text-center">
                <i class="fa-solid fa-circle-notch fa-spin text-4xl text-indigo-600 mb-4"></i>
                <h1 class="text-xl font-extrabold text-slate-900 animate-pulse">Initializing Secure Engine...</h1>
                <p id="loading-status" class="text-xs text-slate-500 mt-2 font-mono">Syncing components...</p>
            </div>
        </div>
    </div>

    <!-- 🔥 BULLETPROOF OBFUSCATED ENGINE LOADER 🔥 -->
    <script>
        document.addEventListener("DOMContentLoaded", async function() {
            const token = document.querySelector('meta[name="phantom-token"]').getAttribute("content");
            try {
                const engineUrl = 'x-bulk-engine.php?t=' + new Date().getTime();
                const response = await fetch(engineUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Phantom-Token': token },
                    body: JSON.stringify({ action: 'load_engine', phantom_token: token })
                });
                
                if (!response.ok) {
                    if(response.status === 404) throw new Error("File 'x-bulk-engine.php' not found (404). Ensure both files are uploaded correctly.");
                    throw new Error("HTTP Status " + response.status);
                }
                
                const data = await response.json();
                
                if(data.success) {
                    document.getElementById('loading-status').innerText = "Decrypting payload...";
                    const decodeUTF8Base64 = (b64) => {
                        const binString = window.atob(b64);
                        const bytes = new Uint8Array(binString.length);
                        for (let i = 0; i < binString.length; i++) { bytes[i] = binString.charCodeAt(i); }
                        return new TextDecoder('utf-8').decode(bytes);
                    };
                    const reverseStr = str => str.split('').reverse().join('');
                    
                    const decodedHTML = decodeUTF8Base64(reverseStr(data._x_h));
                    const decodedJS = decodeUTF8Base64(reverseStr(data._x_j));
                    
                    document.getElementById('secure-app-mount').innerHTML = decodedHTML;
                    
                    try {
                        const secureFunction = new Function(decodedJS);
                        secureFunction();
                    } catch(jsErr) {
                        document.getElementById('secure-app-mount').innerHTML = `<div class="text-center text-rose-600 font-bold mt-20">Script Rendering Error: ${jsErr.message}</div>`;
                    }
                } else {
                    document.getElementById('secure-app-mount').innerHTML = `<div class="text-center text-rose-600 font-bold mt-20">Security Access Denied: ${data.msg}</div>`;
                }
            } catch (e) {
                document.getElementById('secure-app-mount').innerHTML = `<div class="text-center text-rose-600 font-bold mt-20">Engine Initialization Error: ${e.message}<br><span class="text-xs text-slate-500">Please check your internet connection or reload the page.</span></div>`;
            }
        });
    </script>
</body>
</html>