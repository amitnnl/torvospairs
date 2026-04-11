<?php
/**
 * TORVO SPAIR API — Documentation Page
 */
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'REST API Documentation';
$pageIcon       = 'fas fa-code';
$activePage     = 'api_docs';
$pageBreadcrumb = 'API Documentation';
include BASE_PATH . '/includes/header.php';
requireAdmin();

define('API_KEY_DEMO', 'torvo_api_2024x');
$baseUrl = APP_URL . '/api';
?>

<div class="page-body">

<div style="display:grid;grid-template-columns:260px 1fr;gap:1.25rem;align-items:start;">

<!-- Sidebar Nav -->
<div class="card" style="position:sticky;top:84px;">
    <div class="card-body" style="padding:1rem;">
        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.75rem;">Endpoints</div>
        <?php
        $endpoints = [
            ['GET', 'search.php',   'Quick Search'],
            ['GET', 'products.php', 'List Products'],
            ['GET', 'products.php?id=1', 'Single Product'],
            ['POST','products.php', 'Stock Check (SKU)'],
        ];
        foreach ($endpoints as [$method, $ep, $label]):
            $mColor = $method === 'GET' ? '#16a34a' : '#2563eb';
        ?>
        <a href="#ep-<?= md5($method.$ep) ?>" style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.6rem;border-radius:6px;margin-bottom:0.25rem;font-size:0.8rem;color:var(--text-medium);text-decoration:none;transition:all 0.2s;"
           onmouseover="this.style.background='var(--bg-card2)'" onmouseout="this.style.background=''">
            <span style="background:<?= $mColor ?>22;color:<?= $mColor ?>;font-size:0.6rem;font-weight:800;padding:2px 5px;border-radius:4px;min-width:32px;text-align:center;"><?= $method ?></span>
            <?= $label ?>
        </a>
        <?php endforeach; ?>
        <hr style="border:none;border-top:1px solid var(--border-color);margin:0.75rem 0;">
        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.5rem;">Your API Key</div>
        <code style="display:block;background:var(--bg-card2);padding:0.5rem;border-radius:6px;font-size:0.72rem;word-break:break-all;color:var(--primary);"><?= API_KEY_DEMO ?></code>
    </div>
</div>

<!-- Main content -->
<div>
    <!-- Header -->
    <div class="card" style="margin-bottom:1.25rem;border-top:3px solid var(--primary);">
        <div class="card-body">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                <div>
                    <div style="font-size:1.2rem;font-weight:800;color:var(--text-primary);margin-bottom:0.3rem;">TORVO SPAIR REST API <span style="font-size:0.7rem;background:var(--primary);color:#fff;padding:2px 7px;border-radius:20px;margin-left:0.3rem;">v1.0</span></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Base URL: <code style="background:var(--bg-card2);padding:2px 7px;border-radius:4px;"><?= $baseUrl ?></code></div>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);">
                    <i class="fas fa-lock" style="color:var(--success);"></i> API Key Auth<br>
                    <i class="fas fa-code" style="color:var(--primary);"></i> JSON Responses
                </div>
            </div>
        </div>
    </div>

    <!-- Auth -->
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><div class="card-title"><i class="fas fa-key"></i> Authentication</div></div>
        <div class="card-body" style="font-size:0.875rem;">
            <p style="margin-bottom:0.75rem;color:var(--text-medium);">Pass your API key in the request header or as a query parameter:</p>
            <pre style="background:var(--bg-card2);padding:0.85rem 1rem;border-radius:8px;font-size:0.78rem;overflow-x:auto;margin-bottom:0.75rem;"><code># Via Header (recommended)
X-API-Key: <?= API_KEY_DEMO ?>

