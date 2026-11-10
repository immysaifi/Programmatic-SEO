<?php
// ==============================================================
// x-bulk-engine.php
// AI Programmatic SEO Pages
// HYBRID PHANTOM ENGINE: Provides Encrypted Payload AND Handles Generation API
// ==============================================================
@session_start();
error_reporting(0);
@ini_set('memory_limit', '-1'); 
@set_time_limit(0); 

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Phantom-Token, Authorization");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Frame-Options: DENY"); 
header("X-Content-Type-Options: nosniff");

// 🔥 Handle Preflight OPTIONS request 🔥
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php'; 

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);
if (!$data) $data = $_POST;

$action = $data['action'] ?? '';

function xorDecrypt($base64Data, $key) {
    $text = base64_decode($base64Data); $result = '';
    for ($i = 0; $i < strlen($text); $i++) { $result .= $text[$i] ^ $key[$i % strlen($key)]; }
    return $result;
}

// ==========================================
// SCENARIO 1: ACTUAL PROGRAMMATIC SEO GENERATOR EXECUTION
// ==========================================
if (isset($data['payload'])) {
    
    if (empty($_SESSION['user_id'])) {
        ob_end_clean(); echo json_encode(['success' => false, 'msg' => 'Unauthorized. Please log in first.']); exit;
    }

    $user_id = $_SESSION['user_id'];
    $cost_per_run = 100; // 100 credits per run

    // Fetch User Plan & Credits
    $stmt = $pdo->prepare("SELECT credits, plan_type FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user['plan_type'] === 'free' || $user['plan_type'] === 'payg') {
        if ($user['credits'] < $cost_per_run) {
            ob_end_clean(); echo json_encode(['success' => false, 'error_code' => 'OUT_OF_CREDITS', 'msg' => 'Insufficient credits.']); exit;
        }
        $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")->execute([$cost_per_run, $user_id]);
        $_SESSION['credits'] = $user['credits'] - $cost_per_run; 
    }

    $cipherKey  = $_SESSION['bukkry_cipher_key'] ?? null;
    $csrfToken  = $_SESSION['bukkry_bulk_token'] ?? null;

    if (!$cipherKey || !isset($data['token']) || $data['token'] !== $csrfToken) {
        ob_end_clean(); echo json_encode(['success' => false, 'msg' => 'Access Denied: Cipher Mismatch']); exit;
    }

    $decodedPayload = json_decode(xorDecrypt($data['payload'], $cipherKey), true);
    if (!$decodedPayload) {
        ob_end_clean(); echo json_encode(['success' => false, 'msg' => 'Invalid encrypted payload']); exit;
    }

    $rawList = trim($decodedPayload['csv']); 
    $templateRaw = $decodedPayload['template']; 
    $useSpintax = filter_var($decodedPayload['use_spintax'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $useSchema = filter_var($decodedPayload['use_schema'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $targetDomain = rtrim(filter_var($decodedPayload['domain'] ?? 'https://yourdomain.com', FILTER_SANITIZE_URL), '/') . '/';

    $lines = array_filter(explode("\n", str_replace(["\r\n", "\r", "\xEF\xBB\xBF"], "\n", $rawList)));
    $headers = ['Keyword'];
    $isMultiColumn = false;

    if (strpos(reset($lines), ',') !== false) {
        $isMultiColumn = true;
        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);
    }

    $parsedRows = []; 
    foreach ($lines as $index => $line) {
        if (empty(trim($line))) continue;
        
        $rowData = [];
        if ($isMultiColumn) {
            $cols = str_getcsv($line);
            foreach ($headers as $i => $h) {
                $rowData[$h] = isset($cols[$i]) ? trim($cols[$i]) : '';
            }
            $keyword = $rowData[$headers[0]] ?? ''; 
        } else {
            $keyword = trim($line, " \t\n\r\0\x0B\",");
            $rowData['Keyword'] = $keyword;
        }
        
        if (empty($keyword) || strtolower($keyword) === 'keyword') continue;
        
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $keyword), '-'));
        $titlePrefixes = ["Top", "Best", "Expert", "Premium", "Professional"];
        $randomPrefix = $titlePrefixes[array_rand($titlePrefixes)];
        $pageUrl = $targetDomain . $slug; 
        
        $parsedRows[] = [
            'keyword' => $keyword,
            'data' => $rowData,
            '_slug' => $slug,
            '_pageUrl' => $pageUrl,
            '_metaTitle' => "{$randomPrefix} " . ucwords($keyword) . " | Verified",
            '_metaDesc' => "Looking for " . htmlspecialchars($keyword) . "? Our experts provide top-rated, highly professional services. Contact us today!"
        ];
    }

    $generatedPages = []; 
    $sitemapUrls = [];
    $imageSitemapUrls = []; 

    $currentMonth = date('F');
    $currentYear = date('Y');

    $htmlBranding = "<!-- Database generated by AI Programmatic SEO Pages Engine Bukkry -->\n<!-- Powered By Bukkry - Multimedia and Services -->\n<!-- https://bukkry.in -->\n\n";
    $sitemapBranding = "<!-- Sitemap generated by Sitemap Generator Bukkry -->\n<!-- Powered By Bukkry - Multimedia and Services -->\n<!-- https://bukkry.in -->\n";

    foreach ($parsedRows as $index => $pageData) {
        $placeholders = [];
        // 🔥 FIX: 100% Bulletproof Regex to protect ALL backend PHP processing blocks AND frontend scripts/styles from manipulation 🔥
        $processedText = preg_replace_callback('/(<\?php.*?\?>|<\?=.*?\?>|<\?.*?\?>|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>)/is', function($matches) use (&$placeholders) {
            $id = '@@BLOCK' . count($placeholders) . '@@';
            $placeholders[$id] = $matches[0];
            return $id;
        }, $templateRaw);

        $keyword = $pageData['keyword'];
        $pageUrl = $pageData['_pageUrl'];
        $pageImages = [];

        foreach ($pageData['data'] as $colName => $colValue) {
            $processedText = str_ireplace('[' . $colName . ']', $colValue, $processedText);
        }
        
        $processedText = str_ireplace('[Month]', $currentMonth, $processedText);
        $processedText = str_ireplace('[Year]', $currentYear, $processedText);

        $mapIframe = "<div style='width:100%; overflow:hidden; border-radius:8px;'><iframe width='100%' height='400' frameborder='0' src='https://maps.google.com/maps?q=".urlencode($keyword)."&output=embed' loading='lazy'></iframe></div>";
        $ytIframe = "<div style='width:100%; overflow:hidden; border-radius:8px;'><iframe width='100%' height='400' src='https://www.youtube.com/embed?listType=search&list=".urlencode($keyword)."' frameborder='0' loading='lazy'></iframe></div>";
        $processedText = str_ireplace('[Map]', $mapIframe, $processedText);
        $processedText = str_ireplace('[YouTube]', $ytIframe, $processedText);

        if ($useSpintax) {
            $processedText = preg_replace_callback('/\{\{([^}]+)\}\}/', function($m) use ($index) {
                $p = explode('|', $m[1]); return $p[$index % count($p)];
            }, $processedText);
            $processedText = preg_replace_callback('/\{([^}]+)\}/', function($m) {
                $p = explode('|', $m[1]); return $p[array_rand($p)];
            }, $processedText);
        }

        $optimizedH1 = ucwords($keyword);
        $processedText = preg_replace('/(<h1[^>]*>).*?(<\/h1>)/is', '$1' . $optimizedH1 . '$2', $processedText, 1);

        $processedText = preg_replace_callback('/<img\s+([^>]+)>/i', function($m) use ($keyword, &$pageImages, $targetDomain) {
            $attrs = $m[1];
            $safeKw = htmlspecialchars(ucwords($keyword), ENT_QUOTES, 'UTF-8');
            if (!preg_match('/alt\s*=\s*["\']/i', $attrs)) $attrs .= ' alt="' . $safeKw . '"';
            if (!preg_match('/title\s*=\s*["\']/i', $attrs)) $attrs .= ' title="' . $safeKw . '"';
            if (!preg_match('/data-caption\s*=\s*["\']/i', $attrs)) $attrs .= ' data-caption="' . $safeKw . ' Services"';
            if (!preg_match('/meta-description\s*=\s*["\']/i', $attrs)) $attrs .= ' meta-description="Top quality ' . $safeKw . '"';
            if (!preg_match('/loading\s*=\s*["\']/i', $attrs)) $attrs .= ' loading="lazy" decoding="async"';
            
            if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $attrs, $srcMatch)) {
                $imgUrl = $srcMatch[1];
                if (strpos($imgUrl, 'http') === false) $imgUrl = $targetDomain . ltrim($imgUrl, '/');
                $pageImages[] = ['loc' => $imgUrl, 'title' => $safeKw];
            }
            return '<img ' . trim($attrs) . '>';
        }, $processedText);

        $processedText = preg_replace_callback('/(<p[^>]*>)(.*?)(<\/p>)/is', function($m) {
            $openTag = $m[1]; $content = $m[2]; $closeTag = $m[3];
            if (strpos($content, 'quality') !== false) {
                $content = preg_replace('/\bquality\b/i', '<a href="https://en.wikipedia.org/wiki/Quality_(business)" target="_blank" rel="nofollow" style="color:inherit; text-decoration:none;">quality</a>', $content, 1);
            } elseif (strpos($content, 'expert') !== false) {
                $content = preg_replace('/\bexpert\b/i', '<a href="https://en.wikipedia.org/wiki/Expert" target="_blank" rel="nofollow" style="color:inherit; text-decoration:none;">expert</a>', $content, 1);
            }
            return $openTag . $content . $closeTag;
        }, $processedText);

        $cityForGeo = isset($pageData['data']['City']) && !empty($pageData['data']['City']) ? htmlspecialchars($pageData['data']['City'], ENT_QUOTES) : 'India';
        $safeTitle = htmlspecialchars($pageData['_metaTitle'], ENT_QUOTES);
        $safeDesc = htmlspecialchars($pageData['_metaDesc'], ENT_QUOTES);
        
        $seoHeadTags = "<title>{$safeTitle}</title><meta name=\"description\" content=\"{$safeDesc}\"><link rel=\"canonical\" href=\"{$pageUrl}\"><meta property=\"og:title\" content=\"{$safeTitle}\"><meta property=\"og:description\" content=\"{$safeDesc}\"><meta property=\"og:url\" content=\"{$pageUrl}\"><meta property=\"og:type\" content=\"website\"><meta name=\"twitter:card\" content=\"summary_large_image\"><meta name=\"twitter:title\" content=\"{$safeTitle}\"><meta name=\"twitter:description\" content=\"{$safeDesc}\"><meta name=\"geo.region\" content=\"IN\"><meta name=\"geo.placename\" content=\"{$cityForGeo}\">";

        $processedText = preg_replace('/<title>.*?<\/title>/is', '', $processedText);
        if (stripos($processedText, '<head>') !== false) {
            $processedText = str_ireplace('<head>', "<head>\n" . $seoHeadTags, $processedText);
        }

        $schemaJsonStr = "";
        if ($useSchema) {
            $schemas = [];
            $schemas[] = ["@context" => "https://schema.org", "@type" => "Service", "name" => $keyword, "url" => $pageUrl, "areaServed" => $cityForGeo, "provider" => ["@type" => "Organization", "name" => "Top {$keyword} Experts"], "mainEntityOfPage" => $pageUrl, "mentions" => ["@type" => "Thing", "name" => $keyword, "sameAs" => "https://en.wikipedia.org/wiki/Special:Search?search=".urlencode($keyword)]];
            $schemas[] = ["@context" => "https://schema.org", "@type" => "Product", "name" => $keyword . " Services", "aggregateRating" => ["@type" => "AggregateRating", "ratingValue" => (string)(mt_rand(47, 49) / 10), "reviewCount" => (string)mt_rand(150, 600), "bestRating" => "5"]];
            $schemas[] = ["@context" => "https://schema.org", "@type" => "BreadcrumbList", "itemListElement" => [["@type" => "ListItem", "position" => 1, "name" => "Home", "item" => rtrim($targetDomain, '/')], ["@type" => "ListItem", "position" => 2, "name" => "Services", "item" => rtrim($targetDomain, '/') . "/services"], ["@type" => "ListItem", "position" => 3, "name" => $keyword, "item" => $pageUrl]]];
            $schemaJsonStr = "<script type=\"application/ld+json\">\n" . json_encode($schemas, JSON_UNESCAPED_SLASHES) . "\n</script>";
        }

        $totalRows = count($parsedRows);
        $siloHtml = "";
        if ($totalRows > 1) {
            $siloHtml = "\n<!-- Dynamic Semantic Silo -->\n<nav aria-label='Related Services' style='position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0;'>\n<ul>\n";
            $nextIdx = ($index + 1) % $totalRows;
            $nextPage = $parsedRows[$nextIdx];
            $siloHtml .= "  <li><a href='{$nextPage['_pageUrl']}'>{$nextPage['keyword']}</a></li>\n";

            if ($totalRows > 2) {
                $crossLinkCount = min(3, $totalRows - 1);
                $randomKeys = (array) array_rand($parsedRows, $totalRows);
                $added = 0;
                foreach ($randomKeys as $rk) {
                    if ($rk !== $index && $rk !== $nextIdx) {
                        $rp = $parsedRows[$rk];
                        $siloHtml .= "  <li><a href='{$rp['_pageUrl']}'>{$rp['keyword']}</a></li>\n";
                        $added++;
                        if ($added >= $crossLinkCount) break;
                    }
                }
            }
            $siloHtml .= "</ul>\n</nav>\n";
        }

        $randomHash = substr(md5(mt_rand()), 0, 10);
        $finalInjection = $schemaJsonStr . "\n<!-- render_node_x{$randomHash} -->\n" . $siloHtml;
        
        if (stripos($processedText, '</body>') !== false) {
            $processedText = str_ireplace('</body>', $finalInjection . "\n</body>", $processedText);
        } else {
            $processedText .= $finalInjection;
        }

        // Restore protected blocks (including your CRM PHP logic securely)
        foreach ($placeholders as $id => $content) {
            $processedText = str_replace($id, $content, $processedText);
        }

        $processedText = preg_replace('/>\s+</', '><', $processedText);
        $processedText = trim(preg_replace('/\t+/', '', $processedText));
        $processedText = $htmlBranding . $processedText;

        $sitemapUrls[] = $pageUrl;
        if (!empty($pageImages)) { $imageSitemapUrls[$pageUrl] = $pageImages; }
        $generatedPages[] = ['id' => $index + 1, 'slug' => $pageData['_slug'], 'content' => $processedText, 'schema' => ''];
    }

    $sitemapXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $sitemapBranding . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($sitemapUrls as $url) { $sitemapXml .= "  <url>\n    <loc>" . htmlspecialchars($url) . "</loc>\n    <changefreq>daily</changefreq>\n  </url>\n"; }
    $sitemapXml .= "</urlset>";

    $imgSitemapXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $sitemapBranding . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";
    foreach ($imageSitemapUrls as $pageLoc => $images) {
        $imgSitemapXml .= "  <url>\n    <loc>" . htmlspecialchars($pageLoc) . "</loc>\n";
        foreach ($images as $img) { $imgSitemapXml .= "    <image:image>\n      <image:loc>" . htmlspecialchars($img['loc']) . "</image:loc>\n      <image:title>" . htmlspecialchars($img['title']) . "</image:title>\n    </image:image>\n"; }
        $imgSitemapXml .= "  </url>\n";
    }
    $imgSitemapXml .= "</urlset>";

    $pingUrl = "http://www.google.com/ping?sitemap=" . urlencode($targetDomain . "sitemap.xml");
    @file_get_contents($pingUrl, false, stream_context_create(['http' => ['timeout' => 1]])); 

    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'total_generated' => count($generatedPages), 
        'pages' => $generatedPages, 
        'sitemap' => $sitemapXml,
        'image_sitemap' => $imgSitemapXml
    ]);
    exit;
}

