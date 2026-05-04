<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/helpers.php';

$modelRows = load_csv_assoc(__DIR__ . '/data/model_comparison_table.csv');
$featureRows = load_csv_assoc(__DIR__ . '/data/top_feature_table.csv');
$imbalanceRows = load_csv_assoc(__DIR__ . '/data/imbalance_strategy_table.csv');
$featureSelectionRows = load_csv_assoc(__DIR__ . '/data/feature_selection_table.csv');
$modelPayload = load_json_file(__DIR__ . '/data/model_payload.json');
$features = $modelPayload['features'] ?? [];
$numericFeatures = $modelPayload['numeric_features'] ?? [];
$categoricalFeatures = $modelPayload['categorical_features'] ?? [];
$medians = $modelPayload['medians'] ?? [];
$catOptions = $modelPayload['cat_options'] ?? [];
$metrics = $modelPayload['metrics'] ?? [];
$sampleInput = $modelPayload['sample_input'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orange Churn Prediction Dashboard</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="hero">
    <div>
      <h1>Orange Churn Prediction Dashboard</h1>
      <p>A hostable PHP dashboard with a Python prediction API for manual and batch churn scoring.</p>
    </div>
    <div class="hero-badges">
      <span>Frontend: PHP</span>
      <span>Backend: Python API</span>
      <span>Data: Orange KDD 2009</span>
    </div>
  </header>

  <nav class="nav-tabs">
    <a href="#predict">Predict</a>
    <a href="#batch">Batch CSV</a>
    <a href="#results">Results</a>
    <a href="#insights">Insights</a>
    <a href="#install">Install</a>
  </nav>

  <main class="container">
    <section id="predict" class="card">
      <h2>Manual prediction</h2>
      <p>Enter values for the selected model features below. Because the Orange dataset uses anonymised variables, the form uses feature IDs such as <strong>Var217</strong> and <strong>Var126</strong>.</p>
      <div class="meta-grid">
        <div><strong>API URL:</strong> <?= htmlspecialchars(API_BASE_URL) ?></div>
        <div><strong>Threshold:</strong> <?= htmlspecialchars((string)($metrics['threshold'] ?? '')) ?></div>
        <div><strong>Demo model ROC-AUC:</strong> <?= htmlspecialchars((string)($metrics['roc_auc'] ?? '')) ?></div>
        <div><strong>Demo model PR-AUC:</strong> <?= htmlspecialchars((string)($metrics['pr_auc'] ?? '')) ?></div>
      </div>

      <form id="predictForm" class="form-grid">
        <?php foreach ($numericFeatures as $feature): ?>
          <label>
            <span><?= htmlspecialchars($feature) ?></span>
            <input type="number" step="any" name="<?= htmlspecialchars($feature) ?>" value="<?= htmlspecialchars((string)($medians[$feature] ?? '')) ?>">
          </label>
        <?php endforeach; ?>

        <?php foreach ($categoricalFeatures as $feature): ?>
          <label>
            <span><?= htmlspecialchars($feature) ?></span>
            <select name="<?= htmlspecialchars($feature) ?>">
              <?php foreach (($catOptions[$feature] ?? []) as $option): ?>
                <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endforeach; ?>
      </form>

      <div class="button-row">
        <button id="sampleBtn" class="secondary">Load sample values</button>
        <button id="predictBtn">Predict churn risk</button>
      </div>

      <div id="predictionResult" class="result-box hidden"></div>
    </section>

    <section id="batch" class="card">
      <h2>Batch prediction with CSV upload</h2>
      <p>Upload a CSV file containing the same selected feature columns used by the manual form. The API will return a downloadable CSV with predicted probability, class, and risk band.</p>
      <div class="button-row">
        <a class="button-link secondary" href="data/template_input.csv" download>Download CSV template</a>
      </div>
      <form id="batchForm" enctype="multipart/form-data" class="inline-form">
        <input type="file" id="batchFile" accept=".csv">
        <button id="batchBtn">Run batch prediction</button>
      </form>
      <div id="batchStatus" class="status"></div>
    </section>

    <section id="results" class="card">
      <h2>Project result tables</h2>
      <p>These tables are loaded from the dissertation result CSV files so the dashboard remains tied to the actual analysis outputs.</p>

      <h3>Model comparison</h3>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <?php foreach (array_keys($modelRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?>
          </tr></thead>
          <tbody>
          <?php foreach ($modelRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3>Top features</h3>
      <div class="table-wrap small-table">
        <table>
          <thead><tr><?php foreach (array_keys($featureRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($featureRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3>Imbalance strategies</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><?php foreach (array_keys($imbalanceRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($imbalanceRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3>Feature-selection strategies</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><?php foreach (array_keys($featureSelectionRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($featureSelectionRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section id="insights" class="card">
      <h2>How to interpret the prediction</h2>
      <div class="insight-grid">
        <div>
          <h3>What the probability means</h3>
          <p>The prediction API returns a churn probability between 0 and 1. A higher value means the record is more similar to the churners seen during model training.</p>
        </div>
        <div>
          <h3>Risk bands</h3>
          <p><strong>High risk</strong> if probability ≥ 0.25, <strong>Medium risk</strong> if probability is between 0.10 and 0.249, and <strong>Low risk</strong> below 0.10.</p>
        </div>
        <div>
          <h3>Important caution</h3>
          <p>The Orange variables are anonymous, so this dashboard supports prioritisation and review, not causal business decisions tied to named real-world drivers.</p>
        </div>
      </div>
    </section>

    <section id="install" class="card">
      <h2>Installation summary</h2>
      <ol>
        <li>Run the Python API from the <code>api/</code> folder.</li>
        <li>Update <code>frontend/config.php</code> if your API runs on a different URL.</li>
        <li>Upload the <code>frontend/</code> folder to your PHP host and visit <code>index.php</code>.</li>
      </ol>
      <p>See <code>README.md</code> in the package root for full local and hosted deployment instructions.</p>
    </section>
  </main>

  <script>
    window.APP_CONFIG = {
      apiBaseUrl: <?= json_encode(API_BASE_URL) ?>,
      sampleInput: <?= json_encode($sampleInput) ?>
    };
  </script>
  <script src="assets/app.js"></script>
</body>
</html>