# Via Query Parameter
<?= $baseUrl ?>/products.php?api_key=<?= API_KEY_DEMO ?></code></pre>
            <div style="background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.2);border-radius:8px;padding:0.65rem;font-size:0.78rem;color:#92400e;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Note:</strong> Search and product listing endpoints are public (no auth needed). 
                Write operations and stock checks require the API key.
            </div>
        </div>
    </div>

    <!-- Endpoints -->
    <?php
    $docs = [
        [
            'method' => 'GET',
            'path'   => 'search.php',
            'title'  => 'Quick Search',
            'desc'   => 'Search products and tools simultaneously. Returns up to 8 products + 4 tools.',
            'params' => [['q','string','Yes','Min 2 chars search term']],
            'example_url' => $baseUrl . '/search.php?q=drill',
            'example_res' => '{"status":"success","data":{"query":"drill","count":3,"results":[{"id":1,"name":"Drill Chuck Key","type":"product","category":"Drill Parts"}]}}',
        ],
        [
            'method' => 'GET',
            'path'   => 'products.php',
            'title'  => 'List Products',
            'desc'   => 'Returns paginated product list with optional filtering by category, tool compatibility, brand, and stock.',
            'params' => [
                ['page','int','No','Page number (default: 1)'],
                ['limit','int','No','Results per page, max 100 (default: 20)'],
                ['category','int','No','Filter by Category ID'],
                ['tool','int','No','Filter by compatible Tool ID'],
                ['brand','string','No','Filter by brand name'],
                ['search','string','No','Search in name/SKU'],
                ['in_stock','bool','No','"1" for in-stock only'],
            ],
            'example_url' => $baseUrl . '/products.php?tool=1&in_stock=1&limit=5',
            'example_res' => '{"status":"success","data":{"page":1,"limit":5,"total":12,"pages":3,"products":[{"id":1,"name":"Drill Bit Set","sku":"DBT-001","price":"250.00","quantity":45}]}}',
        ],
        [
            'method' => 'GET',
            'path'   => 'products.php?id=1',
            'title'  => 'Single Product',
            'desc'   => 'Returns full product detail including compatible tools and last 5 stock movements.',
            'params' => [['id','int','Yes','Product ID']],
            'example_url' => $baseUrl . '/products.php?id=1',
            'example_res' => '{"status":"success","data":{"id":1,"name":"Drill Bit Set","compatible_tools":[{"id":1,"name":"Drill Machine"}],"stock_history":[{"action":"stock_in","quantity":50}]}}',
        ],
        [
            'method' => 'POST',
            'path'   => 'products.php',
            'title'  => 'Stock Check by SKU',
            'desc'   => 'Check stock availability by SKU code. Requires API key authentication.',
            'auth'   => true,
            'params' => [['sku','string','Yes','Product SKU code']],
            'example_url' => $baseUrl . '/products.php',
            'body'   => '{"sku": "DBT-001"}',
            'example_res' => '{"status":"success","data":{"id":1,"name":"Drill Bit Set","sku":"DBT-001","quantity":45,"in_stock":true,"low_stock":false}}',
        ],
    ];
    foreach ($docs as $doc):
        $mColor = $doc['method'] === 'GET' ? '#16a34a' : '#2563eb';
    ?>
    <div class="card" style="margin-bottom:1.25rem;" id="ep-<?= md5($doc['method'].$doc['path']) ?>">
        <div class="card-header">
            <div class="card-title" style="flex-wrap:wrap;gap:0.5rem;">
                <span style="background:<?= $mColor ?>;color:#fff;font-size:0.72rem;font-weight:800;padding:3px 10px;border-radius:20px;"><?= $doc['method'] ?></span>
                <code style="font-size:0.85rem;">/api/<?= htmlspecialchars($doc['path']) ?></code>
                <?php if (!empty($doc['auth'])): ?>
                <span style="background:rgba(217,119,6,0.12);color:#d97706;font-size:0.65rem;padding:2px 7px;border-radius:20px;"><i class="fas fa-lock"></i> Auth Required</span>
                <?php endif; ?>
            </div>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?= $doc['title'] ?></span>
        </div>
        <div class="card-body" style="font-size:0.875rem;">
            <p style="color:var(--text-medium);margin-bottom:1rem;"><?= $doc['desc'] ?></p>

            <!-- Parameters -->
            <?php if (!empty($doc['params'])): ?>
            <div style="margin-bottom:1rem;">
                <div style="font-weight:700;font-size:0.78rem;margin-bottom:0.5rem;color:var(--text-muted);text-transform:uppercase;">Parameters</div>
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead><tr style="background:var(--bg-card2);">
                        <th style="padding:0.4rem 0.65rem;text-align:left;border:1px solid var(--border-color);">Name</th>
                        <th style="padding:0.4rem 0.65rem;text-align:left;border:1px solid var(--border-color);">Type</th>
                        <th style="padding:0.4rem 0.65rem;text-align:left;border:1px solid var(--border-color);">Required</th>
                        <th style="padding:0.4rem 0.65rem;text-align:left;border:1px solid var(--border-color);">Description</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($doc['params'] as [$name, $type, $req, $desc]): ?>
                    <tr>
                        <td style="padding:0.4rem 0.65rem;border:1px solid var(--border-color);font-family:monospace;color:var(--primary);"><?= $name ?></td>
                        <td style="padding:0.4rem 0.65rem;border:1px solid var(--border-color);color:var(--text-muted);"><?= $type ?></td>
                        <td style="padding:0.4rem 0.65rem;border:1px solid var(--border-color);">
                            <span style="color:<?= $req==='Yes'?'var(--danger)':'var(--text-muted)' ?>;"><?= $req ?></span>
                        </td>
                        <td style="padding:0.4rem 0.65rem;border:1px solid var(--border-color);"><?= $desc ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Example -->
            <div style="font-weight:700;font-size:0.78rem;margin-bottom:0.4rem;color:var(--text-muted);text-transform:uppercase;">Example Request</div>
            <pre style="background:#0f172a;color:#e2e8f0;padding:0.75rem 1rem;border-radius:8px;font-size:0.75rem;overflow-x:auto;margin-bottom:0.75rem;"><code><?= $doc['method'] === 'POST' ? 'curl -X POST' : 'curl' ?> "<?= htmlspecialchars($doc['example_url']) ?>"<?= $doc['method']==='POST'?' \\
     -H "X-API-Key: '.API_KEY_DEMO.'" \\
     -H "Content-Type: application/json" \\
     -d \''.htmlspecialchars($doc['body'] ?? '{}').'\'' : '' ?></code></pre>

            <div style="font-weight:700;font-size:0.78rem;margin-bottom:0.4rem;color:var(--text-muted);text-transform:uppercase;">Example Response</div>
            <pre style="background:#0f172a;color:#86efac;padding:0.75rem 1rem;border-radius:8px;font-size:0.72rem;overflow-x:auto;"><code><?= htmlspecialchars($doc['example_res']) ?></code></pre>

            <div style="margin-top:0.75rem;">
                <a href="<?= htmlspecialchars($doc['example_url']) ?>" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fas fa-external-link-alt"></i> Test in Browser
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
