#!/usr/bin/env python3
"""Profile real localhost HTTP against disposable synthetic SQLite databases only."""
from pathlib import Path
import argparse, base64, datetime, http.cookiejar, json, math, os, re, secrets
import signal, socket, statistics, subprocess, sys, tempfile, time, urllib.error, urllib.parse, urllib.request
from decimal import Decimal

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--samples', type=int, default=20)
parser.add_argument('--lines', type=int, nargs='+', choices=[10, 100, 1000], default=[10, 100, 1000])
args = parser.parse_args()
if not 3 <= args.samples <= 100:
    parser.error('--samples admite 3 a 100; usa al menos 20 para el p95 local.')
if (root / 'bootstrap/cache/config.php').exists():
    parser.error('Usa un checkout de ensayo sin config cache; el perfil no modifica la configuración guardada.')

def request(opener, url, headers=None, data=None):
    started = time.perf_counter()
    with opener.open(urllib.request.Request(url, data=data, headers=headers or {}), timeout=180) as response:
        raw = response.read()
        return raw, round((time.perf_counter() - started) * 1000, 2), response.status


report = {'scope': 'HTTP serial real, PHP 8.5/Laravel 13, sesión en SQLite; dos calentamientos y muestras indicadas. HTTP incluye bootstrap/transporte; middleware mide construcción del servidor. Datos sintéticos: 50% con precio, un gasto por dos productos y 60 snapshots históricos.', 'rows': [], 'errors': []}
with tempfile.TemporaryDirectory(prefix='costoreal-http-profile-') as temporary:
    directory = Path(temporary)
    directory.chmod(0o700)
    password = secrets.token_urlsafe(24)
    for lines in args.lines:
        environment = dict(os.environ, COSTOREAL_PROFILE_PASSWORD=password, CACHE_STORE='array', BCRYPT_ROUNDS='4', APP_DEBUG='false')
        fixture = json.loads(subprocess.check_output([str(root / 'bin/php'), str(root / 'tools/profile-http-fixture.php'), str(directory), str(lines)], cwd=root, env=environment, text=True))
        with socket.socket() as available:
            available.bind(('127.0.0.1', 0))
            port = available.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        environment.update(DB_CONNECTION='sqlite', DB_DATABASE=fixture['database'], APP_URL=base, APP_ENV='local', SESSION_DRIVER='database',
                           SESSION_COOKIE='costoreal_profile_' + secrets.token_hex(8), APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                           COSTOREAL_PERFORMANCE_ENABLED='true', COSTOREAL_PERFORMANCE_LOG_ALL='true', COSTOREAL_PERFORMANCE_LOG_PATH=str(directory / f'metrics-{lines}.log'),
                           COSTOREAL_PRIVATE_STORAGE_ROOT=str(directory / 'private'), COSTOREAL_PUBLIC_STORAGE_ROOT=str(directory / 'public'))
        with (directory / f'server-{lines}.log').open('wb') as output:
            process = subprocess.Popen([str(root / 'bin/php'), 'artisan', 'serve', '--host=127.0.0.1', f'--port={port}', '--no-reload'], cwd=root, env=environment,
                                       stdin=subprocess.DEVNULL, stdout=output, stderr=output, start_new_session=True)
            try:
                cookie_handler = urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
                opener = urllib.request.build_opener(cookie_handler)
                limit = time.monotonic() + 30
                while True:
                    if process.poll() is not None:
                        raise RuntimeError('No inició el servicio temporal.')
                    try:
                        request(opener, base + '/login')
                        break
                    except urllib.error.URLError:
                        if time.monotonic() >= limit:
                            raise RuntimeError('El servicio temporal no respondió.') from None
                        time.sleep(.1)
                # Attach the known handler explicitly; do not rely on urllib handler order.
                raw, _, _ = request(opener, base + '/login')
                page = json.loads(re.search(r'<script[^>]*type="application/json"[^>]*>(.*?)</script>', raw.decode(), re.S).group(1))
                token = next(urllib.parse.unquote(cookie.value) for cookie in cookie_handler.cookiejar if cookie.name == 'XSRF-TOKEN')
                data = urllib.parse.urlencode({'email': 'performance@example.test', 'password': password}).encode()
                request(opener, base + '/login', {'X-XSRF-TOKEN': token, 'Content-Type': 'application/x-www-form-urlencoded'}, data)
                inertia = {'X-Inertia': 'true', 'X-Inertia-Version': page['version'], 'Accept': 'application/json'}
                trip, company = fixture['trip_id'], fixture['company_id']
                cases = []
                for count in [1, 20, 200]:
                    date = (datetime.date(2026, 1, 1) + datetime.timedelta(days=count - 1)).isoformat()
                    cases.extend([('trips.index', f'/trips?to={date}', count, inertia), ('analytics.index', f'/analytics?to={date}', count, inertia)])
                cases.extend([('trips.show', f'/trips/{trip}', 1, inertia), ('companies.show', f'/companies/{company}', 200, inertia),
                              ('trips.cost-runs.index', f'/trips/{trip}/cost-runs', 1, {'Accept': 'application/json'}),
                              ('trips.audit.index', f'/trips/{trip}/audit-events', 1, {'Accept': 'application/json'}),
                              ('trips.scenarios.index', f'/trips/{trip}/scenarios', 1, {'Accept': 'application/json'}),
                              ('trips.export.excel', f'/trips/{trip}/export/excel', 1, {}), ('trips.export.pdf', f'/trips/{trip}/export/pdf', 1, {})])
                for route, path, count, headers in cases:
                    try:
                        request(opener, base + path, headers)
                        request(opener, base + path, headers)
                        samples = []
                        for _ in range(args.samples):
                            log = sorted(directory.glob(f'metrics-{lines}-*.log'))[-1]
                            offset = log.stat().st_size
                            raw, elapsed, status = request(opener, base + path, headers)
                            with log.open() as handle:
                                handle.seek(offset)
                                entries = [json.loads(row)['context'] for row in handle if row.strip()]
                            matching = [row for row in entries if row.get('route') == route]
                            if len(matching) != 1:
                                raise RuntimeError('Falta una muestra de observación por respuesta.')
                            metrics = matching[0]
                            if metrics['status'] != status or status != 200 or metrics['response_bytes'] != len(raw):
                                raise RuntimeError('Estado o tamaño de respuesta no concilia.')
                            if route in ['trips.index', 'analytics.index', 'trips.show', 'companies.show']:
                                props = json.loads(raw)['props']
                                total = props['estadisticas']['costo_total'] if route == 'trips.show' else props['metrics']['total']
                                if Decimal(total) != Decimal('10.20') * lines * count:
                                    raise RuntimeError('El total monetario no concilia.')
                                if route == 'analytics.index' and (props['projection']['lines'] != lines * count or props['projection']['priced_lines'] != lines * count // 2):
                                    raise RuntimeError('La cobertura de precios no concilia.')
                            elif route == 'trips.cost-runs.index':
                                history = json.loads(raw)
                                if history['total'] != 60 or len(history['data']) != 20:
                                    raise RuntimeError('El historial paginado no concilia.')
                            elif route == 'trips.export.excel' and not raw.startswith(b'PK'):
                                raise RuntimeError('La exportación no es un XLSX.')
                            elif route == 'trips.export.pdf' and not raw.startswith(b'%PDF-'):
                                raise RuntimeError('La exportación no es un PDF.')
                            samples.append({'http_ms': elapsed, **metrics})
                        durations = sorted(row['http_ms'] for row in samples)
                        row = {'route': route, 'imports_in_scope': count, 'lines_per_import': lines, 'samples': samples,
                               'http_median_ms': statistics.median(durations), 'http_empirical_p95_ms': durations[math.ceil(.95 * len(durations)) - 1] if len(durations) >= 20 else None,
                               'queries_min': min(row['query_count'] for row in samples), 'queries_max': max(row['query_count'] for row in samples),
                               'response_bytes_max': max(row['response_bytes'] for row in samples),
                               'process_peak_mb_max': max(row['process_peak_mb'] for row in samples), 'all_reconciled': True}
                        report['rows'].append(row)
                        print(json.dumps({key: value for key, value in row.items() if key != 'samples'}), file=sys.stderr, flush=True)
                    except (urllib.error.URLError, RuntimeError) as error:
                        reason = f'HTTP {error.code}' if isinstance(error, urllib.error.HTTPError) else str(error)
                        report['errors'].append({'route': route, 'lines_per_import': lines, 'reason': reason})
            finally:
                if process.poll() is None:
                    os.killpg(os.getpgid(process.pid), signal.SIGTERM)
                process.wait(timeout=10)
                if getattr(process, 'returncode', None) is None:
                    raise RuntimeError('No terminó el servicio temporal.')
print(json.dumps(report, ensure_ascii=False, indent=2))
sys.exit(1 if report['errors'] else 0)
