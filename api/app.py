from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import csv
import io
import json
import os
from pathlib import Path
import xgboost as xgb
import pandas as pd

BASE_DIR = Path(__file__).resolve().parent
PAYLOAD = json.loads((BASE_DIR / 'model_payload.json').read_text())
MODEL_PATH = BASE_DIR / 'xgb_top6_model.json'

FEATURES = PAYLOAD['features']
CATEGORICAL = PAYLOAD['categorical_features']
CAT_MAPS = PAYLOAD['cat_maps']
CAT_OPTIONS = PAYLOAD['cat_options']
THRESHOLD = PAYLOAD['threshold']
METRICS = PAYLOAD['metrics']
SAMPLE_INPUT = PAYLOAD['sample_input']

booster = xgb.Booster()
booster.load_model(str(MODEL_PATH))

app = Flask(__name__)
CORS(app)


def _encode_record(record: dict):
    values = []
    normalised = {}
    for feature in FEATURES:
        raw = record.get(feature, 'Missing')
        key = 'Missing' if raw in (None, '', 'nan', 'None') else str(raw)
        mapped = CAT_MAPS.get(feature, {}).get(key, 0)
        values.append(float(mapped))
        normalised[feature] = key
    return values, normalised


def _predict_one(record: dict):
    encoded, normalised = _encode_record(record)
    df = pd.DataFrame([encoded], columns=FEATURES)
    dmat = xgb.DMatrix(df)
    probability = float(booster.predict(dmat)[0])
    predicted_class = 1 if probability >= THRESHOLD else 0

    if probability >= 0.25:
        band = 'High risk'
    elif probability >= 0.10:
        band = 'Medium risk'
    else:
        band = 'Low risk'

    contribs = booster.predict(dmat, pred_contribs=True)[0]
    top_contributors = []
    for feature, contribution in zip(FEATURES, contribs[:-1]):
        top_contributors.append({
            'feature': feature,
            'selected_value': normalised.get(feature, ''),
            'contribution': round(float(contribution), 6)
        })
    top_contributors = sorted(top_contributors, key=lambda item: abs(item['contribution']), reverse=True)[:4]

    return {
        'probability': round(probability, 6),
        'predicted_class': predicted_class,
        'recommended_risk_band': band,
        'threshold_used': THRESHOLD,
        'top_contributors': top_contributors,
        'selected_values': normalised,
    }


@app.get('/health')
def health():
    return jsonify({'status': 'ok', 'model_metrics': METRICS, 'feature_count': len(FEATURES)})


@app.get('/metadata')
def metadata():
    fields = []
    for feature in CATEGORICAL:
        fields.append({
            'name': feature,
            'label': feature,
            'type': 'select',
            'options': CAT_OPTIONS.get(feature, []),
        })

    return jsonify({
        'features': fields,
        'metrics': METRICS,
        'sample_input': SAMPLE_INPUT,
        'threshold': THRESHOLD,
        'note': PAYLOAD.get('feature_note', '')
    })


@app.post('/predict')
def predict():
    payload = request.get_json(force=True, silent=True) or {}
    return jsonify(_predict_one(payload))


@app.post('/predict-batch')
def predict_batch():
    if 'file' not in request.files:
        return jsonify({'error': 'No file uploaded.'}), 400

    uploaded = request.files['file']
    text = uploaded.read().decode('utf-8', errors='replace')
    reader = csv.DictReader(io.StringIO(text))

    rows = []
    for row in reader:
        pred = _predict_one(row)
        out = dict(row)
        out['predicted_probability'] = pred['probability']
        out['predicted_class'] = pred['predicted_class']
        out['risk_band'] = pred['recommended_risk_band']
        rows.append(out)

    if not rows:
        return jsonify({'error': 'The uploaded CSV did not contain any rows.'}), 400

    out_io = io.StringIO()
    writer = csv.DictWriter(out_io, fieldnames=list(rows[0].keys()))
    writer.writeheader()
    writer.writerows(rows)

    return send_file(
        io.BytesIO(out_io.getvalue().encode('utf-8')),
        mimetype='text/csv',
        as_attachment=True,
        download_name='orange_top6_dashboard_predictions.csv'
    )


if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5001))
    app.run(host='0.0.0.0', port=port, debug=False)