// ==========================================
// SCENARIO 2: DELIVERING OBFUSCATED FRONTEND PAYLOAD
// ==========================================
if ($action === 'load_engine' || empty($action)) {
    
    $received_token = '';
    if (isset($_SERVER['HTTP_X_PHANTOM_TOKEN'])) {
        $received_token = $_SERVER['HTTP_X_PHANTOM_TOKEN'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-phantom-token') { $received_token = $value; break; }
        }
    }
    if (empty($received_token) && isset($data['phantom_token'])) {
        $received_token = $data['phantom_token'];
    }

    $valid_token = $_SESSION['phantom_engine_token'] ?? null;

    if (empty($received_token) || empty($valid_token) || $received_token !== $valid_token) {
        ob_end_clean();
        die(json_encode(["success" => false, "msg" => "E01: Security Violation. Invalid Token."]));
    }

    unset($_SESSION['phantom_engine_token']); 

    // --- 🟢 FULL HTML PAYLOAD 🟢 ---
    ob_start();
    
    @include __DIR__ . '/navbar.php'; // Navbar resides in Root
    ?>
    <nav class="fixed top-0 w-full z-50 glass-ui px-2 sm:px-6 py-3 border-b border-slate-200 shadow-sm interactive">
        <div class="max-w-[90rem] mx-auto flex justify-between items-center gap-1 sm:gap-2">
            <div class="text-xs sm:text-lg md:text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-1.5 sm:gap-2 truncate">
                <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-md sm:rounded-lg bg-indigo-600 flex items-center justify-center shadow-md shrink-0">
                    <i class="fa-solid fa-layer-group text-white text-[10px] sm:text-xs"></i>
                </div>
                
                <span class="truncate hidden xs:inline">AI Programmatic SEO <span class="text-indigo-600">Bukkry</span></span>
                <span class="truncate xs:hidden">AI Programmatic SEO <span class="text-indigo-600">Pages</span></span>
            </div>
             <div class="hidden lg:flex absolute left-1/2 -translate-x-1/2 items-center gap-6 text-sm font-semibold text-slate-500">
                 <a href="https://ai.bukkry.in/api.php" class="hover:text-brand-purple transition-colors">Developer API</a>
                 <a href="https://ai.bukkry.in/affiliate.php" class="hover:text-brand-purple transition-colors font-bold text-slate-700">Affiliate</a>
                 <a href="https://ai.bukkry.in/buy-google-reviews" class="hover:text-brand-purple transition-colors">Get Google Review</a>
                 <a href="javascript:void(0)" onclick="openPricingModal()" class="hover:text-brand-purple transition-colors cursor-pointer">Pricing</a>
            </div>
            
            <div class="flex items-center gap-1.5 sm:gap-4 shrink-0">
                <a href="/" class="text-[11px] sm:text-sm font-bold text-slate-600 hover:text-indigo-600 transition-colors flex items-center gap-1 bg-slate-100 sm:bg-transparent px-2.5 py-1.5 sm:px-0 sm:py-0 rounded-md sm:rounded-none shadow-sm sm:shadow-none shrink-0">
                    <i class="fa-solid fa-house"></i> <span class="hidden sm:inline">Home</span>
                </a>
                
                <div class="w-px h-4 sm:h-5 bg-slate-300 hidden sm:block"></div>

                <?php if(!empty($_SESSION['user_id'])): ?>
                    <span class="px-2 py-1.5 sm:px-3 sm:py-1.5 rounded-md text-[10px] sm:text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 flex items-center gap-1 shadow-sm shrink-0">
                        <i class="fa-solid fa-coins text-amber-500"></i> <span id="live-credits"><?php echo $_SESSION['credits'] ?? 0; ?></span>
                    </span>
                    <button onclick="window.openPricingModal()" class="text-[10px] sm:text-xs font-bold bg-gradient-to-r from-amber-400 to-orange-500 text-white px-2.5 py-1.5 sm:px-3 sm:py-1.5 rounded-md shadow hover:scale-105 transition-transform flex items-center gap-1 shrink-0">
                        <i class="fa-solid fa-crown"></i> <span class="hidden sm:inline">Upgrade</span>
                    </button>
                    <a href="?logout=true" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-600 px-2.5 py-1.5 sm:px-3 sm:py-1.5 rounded-md text-[10px] sm:text-xs font-bold transition-colors shrink-0 flex items-center justify-center shadow-sm">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <button onclick="window.openAuthModal()" class="text-[10px] sm:text-sm font-bold bg-slate-900 hover:bg-rose-600 text-white px-3 py-1.5 sm:px-4 sm:py-2 rounded-md sm:rounded-lg transition-all shadow-sm flex items-center gap-1.5 shrink-0">
                        <i class="fa-solid fa-user"></i> Login
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="relative z-10 pt-20 sm:pt-24 pb-12 max-w-[90rem] mx-auto px-3 sm:px-6 flex-grow w-full interactive">
        
        <div class="text-center mb-8 sm:mb-10 max-w-4xl mx-auto px-1">
            <div class="inline-flex justify-center mb-4 sm:mb-5">
                <span class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-[10px] sm:text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center gap-2 uppercase tracking-widest shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> <span class="hidden sm:inline">System Status:</span> Live & Active
                </span>
            </div>
            <h1 class="text-2xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 mb-3 tracking-tight leading-tight">
                Programmatic SEO <span class="text-indigo-600">Instant Viral</span> Pages
            </h1>
            <p class="text-slate-600 text-xs sm:text-sm md:text-base leading-relaxed">
                Upload your keyword list. The AI Engine will auto-generate unique URLs, Titles, Descriptions, and inject Enterprise-Grade SEO seamlessly into your code.
            </p>
        </div>

        <div class="mb-8 sm:mb-12 interactive">
            <div class="flex items-center justify-center gap-2 sm:gap-3 mb-5 sm:mb-6">
                <div class="h-px bg-slate-300 flex-grow max-w-[60px] sm:max-w-[100px]"></div>
                <h2 class="text-[10px] sm:text-xs uppercase tracking-widest font-black text-slate-400 text-center">God-Mode SEO Capabilities</h2>
                <div class="h-px bg-slate-300 flex-grow max-w-[60px] sm:max-w-[100px]"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5">
                <div class="bg-white p-3.5 sm:p-5 rounded-xl border border-slate-200 hover-lift flex gap-3 items-start"><div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-indigo-50 flex items-center justify-center shrink-0 mt-0.5"><i class="fa-solid fa-spider text-indigo-600 text-sm"></i></div><div><h4 class="text-xs sm:text-sm font-bold text-slate-900 mb-0.5">Invisible JSON-LD Schemas</h4><p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed">Auto-injects Fake Star Ratings, Breadcrumbs, FAQs, and Semantic Entities without altering frontend design.</p></div></div>
                <div class="bg-white p-3.5 sm:p-5 rounded-xl border border-slate-200 hover-lift flex gap-3 items-start"><div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-emerald-50 flex items-center justify-center shrink-0 mt-0.5"><i class="fa-solid fa-image text-emerald-600 text-sm"></i></div><div><h4 class="text-xs sm:text-sm font-bold text-slate-900 mb-0.5">Advanced Image SEO</h4><p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed">Automatically adds keyword-rich Alt, Title, Meta-Caption tags to every image, plus activates Lazy-Loading.</p></div></div>
                <div class="bg-white p-3.5 sm:p-5 rounded-xl border border-slate-200 hover-lift flex gap-3 items-start"><div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-rose-50 flex items-center justify-center shrink-0 mt-0.5"><i class="fa-solid fa-ghost text-rose-600 text-sm"></i></div><div><h4 class="text-xs sm:text-sm font-bold text-slate-900 mb-0.5">Anti-Footprint AI Bypass</h4><p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed">Randomizes HTML code structures and minifies code to bypass Google's duplicate template penalties.</p></div></div>
                <div class="bg-white p-3.5 sm:p-5 rounded-xl border border-slate-200 hover-lift flex gap-3 items-start"><div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-amber-50 flex items-center justify-center shrink-0 mt-0.5"><i class="fa-solid fa-link text-amber-600 text-sm"></i></div><div><h4 class="text-xs sm:text-sm font-bold text-slate-900 mb-0.5">Wikipedia-Style Interlinking</h4><p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed">Smartly links contextual words in paragraphs to high-authority outbound sources for massive Trust Signals.</p></div></div>
                <div class="bg-white p-3.5 sm:p-5 rounded-xl border border-slate-200 hover-lift flex gap-3 items-start"><div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-sky-50 flex items-center justify-center shrink-0 mt-0.5"><i class="fa-solid fa-map-location-dot text-sky-600 text-sm"></i></div><div><h4 class="text-xs sm:text-sm font-bold text-slate-900 mb-0.5">Local Geo-Tags Matrix</h4><p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed">Secures local rankings by injecting hidden Geo-Coordinates and Open Graph Social Meta Data.</p></div></div>
                <div class="bg-white p-3.5 sm:p-5 rounded-xl border border-slate-200 hover-lift flex gap-3 items-start"><div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-purple-50 flex items-center justify-center shrink-0 mt-0.5"><i class="fa-solid fa-file-zipper text-purple-600 text-sm"></i></div><div><h4 class="text-xs sm:text-sm font-bold text-slate-900 mb-0.5">Domain-Synced Triple Export</h4><p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed">Automatically generates a customized <b>Master JSON</b>, <b>XML Sitemap</b>, and <b>Image Sitemap</b> perfectly mapped to target domain.</p></div></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:gap-8 mb-6 sm:mb-8 interactive">
            <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="bg-slate-50 border-b border-slate-200 px-4 sm:px-6 py-3.5 flex justify-between items-center">
                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-900"><i class="fa-solid fa-cloud-arrow-up text-indigo-600 mr-1.5 sm:mr-2"></i> 1. Database Configuration</h3>
                </div>
                <div class="p-4 sm:p-6 flex flex-col gap-3.5 sm:gap-4">
                    <div>
                        <label class="block text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Target Live Domain/Subdomain</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><i class="fa-solid fa-globe text-slate-400 text-xs sm:text-sm"></i></div>
                            <input type="url" id="domain-input" value="https://interior.bukkry.in" class="w-full pl-9 sm:pl-10 pr-3 py-2.5 sm:py-3 bg-slate-50 border border-slate-200 rounded-lg sm:rounded-xl text-xs sm:text-sm font-code text-slate-700 focus:outline-none focus:border-indigo-500 transition-colors shadow-sm interactive" required>
                        </div>
                    </div>
                    <button id="download-sample-csv" class="w-full bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 hover:border-indigo-600 font-bold py-2.5 sm:py-3 px-3 rounded-lg sm:rounded-xl transition-all flex items-center justify-center gap-2 shadow-sm text-xs sm:text-sm text-center interactive">
                        <i class="fa-solid fa-file-csv text-base sm:text-lg"></i> Download Sample File
                    </button>
                    <div id="csv-dropzone" class="border-2 border-dashed border-slate-300 rounded-lg sm:rounded-xl bg-slate-50 hover:bg-indigo-50 hover:border-indigo-300 transition-colors cursor-pointer flex flex-col items-center justify-center p-3.5 text-center relative group interactive">
                        <input type="file" id="csv-file-input" accept=".csv, .txt" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10 interactive">
                        <i class="fa-solid fa-file-arrow-up text-xl sm:text-2xl text-slate-400 group-hover:text-indigo-600 mb-1 transition-colors"></i>
                        <p class="text-xs sm:text-sm font-bold text-slate-700">Drag & Drop .csv / .txt</p>
                    </div>
                    <div class="relative w-full">
                        <textarea id="csv-input" spellcheck="false" class="w-full h-44 sm:h-52 lg:h-64 p-3.5 bg-slate-50 border border-slate-200 rounded-lg sm:rounded-xl text-xs sm:text-sm font-code text-slate-700 focus:outline-none focus:border-indigo-500 transition-colors resize-none leading-relaxed shadow-sm interactive" placeholder="Paste your keywords here..."></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="bg-slate-50 border-b border-slate-200 px-4 sm:px-6 py-3.5 flex justify-between items-center">
                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-900"><i class="fa-solid fa-code text-emerald-500 mr-1.5 sm:mr-2"></i> 2. Exact Layout HTML Template</h3>
                </div>
                <div class="p-4 sm:p-6 flex flex-col relative flex-grow">
                    <p class="text-[11px] sm:text-xs text-slate-500 mb-2 leading-relaxed">
                        Use <span class="font-bold text-indigo-600">[Keyword]</span>, <span class="font-bold text-rose-500">[Month]</span>, <span class="font-bold text-rose-500">[Year]</span>, <span class="font-bold text-amber-500">[Map]</span> or <span class="font-bold text-amber-500">[YouTube]</span> where needed.
                    </p>
                    <textarea id="template-input" spellcheck="false" class="w-full h-64 sm:h-80 lg:h-full min-h-[220px] p-3.5 bg-[#0d1117] border border-slate-800 rounded-lg sm:rounded-xl text-xs sm:text-sm font-code text-emerald-400 focus:outline-none focus:border-indigo-500 transition-colors resize-none shadow-inner interactive" placeholder="<!DOCTYPE html>
<html>
<head>
    <!-- Engine will auto-inject dynamic Meta, OG & Geo Tags! -->
    <style> body { background: #fff; } </style>
</head>
<body>
    <h1>Welcome to [Keyword]</h1>
    <p>Updated for [Month] [Year]. We provide top quality service.</p>
    <div>[Map]</div>
</body>
</html>"></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 mb-8 flex justify-center interactive">
            <button id="generate-btn" class="w-full max-w-xl bg-slate-900 hover:bg-indigo-600 text-white font-black px-5 sm:px-10 py-3.5 sm:py-5 rounded-xl sm:rounded-2xl transition-all shadow-lg sm:shadow-xl flex items-center justify-center gap-2.5 text-sm sm:text-lg group interactive">
                Initiate Viral Scaler <i class="fa-solid fa-rocket group-hover:-translate-y-1 group-hover:translate-x-1 transition-transform"></i>
            </button>
        </div>

        <div id="terminal-section" class="w-full bg-[#0b0f19] border border-slate-800 rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-2xl mb-12 hidden interactive">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between mb-3 pb-3 border-b border-slate-800 gap-3">
                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <div class="w-2.5 h-2.5 rounded-full bg-rose-500 shrink-0"></div><div class="w-2.5 h-2.5 rounded-full bg-amber-400 shrink-0"></div><div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></div>
                    <span class="text-slate-500 text-[10px] sm:text-xs font-code ml-1 break-all">system-compiler:~ root$</span>
                </div>
                
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto interactive">
                    <div id="social-share-box" class="hidden flex items-center justify-center gap-3 bg-slate-800/80 px-3 py-1.5 rounded-lg border border-slate-700 shadow-inner">
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Share:</span>
                        <a href="#" onclick="window.shareSocial('wa', event)" class="text-slate-300 hover:text-emerald-500 transition-colors interactive"><i class="fa-brands fa-whatsapp"></i></a>
                        <a href="#" onclick="window.shareSocial('fb', event)" class="text-slate-300 hover:text-blue-500 transition-colors interactive"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#" onclick="window.shareSocial('tw', event)" class="text-slate-300 hover:text-sky-400 transition-colors interactive"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#" onclick="window.shareSocial('ln', event)" class="text-slate-300 hover:text-blue-600 transition-colors interactive"><i class="fa-brands fa-linkedin"></i></a>
                    </div>
                    
                    <div id="export-buttons-group" class="hidden flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                        <button id="download-json-btn" class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-400 text-white text-[10px] sm:text-[11px] font-bold px-3.5 py-2 rounded transition-colors uppercase tracking-widest shadow-lg flex items-center justify-center gap-1.5 interactive"><i class="fa-solid fa-database"></i> JSON DATA</button>
                        <button id="download-sitemap-btn" class="w-full sm:w-auto bg-indigo-500 hover:bg-indigo-400 text-white text-[10px] sm:text-[11px] font-bold px-3.5 py-2 rounded transition-colors uppercase tracking-widest shadow-lg flex items-center justify-center gap-1.5 interactive"><i class="fa-solid fa-sitemap"></i> SITEMAP</button>
                        <button id="download-img-sitemap-btn" class="w-full sm:w-auto bg-sky-500 hover:bg-sky-400 text-white text-[10px] sm:text-[11px] font-bold px-3.5 py-2 rounded transition-colors uppercase tracking-widest shadow-lg flex items-center justify-center gap-1.5 interactive"><i class="fa-solid fa-images"></i> IMG SITEMAP</button>
                        <button id="viral-share-btn" onclick="window.generateDeliverableLink()" class="w-full sm:w-auto bg-rose-600 hover:bg-rose-700 text-white text-[10px] sm:text-[11px] font-bold px-3.5 py-2 rounded transition-all uppercase tracking-widest shadow-lg hover:shadow-rose-500/30 flex items-center justify-center gap-1.5 no-print interactive"><i class="fa-solid fa-share-nodes"></i> SHARE REPORT</button>
                    </div>
                </div>
            </div>
            <div id="terminal-logs" class="flex flex-col gap-1.5 font-code text-xs sm:text-sm h-40 sm:h-48 overflow-y-auto interactive"></div>
        </div>

        <div id="how-to" class="mb-12 sm:mb-16 pt-4">
            <div class="flex items-center justify-center gap-2 sm:gap-3 mb-5 sm:mb-6">
                <div class="h-px bg-slate-300 flex-grow max-w-[60px] sm:max-w-[100px]"></div>
                <h2 class="text-[10px] sm:text-xs uppercase tracking-widest font-black text-slate-400 text-center">Deployment Guide</h2>
                <div class="h-px bg-slate-300 flex-grow max-w-[60px] sm:max-w-[100px]"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 lg:gap-8 max-w-5xl mx-auto interactive">
                <div class="bg-white p-5 sm:p-6 rounded-xl sm:rounded-2xl border border-slate-200 shadow-sm flex flex-col hover-lift relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 text-slate-50 opacity-50 group-hover:text-indigo-50 transition-colors z-0"><i class="fa-solid fa-cloud-arrow-down text-[8rem]"></i></div>
                    <div class="relative z-10 flex items-center gap-3 mb-4"><div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm border border-indigo-100">1</div><h3 class="text-sm sm:text-base font-extrabold text-slate-900">Download Server Engine</h3></div>
                    <p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed relative z-10">Your exported JSON data needs our core router to function as live webpages. Download the engine kit.</p>
                    <div class="mt-auto relative z-10">
                        <a href="bukkry-engine.zip" download class="w-full bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 hover:border-indigo-600 font-bold py-2.5 sm:py-3 px-4 rounded-lg sm:rounded-xl transition-all flex items-center justify-center gap-2 shadow-sm text-xs sm:text-sm text-center interactive">
                            <i class="fa-solid fa-file-zipper text-base sm:text-lg"></i> Download Engine (.zip)
                        </a>
                        <p class="text-[10px] sm:text-[11px] text-slate-400 mt-3 text-center">Contains <code class="font-code text-slate-500 bg-slate-100 px-1 rounded">router.php</code> & <code class="font-code text-slate-500 bg-slate-100 px-1 rounded">.htaccess</code></p>
                    </div>
                </div>

                <div class="bg-white p-5 sm:p-6 rounded-xl sm:rounded-2xl border border-slate-200 shadow-sm flex flex-col hover-lift relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 text-slate-50 opacity-50 group-hover:text-emerald-50 transition-colors z-0"><i class="fa-solid fa-server text-[8rem]"></i></div>
                    <div class="relative z-10 flex items-center gap-3 mb-4"><div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm border border-emerald-100">2</div><h3 class="text-sm sm:text-base font-extrabold text-slate-900">Upload to File Manager</h3></div>
                    <p class="text-xs sm:text-sm text-slate-500 mb-4 leading-relaxed relative z-10">Open your hosting cPanel (public_html) and upload these files together to make pages LIVE:</p>
                    <ul class="text-xs sm:text-sm text-slate-600 space-y-3 font-medium relative z-10 mt-auto">
                        <li class="flex items-center bg-slate-50 p-2 rounded-lg border border-slate-100"><i class="fa-solid fa-circle-check text-emerald-500 mr-2.5"></i> <code class="font-code text-rose-500 mr-2">database.json, sitemaps & image-sitemap </code> <span class="text-[10px] text-slate-400 font-normal ml-auto">(Your Data)</span></li>
                        <li class="flex items-center bg-slate-50 p-2 rounded-lg border border-slate-100"><i class="fa-solid fa-circle-check text-emerald-500 mr-2.5"></i> <code class="font-code text-indigo-500 mr-2">router.php</code> <span class="text-[10px] text-slate-400 font-normal ml-auto">(From Zip)</span></li>
                        <li class="flex items-center bg-slate-50 p-2 rounded-lg border border-slate-100"><i class="fa-solid fa-circle-check text-emerald-500 mr-2.5"></i> <code class="font-code text-indigo-500 mr-2">.htaccess</code> <span class="text-[10px] text-slate-400 font-normal ml-auto">(From Zip)</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto mb-12 sm:mb-16">
            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 mb-4 sm:mb-6 text-center">Frequently Asked Questions</h3>
            <div class="space-y-3 sm:space-y-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 shadow-sm"><h4 class="text-xs sm:text-sm font-bold text-indigo-700 mb-1.5 flex items-center"><i class="fa-solid fa-circle-question mr-2 shrink-0"></i> What variables can I use in my HTML Template?</h4><p class="text-xs sm:text-sm text-slate-600 leading-relaxed">You can use <b>[Keyword]</b>, <b>[Month]</b>, and <b>[Year]</b> anywhere in your code. The engine will dynamically replace them. You can also use <b>[Map]</b> and <b>[YouTube]</b> to auto-inject iFrames. If you upload a Multi-Column CSV (e.g., Keyword, City, Price), you can use <b>[City]</b> and <b>[Price]</b> in your layout too.</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 shadow-sm"><h4 class="text-xs sm:text-sm font-bold text-indigo-700 mb-1.5 flex items-center"><i class="fa-solid fa-circle-question mr-2 shrink-0"></i> Will the 17-in-1 SEO Engine break my design layout?</h4><p class="text-xs sm:text-sm text-slate-600 leading-relaxed"><b>Absolutely not.</b> The engine is designed to be 100% layout-safe. Features like Schemas, Meta Tags, and Geo-Tags are injected invisibly into your <code>&lt;head&gt;</code> section. Image optimizations (Alt, Lazy-load) are seamlessly attached to your existing <code>&lt;img&gt;</code> tags without changing their visual properties.</p></div>
            </div>
        </div>

        <!-- 🌟 TESTIMONIAL CAROUSEL 🌟 -->
        <div class="max-w-6xl mx-auto mb-12 sm:mb-16 overflow-hidden mask-edges px-1 relative interactive">
            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 mb-6 sm:mb-8 text-center">Loved by SEO Experts & Agencies</h3>
            <div id="testimonial-slider" class="flex overflow-x-auto gap-4 sm:gap-5 pb-4 hide-scrollbar cursor-grab active:cursor-grabbing select-none interactive">
                <div class="w-[260px] sm:w-[320px] bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col shrink-0 pointer-events-none"><div class="flex text-amber-400 text-[10px] sm:text-xs mb-3 gap-0.5"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div><p class="text-xs sm:text-sm text-slate-600 italic mb-4">"The Invisible JSON-LD feature is a game-changer. My pages got the 5-star snippets in Google within a week. Absolutely flawless engine!"</p><div class="flex items-center gap-2.5 mt-auto"><div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-black text-xs flex items-center justify-center">R</div><div><h4 class="text-[11px] font-bold text-slate-900">Rahul Sharma</h4><p class="text-[9px] text-slate-500 uppercase">Digital Marketer</p></div></div></div>
                <div class="w-[260px] sm:w-[320px] bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col shrink-0 pointer-events-none"><div class="flex text-amber-400 text-[10px] sm:text-xs mb-3 gap-0.5"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div><p class="text-xs sm:text-sm text-slate-600 italic mb-4">"Generated 1,200 location pages in a single click. The Anti-Footprint AI bypass works perfectly. My server didn't even break a sweat."</p><div class="flex items-center gap-2.5 mt-auto"><div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 font-black text-xs flex items-center justify-center">S</div><div><h4 class="text-[11px] font-bold text-slate-900">Sneha Patel</h4><p class="text-[9px] text-slate-500 uppercase">SEO Agency Owner</p></div></div></div>
                <div class="w-[260px] sm:w-[320px] bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col shrink-0 pointer-events-none"><div class="flex text-amber-400 text-[10px] sm:text-xs mb-3 gap-0.5"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i></div><p class="text-xs sm:text-sm text-slate-600 italic mb-4">"The Image SEO and Breadcrumbs integration saved me hours of manual coding. The tool injects it natively without ruining my CSS."</p><div class="flex items-center gap-2.5 mt-auto"><div class="w-8 h-8 rounded-full bg-rose-50 text-rose-600 font-black text-xs flex items-center justify-center">V</div><div><h4 class="text-[11px] font-bold text-slate-900">Vikram Singh</h4><p class="text-[9px] text-slate-500 uppercase">Web Developer</p></div></div></div>
                <div class="w-[260px] sm:w-[320px] bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col shrink-0 pointer-events-none" aria-hidden="true"><div class="flex text-amber-400 text-[10px] sm:text-xs mb-3 gap-0.5"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div><p class="text-xs sm:text-sm text-slate-600 italic mb-4">"The Invisible JSON-LD feature is a game-changer. My pages got the 5-star snippets in Google within a week. Absolutely flawless engine!"</p><div class="flex items-center gap-2.5 mt-auto"><div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-black text-xs flex items-center justify-center">R</div><div><h4 class="text-[11px] font-bold text-slate-900">Rahul Sharma</h4><p class="text-[9px] text-slate-500 uppercase">Digital Marketer</p></div></div></div>
                <div class="w-[260px] sm:w-[320px] bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col shrink-0 pointer-events-none" aria-hidden="true"><div class="flex text-amber-400 text-[10px] sm:text-xs mb-3 gap-0.5"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div><p class="text-xs sm:text-sm text-slate-600 italic mb-4">"Generated 1,200 location pages in a single click. The Anti-Footprint AI bypass works perfectly. My server didn't break a sweat."</p><div class="flex items-center gap-2.5 mt-auto"><div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 font-black text-xs flex items-center justify-center">S</div><div><h4 class="text-[11px] font-bold text-slate-900">Sneha Patel</h4><p class="text-[9px] text-slate-500 uppercase">SEO Agency Owner</p></div></div></div>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 pt-8 pb-8 relative z-10 shadow-[0_-10px_40px_rgba(0,0,0,0.02)] interactive">
        <div class="max-w-7xl mx-auto px-4 md:px-6 flex flex-col md:flex-row items-center justify-between gap-5 text-center md:text-left text-[10px] md:text-xs font-bold text-slate-500 uppercase tracking-widest">
            <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3">
                <span class="text-slate-400">Powered by</span>
                <a href="https://bukkry.in" target="_blank" class="shrink-0 interactive">
                    <img src="https://ai.bukkry.in/image/bukkry-logo.png" alt="Bukkry Logo" class="h-6 md:h-8 object-contain hover:scale-105 transition-transform drop-shadow-sm">
                </a>
            </div>
            <p class="leading-relaxed mt-2 md:mt-0">© 2026 | Developed by <br class="block sm:hidden"><a href="https://bukkry.in" target="_blank" class="text-brand-purple hover:text-brand-teal transition-colors whitespace-nowrap">Bukkry - Multimedia and Services</a></p>
        </div>
    </footer>

    <!-- AUTHENTICATION MODAL -->
    <?php if(empty($_SESSION['user_id'])): ?>
    <div id="auth-modal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4 interactive" style="background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px);">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden relative border border-slate-200 transform transition-all">
            <button onclick="window.closeAuthModal()" class="absolute top-4 right-4 text-white/70 hover:text-white z-50 transition-colors interactive"><i class="fa-solid fa-xmark text-xl"></i></button>
            <div class="bg-slate-900 p-6 text-center relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-10 -mr-10 w-32 h-32 bg-brand-purple opacity-30 rounded-full blur-2xl"></div>
                <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-32 h-32 bg-rose-500 opacity-30 rounded-full blur-2xl"></div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-12 h-12 rounded-xl bg-rose-600 flex items-center justify-center shadow-lg mb-3"><i class="fa-solid fa-lock text-white text-xl"></i></div>
                    <h2 class="text-xl font-extrabold text-white tracking-tight">Login Required</h2>
                    <p class="text-slate-400 text-xs mt-1">Please login or create a free account.</p>
                </div>
            </div>
            <div id="auth-alert" class="hidden text-center text-xs font-bold py-2 px-4"></div>
            <div class="p-6 sm:p-8 pt-4">
                <form id="login-form" class="space-y-4 transition-all block">
                    <a href="google_auth.php?action=login" class="w-full bg-white border border-slate-200 text-slate-700 font-bold py-3.5 rounded-xl flex items-center justify-center gap-3 shadow-sm hover:bg-slate-50 hover:border-slate-300 transition-all mb-4 interactive">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                        Continue with Google
                    </a>
                    <div class="flex items-center gap-3 mb-5"><div class="h-px bg-slate-200 flex-1"></div><span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">OR EMAIL</span><div class="h-px bg-slate-200 flex-1"></div></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Work Email</label><input type="email" id="login-email" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest">Password</label>
                            <a href="javascript:void(0)" onclick="window.switchAuthView('forgot')" class="text-[11px] font-bold text-rose-600 hover:text-rose-800 interactive">Forgot Password?</a>
                        </div>
                        <input type="password" id="login-pass" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive">
                    </div>
                    <button type="submit" class="w-full bg-slate-900 hover:bg-rose-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-md mt-2 interactive">Access Engine <i class="fa-solid fa-arrow-right"></i></button>
                    <p class="text-center text-xs text-slate-500 mt-4 font-medium">New to Bukkry? <a href="javascript:void(0)" onclick="window.switchAuthView('signup')" class="text-rose-600 font-bold hover:underline interactive">Create Account</a></p>
                </form>

                <form id="signup-form" class="space-y-4 transition-all hidden">
                    <div class="bg-rose-50 text-rose-700 text-xs font-bold p-3 rounded-lg border border-rose-100 flex items-center gap-2 mb-2"><i class="fa-solid fa-gift text-rose-500 text-lg"></i> Get 100 Free Credits!</div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Full Name</label><input type="text" id="signup-name" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Work Email</label><input type="email" id="signup-email" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Password</label><input type="password" id="signup-pass" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-md mt-2 interactive">Claim Free Credits</button>
                    <p class="text-center text-xs text-slate-500 mt-4 font-medium">Already have an account? <a href="javascript:void(0)" onclick="window.switchAuthView('login')" class="text-rose-600 font-bold hover:underline interactive">Log In</a></p>
                </form>

                <form id="verify-signup-form" class="space-y-4 transition-all hidden">
                    <div class="bg-rose-50/80 text-rose-700 text-[11px] font-bold p-3 rounded-lg border border-rose-200/50 flex items-center gap-2 mb-2"><i class="fa-solid fa-envelope-circle-check text-rose-500 text-lg"></i> Verification OTP sent!</div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 text-center">Enter 6-Digit OTP</label><input type="text" id="signup-otp" required maxlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-center text-2xl tracking-[0.5em] font-code focus:outline-none focus:border-rose-500 shadow-sm interactive"></div>
                    <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-md mt-2 interactive">Verify & Claim Credits</button>
                    <p class="text-center text-xs text-slate-500 mt-3 font-medium">Didn't receive it? <a href="javascript:void(0)" onclick="window.resendSignupOTP()" class="text-rose-600 font-bold hover:underline interactive">Resend OTP</a></p>
                    <p class="text-center text-xs text-slate-500 mt-2"><a href="javascript:void(0)" onclick="window.switchAuthView('signup')" class="text-slate-500 hover:text-slate-800 font-bold interactive"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Signup</a></p>
                </form>

                <form id="forgot-form" class="space-y-4 transition-all hidden">
                    <p class="text-xs text-slate-500 text-center mb-4 leading-relaxed">Enter your registered email address for an OTP.</p>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Registered Email</label><input type="email" id="forgot-email" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <button type="submit" class="w-full bg-slate-900 hover:bg-rose-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-md mt-2 interactive">Send OTP to Email</button>
                    <p class="text-center text-xs text-slate-500 mt-4 font-medium"><a href="javascript:void(0)" onclick="window.switchAuthView('login')" class="text-slate-500 hover:text-slate-800 font-bold interactive"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Login</a></p>
                </form>

                <form id="reset-form" class="space-y-4 transition-all hidden">
                    <div class="bg-emerald-50 text-emerald-700 text-[11px] font-bold p-3 rounded-lg border border-emerald-100 flex items-center gap-2 mb-2"><i class="fa-solid fa-envelope-circle-check text-emerald-500 text-lg"></i> OTP sent! Check your inbox.</div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">6-Digit OTP</label><input type="text" id="reset-otp" required maxlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-center text-xl tracking-[0.5em] font-code focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">New Password</label><input type="password" id="reset-pass" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-rose-500 transition-colors shadow-sm interactive"></div>
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-md mt-2 interactive">Update Password</button>
                    <p class="text-center text-xs text-slate-500 mt-4 font-medium"><a href="javascript:void(0)" onclick="window.switchAuthView('forgot')" class="text-slate-500 hover:text-slate-800 font-bold interactive"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Email</a></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PRICING MODAL -->
    <div id="pricing-modal" class="hidden fixed inset-0 z-[110] flex-col items-center justify-center p-0 sm:p-4 overflow-hidden interactive" style="background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px);">
        <div class="bg-slate-50 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-5xl relative flex flex-col max-h-[95vh] transform transition-all -mt-12 sm:mt-0">
            <button onclick="window.closePricingModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-900 z-50 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow interactive"><i class="fa-solid fa-xmark"></i></button>
            <div class="text-center pt-8 pb-4 px-4 shrink-0">
                <h2 class="text-2xl sm:text-4xl font-extrabold text-slate-900 mb-2 tracking-tight">Upgrade Your Engine</h2>
                <p class="text-slate-500 text-xs sm:text-sm">Swipe horizontally to view all plans.</p>
            </div>
            <div class="flex md:grid md:grid-cols-3 flex-nowrap overflow-x-auto overflow-y-hidden snap-x snap-mandatory gap-4 px-[7.5vw] md:px-8 pt-0 pb-8 pricing-hide-scrollbar items-stretch">
                <div class="w-[85vw] sm:w-[320px] md:w-auto shrink-0 snap-center bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 flex flex-col relative pricing-hover-lift hover-lift">
                    <div class="mb-4"><span class="bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full">Pay As You Go</span></div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">₹1499 <span class="text-sm font-normal text-slate-500">/one-time</span></h3>
                    <p class="text-sm text-slate-500 mb-6">Perfect for solo bloggers and quick tests.</p>
                    <ul class="text-sm text-slate-600 space-y-3 mb-8 flex-grow">
                        <li><i class="fa-solid fa-check text-emerald-500 mr-2"></i> <b>1,000 Credits</b> added instantly</li>
                        <li><i class="fa-solid fa-check text-emerald-500 mr-2"></i> Never expires</li>
                        <li><i class="fa-solid fa-check text-emerald-500 mr-2"></i> Access to basic SEO tools</li>
                    </ul>
                    <button onclick="window.processPayment('payg', 1499)" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold py-3 rounded-xl transition-colors mt-auto interactive">Buy Credits</button>
                </div>
                <div class="bg-slate-900 rounded-2xl border border-slate-800 p-5 sm:p-6 flex flex-col relative pricing-hover-lift hover-lift transform md:-translate-y-4 shadow-2xl min-w-[85%] sm:min-w-[320px] md:min-w-0 snap-center shrink-0">
                    <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-rose-500 to-indigo-500"></div>
                    <div class="mb-4 flex justify-between items-center"><span class="bg-indigo-500/20 text-indigo-300 text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full">Monthly Pro</span><span class="text-[10px] text-amber-400 font-bold uppercase tracking-widest animate-pulse"><i class="fa-solid fa-star"></i> Most Popular</span></div>
                    <h3 class="text-3xl font-extrabold text-white mb-2">₹3,999 <span class="text-sm font-normal text-slate-400">/month</span></h3>
                    <p class="text-sm text-slate-400 mb-6">Built for growing SEO agencies.</p>
                    <ul class="text-sm text-slate-300 space-y-3 mb-8 flex-grow">
                        <li><i class="fa-solid fa-check text-emerald-400 mr-2"></i> <b>10,000 Credits</b> refreshed monthly</li>
                        <li><i class="fa-solid fa-check text-emerald-400 mr-2"></i> Priority Server Processing</li>
                        <li><i class="fa-solid fa-check text-emerald-400 mr-2"></i> Advanced XML Sitemaps</li>
                    </ul>
                    <button onclick="window.processPayment('monthly', 3999)" class="w-full bg-rose-600 hover:bg-rose-500 text-white font-bold py-3.5 rounded-xl transition-colors shadow-lg mt-auto interactive">Subscribe Monthly</button>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 flex flex-col relative pricing-hover-lift hover-lift min-w-[85%] sm:min-w-[320px] md:min-w-0 snap-center shrink-0">
                    <div class="absolute -top-3 -right-3 bg-rose-500 text-white text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full shadow-lg transform rotate-3">20% OFF</div>
                    <div class="mb-4"><span class="bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full">Annual Elite</span></div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">₹38,390 <span class="text-sm font-normal text-slate-500">/year</span></h3>
                    <p class="text-sm text-slate-500 mb-6">For enterprise marketing teams scaling hard.</p>
                    <ul class="text-sm text-slate-600 space-y-3 mb-8 flex-grow">
                        <li><i class="fa-solid fa-check text-emerald-500 mr-2"></i> <b>Unlimited Runs</b> (Fair Use)</li>
                        <li><i class="fa-solid fa-check text-emerald-500 mr-2"></i> Save ₹9,598 instantly</li>
                    </ul>
                    <button onclick="window.processPayment('annual', 38390)" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold py-3 rounded-xl transition-colors mt-auto interactive">Go Annual</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $html_payload = ob_get_clean();

    // --- 🟢 FULL JAVASCRIPT PAYLOAD 🟢 ---
    ob_start();
    ?>
    // 🛡️ DOMAIN LOCK 🛡️
    if (window.location.hostname !== 'ai.bukkry.in' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        document.body.innerHTML = '<div style="color:red; font-family:sans-serif; text-align:center; margin-top:20%;"><h1>FATAL ERROR: UNAUTHORIZED DOMAIN</h1></div>';
        while(true) { debugger; }
    }

    // Carousel Logic
    window.initCarousel = function() {
        const slider = document.getElementById('testimonial-slider');
        if(!slider) return;
        let isDown = false;
        let startX;
        let scrollLeft;
        let animationId;

        function step() {
            if(!isDown) {
                slider.scrollLeft += 1.5; 
                if(slider.scrollLeft >= (slider.scrollWidth - slider.clientWidth - 1)) { slider.scrollLeft = 0; }
            }
            animationId = requestAnimationFrame(step);
        }

        function startAutoScroll() { animationId = requestAnimationFrame(step); }
        function stopAutoScroll() { cancelAnimationFrame(animationId); }

        slider.addEventListener('mousedown', (e) => { isDown = true; slider.style.scrollBehavior = 'auto'; startX = e.pageX - slider.offsetLeft; scrollLeft = slider.scrollLeft; stopAutoScroll(); });
        slider.addEventListener('mouseleave', () => { if(isDown) { isDown = false; slider.classList.remove('cursor-grabbing'); slider.classList.add('cursor-grab'); startAutoScroll(); } });
        slider.addEventListener('mouseup', () => { isDown = false; slider.classList.remove('cursor-grabbing'); slider.classList.add('cursor-grab'); startAutoScroll(); });
        slider.addEventListener('mousemove', (e) => { if (!isDown) return; e.preventDefault(); const x = e.pageX - slider.offsetLeft; const walk = (x - startX) * 2; slider.scrollLeft = scrollLeft - walk; });

        slider.addEventListener('touchstart', () => { isDown = true; stopAutoScroll(); }, {passive: true});
        slider.addEventListener('touchend', () => { isDown = false; startAutoScroll(); }, {passive: true});

        startAutoScroll();
    }
    window.initCarousel();

    // UI Logic
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    if(mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('max-h-0'); mobileMenu.classList.toggle('opacity-0');
            mobileMenu.classList.toggle('max-h-[500px]'); mobileMenu.classList.toggle('opacity-100');
        });
    }

    // Auth & Pricing
    const isLoggedIn = <?php echo !empty($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    window.openAuthModal = function() { const m = document.getElementById('auth-modal'); if(m) { m.classList.remove('hidden'); m.classList.add('flex'); window.switchAuthView('login'); } }
    window.closeAuthModal = function() { const m = document.getElementById('auth-modal'); if(m) { m.classList.add('hidden'); m.classList.remove('flex'); } }
    window.openPricingModal = function() { document.getElementById('pricing-modal').classList.remove('hidden'); document.getElementById('pricing-modal').classList.add('flex'); }
    window.closePricingModal = function() { document.getElementById('pricing-modal').classList.add('hidden'); document.getElementById('pricing-modal').classList.remove('flex'); }
    window.switchAuthView = function(view) {
        ['login', 'signup', 'forgot', 'reset', 'verify-signup'].forEach(v => { const f = document.getElementById(v+'-form'); if(f) f.classList.add('hidden'); });
        const alertBox = document.getElementById('auth-alert'); if(alertBox) alertBox.classList.add('hidden');
        const targetForm = document.getElementById(view + '-form'); if(targetForm) targetForm.classList.remove('hidden');
    }

    function showAuthAlert(msg, type) {
        const a = document.getElementById('auth-alert'); if(!a) return;
        a.className = 'text-center text-xs font-bold py-2 px-4 mb-3 rounded-lg border';
        if(type === 'error') a.classList.add('bg-rose-100', 'text-rose-700', 'border-rose-200');
        if(type === 'success') a.classList.add('bg-emerald-100', 'text-emerald-700', 'border-emerald-200');
        a.innerHTML = msg; a.classList.remove('hidden');
    }

    window.resendSignupOTP = async function() {
        if(!window.signupEmailTemp) return;
        const btn = document.querySelector('a[onclick="window.resendSignupOTP()"]');
        const orig = btn.innerHTML; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Sending...`; btn.style.pointerEvents = 'none';
        const data = new URLSearchParams(); data.append('action', 'resend_signup_otp'); data.append('email', window.signupEmailTemp);
        try {
            const res = await fetch('auth_handler.php', { method: 'POST', body: data }); const result = await res.json();
            if(result.success) showAuthAlert(result.msg, 'success'); else showAuthAlert(result.msg, 'error');
        } catch (err) { showAuthAlert("System Error.", 'error'); }
        btn.innerHTML = orig; btn.style.pointerEvents = 'auto';
    }

    window.submitAuthForm = async function(e, action, formDataObj) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]'); const orig = btn.innerHTML;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Processing...`; btn.disabled = true;

        const data = new URLSearchParams(); data.append('action', action);
        for (const key in formDataObj) { data.append(key, formDataObj[key]); }

        try {
            const res = await fetch('auth_handler.php', { method: 'POST', body: data }); const result = await res.json();
            if(result.success) {
                showAuthAlert(result.msg, 'success');
                if(action === 'login' || action === 'verify_signup_otp') { setTimeout(() => window.location.reload(), 1000); } 
                else if(action === 'signup') { window.signupEmailTemp = formDataObj.email; setTimeout(() => window.switchAuthView('verify-signup'), 1500); } 
                else if(action === 'send_otp') { window.switchAuthView('reset'); } 
                else if(action === 'reset_password') { setTimeout(() => window.switchAuthView('login'), 2000); }
            } else {
                showAuthAlert(result.msg, 'error');
                if(result.requires_verification) { window.signupEmailTemp = result.email; setTimeout(() => window.switchAuthView('verify-signup'), 2000); }
            }
        } catch (err) { showAuthAlert("System Error.", 'error'); }
        btn.innerHTML = orig; btn.disabled = false;
    }

    if (!isLoggedIn) {
        document.getElementById('login-form')?.addEventListener('submit', (e) => window.submitAuthForm(e, 'login', { email: document.getElementById('login-email').value, password: document.getElementById('login-pass').value }));
        document.getElementById('signup-form')?.addEventListener('submit', (e) => window.submitAuthForm(e, 'signup', { name: document.getElementById('signup-name').value, email: document.getElementById('signup-email').value, password: document.getElementById('signup-pass').value }));
        document.getElementById('forgot-form')?.addEventListener('submit', (e) => window.submitAuthForm(e, 'send_otp', { email: document.getElementById('forgot-email').value }));
        document.getElementById('reset-form')?.addEventListener('submit', (e) => window.submitAuthForm(e, 'reset_password', { email: document.getElementById('forgot-email').value, otp: document.getElementById('reset-otp').value, new_password: document.getElementById('reset-pass').value }));
        document.getElementById('verify-signup-form')?.addEventListener('submit', (e) => window.submitAuthForm(e, 'verify_signup_otp', { email: window.signupEmailTemp, otp: document.getElementById('signup-otp').value }));
    }

    window.processPayment = async function(plan, amount) {
        if (!isLoggedIn) { window.closePricingModal(); window.openAuthModal(); return; }
        const btn = event.target; const orig = btn.innerHTML;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Processing...`; btn.disabled = true;

        try {
            const data = new URLSearchParams(); data.append('action', 'create_order'); data.append('plan', plan);
            const res = await fetch('payment_handler.php', { method: 'POST', body: data }); const order = await res.json();
            if (order.success) {
                const options = {
                    "key": order.key, "amount": order.amount, "currency": "INR", "name": "Programmatic SEO Bukkry", "description": "Premium SaaS Upgrade",
                    "image": "https://bukkry.in/wp-content/uploads/IMG_20220129_003512-removebg-preview-1-e1704376259816-1.png.webp",
                    "order_id": order.order_id,
                    "handler": async function (resp) {
                        const vData = new URLSearchParams(); vData.append('action', 'verify_payment');
                        vData.append('razorpay_payment_id', resp.razorpay_payment_id); vData.append('razorpay_order_id', resp.razorpay_order_id);
                        vData.append('razorpay_signature', resp.razorpay_signature); vData.append('plan', plan);
                        const vRes = await fetch('payment_handler.php', { method: 'POST', body: vData }); const vResult = await vRes.json();
                        if (vResult.success) { alert("🎉 Success! " + vResult.msg); window.location.reload(); } else alert("❌ Error: " + vResult.msg);
                    }, "theme": { "color": "#e11d48" }
                };
                const rzp1 = new Razorpay(options); rzp1.on('payment.failed', () => alert("Payment Failed.")); rzp1.open();
            } else alert("Error: " + order.msg);
        } catch (error) { alert("Gateway Error."); }
        btn.innerHTML = orig; btn.disabled = false;
    }

    // Engine UI Interactions
    window.shareSocial = function(platform, e) {
        e.preventDefault(); const url = encodeURIComponent('https://bukkry.in'); const text = encodeURIComponent('I just launched a massive SEO Pages section using the Bukkry AI Programmatic SEO Engine! 🚀📰 Check it out:');
        let shareUrl = '';
        if(platform === 'fb') shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
        if(platform === 'tw') shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
        if(platform === 'wa') shareUrl = `https://api.whatsapp.com/send?text=${text} ${url}`;
        if(platform === 'ln') shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
        window.open(shareUrl, 'share-dialog', 'width=600,height=400');
    }

    const dropzone = document.getElementById('csv-dropzone');
    const fileInput = document.getElementById('csv-file-input');
    const csvInput = document.getElementById('csv-input');
    const sampleCsvBtn = document.getElementById('download-sample-csv');
    const genBtn = document.getElementById('generate-btn');
    const terminal = document.getElementById('terminal-section');
    const logs = document.getElementById('terminal-logs');
    const exportGroup = document.getElementById('export-buttons-group');
    const shareBox = document.getElementById('social-share-box');
    const domainInput = document.getElementById('domain-input');

    if(sampleCsvBtn) {
        sampleCsvBtn.addEventListener('click', () => {
            const sampleCsvContent = `Keyword\nbest interior designer\ntop interior designer\naffordable interior design\nmodern interior decorators\nluxury home interiors`;
            const blob = new Blob([sampleCsvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a"); link.href = URL.createObjectURL(blob); link.download = "SEO_Pages_Topic_List.csv";
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
        });
    }

    if(dropzone) {
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-rose-500'); });
        dropzone.addEventListener('dragleave', () => { dropzone.classList.remove('border-rose-500'); });
        dropzone.addEventListener('drop', (e) => { e.preventDefault(); dropzone.classList.remove('border-rose-500'); if (e.dataTransfer.files.length) handleFileUpload(e.dataTransfer.files[0]); });
    }
    if(fileInput) fileInput.addEventListener('change', (e) => { if (e.target.files.length) handleFileUpload(e.target.files[0]); });

    function handleFileUpload(file) {
        if (file.type !== "text/csv" && file.type !== "text/plain" && !file.name.endsWith('.csv') && !file.name.endsWith('.txt')) { alert("Please upload a valid .csv or .txt file."); return; }
        const reader = new FileReader();
        reader.onload = function(e) { csvInput.value = e.target.result; csvInput.scrollTop = 0; };
        reader.onerror = function() { alert("Error reading file."); }; reader.readAsText(file);
    }

    function logTerm(msg, color="text-slate-400") { logs.innerHTML += `<div class="${color}">${msg}</div>`; logs.scrollTop = logs.scrollHeight; }

    window.globalExportData = null;

    if(genBtn) {
        genBtn.addEventListener('click', async () => {
            if (!isLoggedIn) { openAuthModal(); return; }

            const rawList = csvInput.value.trim();
            const templateData = document.getElementById('template-input').value.trim();
            let targetDomain = domainInput.value.trim();
            
            if (!targetDomain || !targetDomain.startsWith('http')) { alert("Please enter a valid Target Domain starting with http:// or https://"); domainInput.focus(); return; }
            if(!targetDomain.endsWith('/')) targetDomain += '/';
            if (!rawList || !templateData) { alert("Both Topic List and HTML Template are required."); return; }

            terminal.classList.remove('hidden'); exportGroup.classList.add('hidden'); shareBox.classList.add('hidden'); logs.innerHTML = '';
            
            logTerm(`> Authenticating cipher & checking credits...`, "text-rose-400");
            logTerm(`> Linking to Target Domain: ${targetDomain}`, "text-emerald-400");
            logTerm(`> Booting Bukkry AI Programmatic SEO Engine...`);

            function xorEncrypt(text, key) {
                let result = '';
                for(let i = 0; i < text.length; i++) { result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length)); }
                return btoa(result);
            }

            const cipherToken = document.querySelector('meta[name="cipher-token"]').getAttribute('content');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const payloadObject = { csv: rawList, template: templateData, domain: targetDomain, use_spintax: true, use_schema: true };
            const jsonStr = unescape(encodeURIComponent(JSON.stringify(payloadObject)));
            const scrambledPayload = xorEncrypt(jsonStr, cipherToken);

            try {
                logTerm(`> Transmitting data to backend bulk engine...`, "text-indigo-400 font-bold");
                
                // Active Cache buster + Fallback explicit catch for 404
                const engineUrl = 'x-bulk-engine.php?t=' + new Date().getTime();
                const res = await fetch(engineUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: csrfToken, payload: scrambledPayload })
                });
                
                if (!res.ok) {
                    if (res.status === 404) throw new Error("File 'x-bulk-engine.php' not found (404). Ensure both files are uploaded in the same directory.");
                    throw new Error("HTTP Status " + res.status);
                }
                
                const data = await res.json();
                
                if (data.success) {
                    window.globalExportData = data;
                    const creditElem = document.getElementById('live-credits');
                    if(creditElem) { creditElem.innerText = parseInt(creditElem.innerText) - 100; }

                    logTerm(`> SUCCESS: Compiled ${data.total_generated} Viral SEO Pages safely. (100 Credits Used)`, "text-emerald-400 font-bold");
                    logTerm(`> Injected Article Schemas & Meta Matrices.`);
                    logTerm(`> Generated Page-Specific Sitemaps.`);
                    logTerm(`> Ready. Export your files below.`, "text-rose-300 font-bold");
                    
                    exportGroup.classList.remove('hidden'); shareBox.classList.remove('hidden');
                } else {
                    if (data.error_code === 'OUT_OF_CREDITS') {
                        logTerm(`> FATAL: ${data.msg} Opening Billing System...`, "text-amber-500 font-bold");
                        setTimeout(() => openPricingModal(), 1500);
                    } else {
                        logTerm(`> SERVER ERROR: ${data.msg}`, "text-rose-500 font-bold");
                    }
                }
            } catch (e) {
                logTerm(`> CRITICAL ERROR: ${e.message}`, "text-rose-500 font-bold");
            }
        });
    }

    document.getElementById('download-json-btn')?.addEventListener('click', () => {
        if (!window.globalExportData) return;
        const exportObj = {
            _bukkry_branding: ["Database generated by AI Programmatic SEO Pages Engine Bukkry", "Powered By Bukkry - Multimedia and Services", "https://bukkry.in"],
            metadata: { generated_on: new Date().toISOString(), total_pages: window.globalExportData.total_generated, target_domain: document.getElementById('domain-input').value.trim() },
            pages: window.globalExportData.pages
        };
        const blob = new Blob([JSON.stringify(exportObj, null, 2)], { type: "application/json;charset=utf-8" });
        const url = URL.createObjectURL(blob); const a = document.createElement("a");
        a.style.display = "none"; a.href = url; a.download = "database.json";
        document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    });

    document.getElementById('download-sitemap-btn')?.addEventListener('click', () => {
        if (!window.globalExportData || !window.globalExportData.sitemap) return;
        const blob = new Blob([window.globalExportData.sitemap], { type: "application/xml;charset=utf-8" });
        const url = URL.createObjectURL(blob); const a = document.createElement("a");
        a.style.display = "none"; a.href = url; a.download = "page-sitemap.xml";
        document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    });

    document.getElementById('download-img-sitemap-btn')?.addEventListener('click', () => {
        if (!window.globalExportData || !window.globalExportData.image_sitemap) return;
        const blob = new Blob([window.globalExportData.image_sitemap], { type: "application/xml;charset=utf-8" });
        const url = URL.createObjectURL(blob); const a = document.createElement("a");
        a.style.display = "none"; a.href = url; a.download = "image-sitemap.xml";
        document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    });

    // FAST VIRAL SHARE LINK LOGIC - Fallback protected against 404s
    window.generateDeliverableLink = async function() {
        const btn = document.getElementById('viral-share-btn');
        if(!btn) return;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Packaging Files...';
        btn.disabled = true;

        try {
            const resultContainer = document.getElementById('terminal-section');
            if(!resultContainer) { alert("Error: Missing container."); btn.innerHTML = originalText; btn.disabled = false; return; }

            const clonedBox = resultContainer.cloneNode(true);
            const noPrintElements = clonedBox.querySelectorAll('.no-print, #export-buttons-group, #social-share-box');
            noPrintElements.forEach(el => el.remove());

            if (!window.globalExportData) { alert("No data generated yet!"); btn.innerHTML = originalText; btn.disabled = false; return; }

            const exportObj = {
                _bukkry_branding: ["Database generated by AI Programmatic SEO Pages Engine Bukkry", "Powered By Bukkry - Multimedia and Services", "https://bukkry.in"],
                metadata: { generated_on: new Date().toISOString(), total_pages: window.globalExportData.total_generated || 0, target_domain: document.getElementById('domain-input') ? document.getElementById('domain-input').value.trim() : "Unknown" },
                pages: window.globalExportData.pages || []
            };
            
            let myJsonData = JSON.stringify(exportObj, null, 2); 
            let myPageSitemap = window.globalExportData.sitemap || '<' + '?xml version="1.0"?><urlset></urlset>';
            let myImageSitemap = window.globalExportData.image_sitemap || '<' + '?xml version="1.0"?><urlset></urlset>';

            let fileDataToEmbed = { "database.json": myJsonData, "sitemap.xml": myPageSitemap, "image-sitemap.xml": myImageSitemap };
            
            let safeScriptHTMLContent = JSON.stringify(fileDataToEmbed).replace(/<\/script>/gi, "<\\/script>");

            const downloadSection = document.createElement('div');
            downloadSection.className = "mt-6 pt-6 border-t border-slate-800 flex flex-wrap gap-3 justify-center bg-slate-900/50 p-4 rounded-xl";
            downloadSection.innerHTML = 
                '<p class="w-full text-center text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Download Your Files</p>' +
                '<button onclick="downloadEmbeddedFile(\'database.json\', \'application/json\')" class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2 px-4 rounded-lg text-sm transition-all shadow-md"><i class="fa-solid fa-database mr-2"></i> Data JSON</button>' +
                '<button onclick="downloadEmbeddedFile(\'sitemap.xml\', \'application/xml\')" class="bg-indigo-500 hover:bg-indigo-400 text-white font-bold py-2 px-4 rounded-lg text-sm transition-all shadow-md"><i class="fa-solid fa-sitemap mr-2"></i> Page Sitemap</button>' +
                '<button onclick="downloadEmbeddedFile(\'image-sitemap.xml\', \'application/xml\')" class="bg-sky-500 hover:bg-sky-400 text-white font-bold py-2 px-4 rounded-lg text-sm transition-all shadow-md"><i class="fa-solid fa-images mr-2"></i> Image Sitemap</button>';
            
            clonedBox.appendChild(downloadSection);

            const scriptLogic = document.createElement('div');
            let safeScriptHTML = '<script id="embedded-file-data" type="application/json">';
            safeScriptHTML += safeScriptHTMLContent;
            safeScriptHTML += '</' + 'script>';
            safeScriptHTML += '<script>';
            safeScriptHTML += 'function downloadEmbeddedFile(filename, mimeType) { ';
            safeScriptHTML += '  try { ';
            safeScriptHTML += '    const dataObj = JSON.parse(document.getElementById("embedded-file-data").textContent); ';
            safeScriptHTML += '    const fileContent = dataObj[filename]; ';
            safeScriptHTML += '    if(!fileContent) { alert("File is empty or not generated."); return; } ';
            safeScriptHTML += '    const blob = new Blob([fileContent], {type: mimeType + ";charset=utf-8"}); ';
            safeScriptHTML += '    const url = URL.createObjectURL(blob); ';
            safeScriptHTML += '    const a = document.createElement("a"); ';
            safeScriptHTML += '    a.href = url; a.download = filename; ';
            safeScriptHTML += '    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url); ';
            safeScriptHTML += '  } catch (e) { alert("Error generating download file."); } ';
            safeScriptHTML += '} ';
            safeScriptHTML += '</' + 'script>';

            scriptLogic.innerHTML = safeScriptHTML;
            clonedBox.appendChild(scriptLogic);
            
            const clonedLogs = clonedBox.querySelector('#terminal-logs');
            if(clonedLogs) clonedLogs.innerHTML = '<div class="text-emerald-400">> System execution complete. Files compiled successfully.</div>';

            const payload = { target: "Programmatic SEO Batch", html_content: clonedBox.outerHTML };

            const response = await fetch('save_report.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            
            // Handles missing save_report.php by immediately falling back to local file download without crashing!
            if (!response.ok) {
                if (response.status === 404) throw new Error("save_report_404");
                throw new Error("HTTP Status " + response.status);
            }
            
            const result = await response.json();
            
            if(result.success) {
                navigator.clipboard.writeText(result.share_url);
                btn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Link Copied!';
                btn.classList.replace('bg-rose-600', 'bg-emerald-500');
                btn.classList.replace('hover:bg-rose-700', 'hover:bg-emerald-600');
                alert("✅ Secure Download Link Copied!\n\nClient can now see the results and download files directly from their browser memory.");
            } else {
                alert("Error: " + result.msg); btn.innerHTML = originalText;
            }
        } catch (e) {
            if (e.message === "save_report_404") {
                // FALLBACK: Download the report locally if save_report.php is missing. Completely resolves the 404 issue.
                const blob = new Blob([clonedBox.outerHTML], {type: "text/html;charset=utf-8"});
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url; a.download = "SEO_Delivery_Report.html";
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                
                btn.innerHTML = '<i class="fa-solid fa-download mr-2"></i> Report Downloaded!';
                btn.classList.replace('bg-rose-600', 'bg-emerald-500');
                alert("Report script not found on server (404). Your report has been downloaded locally instead as 'SEO_Delivery_Report.html'.");
            } else {
                alert("Failed to generate link. Check network."); btn.innerHTML = originalText;
            }
        }
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace('bg-emerald-500', 'bg-rose-600');
            btn.classList.replace('hover:bg-emerald-600', 'hover:bg-rose-700');
            btn.disabled = false;
        }, 3000);
    }
    
    // Auto-start Script
    window.addEventListener('load', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const autoUrl = urlParams.get('url');
        if (autoUrl) {
            const inputField = document.getElementById('domain-input');
            const actionBtn = document.getElementById('generate-btn');
            if (inputField && actionBtn) {
                inputField.value = autoUrl; 
                setTimeout(() => { actionBtn.click(); }, 800);
            }
        }
    });

    <?php
    $js_payload = ob_get_clean();
    ob_end_clean(); 

    // 🔥 PURE BASE64 ENCODING STRREV (100% RELIABLE) 🔥
    echo json_encode([
        "success" => true,
        "_x_h" => strrev(base64_encode($html_payload)),
        "_x_j" => strrev(base64_encode($js_payload))
    ]);
    exit;
}
?>