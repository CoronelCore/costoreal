#!/usr/bin/env python3
"""Measure real loopback write/close HTTP; each pair owns a fresh synthetic DB copy."""
from pathlib import Path
import argparse, base64, http.cookiejar, json, math, os, re, secrets
import shutil, signal, socket, sqlite3, statistics, subprocess, sys, tempfile, time
from contextlib import closing
import urllib.error, urllib.parse, urllib.request
from decimal import Decimal

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--samples', type=int, default=20)
parser.add_argument('--lines', type=int, nargs='+', choices=[10, 100, 1000], default=[10, 100, 1000])
parser.add_argument('--modes', nargs='+', choices=['same', 'varied'], default=['same', 'varied'])
args = parser.parse_args()
if not 3 <= args.samples <= 100:
    parser.error('Usa entre 3 y 100 muestras; p95 solo se informa a partir de 20.')
if (root / 'bootstrap/cache/config.php').exists():
    parser.error('El perfil exige configuración sin cache y no modifica bases existentes.')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def request(opener, url, headers=None, data=None, method=None):
    started = time.perf_counter()
    try:
        response = opener.open(urllib.request.Request(url, data=data, headers=headers or {}, method=method), timeout=180)
    except urllib.error.HTTPError as error:
        if error.code not in [302, 303]:
            raise
        response = error
    with response:
        raw = response.read()
        return raw, round((time.perf_counter() - started) * 1000, 2), response.status, dict(response.headers)


def connect(database):
    return sqlite3.connect('file:' + str(database) + '?mode=ro', uri=True)


def verify(database, fixture, runs, lines, previous=None):
    with closing(connect(database)) as db:
        revision, state = db.execute('select revision, estado from trips where id=?', [fixture['trip_id']]).fetchone()
        assert (revision, state) == (runs + 1, 'finalizado' if runs == 2 else 'activo')
        records = db.execute('select * from cost_runs order by id').fetchall()
        assert len(records) == runs
        if previous is not None:
            assert records[0] == previous, 'La primera ejecución debe permanecer inmutable.'
        assert db.execute('select count(*) from cost_allocations').fetchone()[0] == runs * fixture['allocation_count_per_run']
        assert db.execute('select count(*) from cost_run_lines').fetchone()[0] == runs * lines
        total = sum(Decimal(row[0]) for row in db.execute('select costo_final_total_clp from products'))
        assert total == Decimal(fixture['expected_total'])
        for run_id, result in db.execute('select id,result from cost_runs'):
            result = json.loads(result)
            assert Decimal(result['total']) == total and result['allocation_count'] == fixture['allocation_count_per_run']
            # Fixture amounts are bounded whole pesos; integer SUM is exact for this synthetic dataset.
            allocated = db.execute('select expense_id,count(*),sum(cast(amount as integer)) from cost_allocations where cost_run_id=? group by expense_id', [run_id]).fetchall()
            expenses = dict(db.execute('select id,monto_clp from expenses'))
            assert len(allocated) == len(expenses)
            assert all(count == lines and amount == Decimal(expenses[expense]) for expense, count, amount in allocated)
            for trace, in db.execute('select trace from cost_allocations where cost_run_id=? order by id limit 3', [run_id]):
                assert isinstance(json.loads(trace), dict)
        assert db.execute('pragma integrity_check').fetchone()[0] == 'ok'
        assert not db.execute('pragma foreign_key_check').fetchall()
        return records[0]


def stop(process):
    if process.poll() is None:
        os.killpg(os.getpgid(process.pid), signal.SIGTERM)
    try:
        process.wait(timeout=10)
    except subprocess.TimeoutExpired:
        os.killpg(os.getpgid(process.pid), signal.SIGKILL)
        process.wait(timeout=10)
        raise RuntimeError('El servicio temporal no terminó con SIGTERM.') from None


report = {'memory_limit': '128M', 'scope': 'HTTP serial loopback con sesión y CSRF reales, sin seguir redirect dentro de la muestra. Dos pares de calentamiento; cada par restaura una DB sintética autenticada nueva y arranca un proceso nuevo. No es carga concurrente ni SLA de producción. Heap PHP no es RSS. Conciliación completa de totales/conteos por ejecución/gasto, muestra de trazas; el hash de todas las trazas se verifica en profile-costing.php.', 'rows': []}
with tempfile.TemporaryDirectory(prefix='costoreal-http-write-profile-') as temporary:
    directory = Path(temporary).resolve()
    directory.chmod(0o700)
    password = secrets.token_urlsafe(24)
    for lines in args.lines:
        for mode in args.modes:
            env = dict(os.environ, COSTOREAL_PROFILE_PASSWORD=password, CACHE_STORE='array', BCRYPT_ROUNDS='4', APP_DEBUG='false')
            env.pop('DB_URL', None)
            env.pop('PHP_CLI_SERVER_WORKERS', None)
            env['LOG_CHANNEL'] = 'stderr'
            fixture = json.loads(subprocess.check_output([str(root / 'bin/php'), str(root / 'tools/profile-http-write-fixture.php'), str(directory), str(lines), mode], cwd=root, env=env, text=True))
            database = Path(fixture['database'])
            assert database.parent == directory and database.is_file()
            with socket.socket() as available:
                available.bind(('127.0.0.1', 0))
                port = available.getsockname()[1]
            base = f'http://127.0.0.1:{port}'
            log_path = directory / f'metrics-{lines}-{mode}.log'
            env.update(DB_CONNECTION='sqlite', DB_DATABASE=str(database), APP_URL=base, APP_ENV='local', SESSION_DRIVER='database',
                       SESSION_COOKIE='costoreal_write_profile_' + secrets.token_hex(8), APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                       COSTOREAL_PERFORMANCE_ENABLED='true', COSTOREAL_PERFORMANCE_LOG_ALL='true', COSTOREAL_PERFORMANCE_LOG_PATH=str(log_path),
                       COSTOREAL_PRIVATE_STORAGE_ROOT=str(directory / 'private'), COSTOREAL_PUBLIC_STORAGE_ROOT=str(directory / 'public'))
            cookies = urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
            opener = urllib.request.build_opener(cookies, NoRedirect())
            samples = {'trips.expenses.update': [], 'trips.close': []}
            baseline = directory / f'baseline-{lines}-{mode}.sqlite'
            version = None
            for pair in range(args.samples + 2):
                if pair:
                    shutil.copyfile(baseline, database)
                with (directory / f'server-{lines}-{mode}.log').open('ab') as output:
                    process = subprocess.Popen([str(root / 'bin/php'), '-d', 'memory_limit=128M', '-S', f'127.0.0.1:{port}', str(root / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], cwd=root / 'public', env=env,
                                               stdin=subprocess.DEVNULL, stdout=output, stderr=output, start_new_session=True)
                    try:
                        limit = time.monotonic() + 30
                        while True:
                            if process.poll() is not None:
                                raise RuntimeError('No inició el servicio temporal.')
                            try:
                                raw, _, _, _ = request(opener, base + '/login')
                                break
                            except urllib.error.URLError:
                                if time.monotonic() >= limit:
                                    raise RuntimeError('El servicio temporal no respondió.') from None
                                time.sleep(.1)
                        if pair == 0:
                            page = json.loads(re.search(r'<script[^>]*type="application/json"[^>]*>(.*?)</script>', raw.decode(), re.S).group(1))
                            version = page['version']
                            token = next(urllib.parse.unquote(c.value) for c in cookies.cookiejar if c.name == 'XSRF-TOKEN')
                            _, _, status, _ = request(opener, base + '/login', {'X-XSRF-TOKEN': token, 'Content-Type': 'application/x-www-form-urlencoded'}, urllib.parse.urlencode({'email': 'performance@example.test', 'password': password}).encode())
                            assert status == 302
                        # Two read warmups outside the measured writes, and verify authentication.
                        for _ in range(2):
                            raw, _, status, _ = request(opener, base + f'/trips/{fixture["trip_id"]}', {'X-Inertia': 'true', 'X-Inertia-Version': version, 'Accept': 'application/json'})
                            assert status == 200 and json.loads(raw)['props']['trip']['revision'] == 1
                        if pair == 0:
                            with closing(connect(database)) as source, closing(sqlite3.connect(baseline)) as target:
                                source.backup(target)
                            baseline.chmod(0o600)
                        token = next(urllib.parse.unquote(c.value) for c in cookies.cookiejar if c.name == 'XSRF-TOKEN')
                        headers = {'X-XSRF-TOKEN': token, 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'text/html', 'X-Inertia': 'true', 'X-Inertia-Version': version}
                        previous = None
                        for route, method, path, data, runs in [
                            ('trips.expenses.update', 'PUT', f'/trips/{fixture["trip_id"]}/expenses/{fixture["expense_id"]}', {'expected_revision': 1, 'concepto': 'Gasto actualizado', 'moneda': 'CLP', 'monto': fixture['update_amount'], 'rate_mode': 'estimated'}, 1),
                            ('trips.close', 'PATCH', f'/trips/{fixture["trip_id"]}/close', {'expected_revision': 2}, 2)]:
                            logs = sorted(directory.glob(f'metrics-{lines}-{mode}-*.log'))
                            log = logs[-1]
                            offset = log.stat().st_size
                            raw, elapsed, status, response_headers = request(opener, base + path, headers, urllib.parse.urlencode(data).encode(), method)
                            assert status == 303 and response_headers.get('Location') == base + f'/trips/{fixture["trip_id"]}'
                            with log.open() as handle:
                                handle.seek(offset)
                                matching = [json.loads(row)['context'] for row in handle if row.strip()]
                            matching = [row for row in matching if row.get('route') == route]
                            assert len(matching) == 1
                            metric = matching[0]
                            assert metric['status'] == status and metric['response_bytes'] == len(raw), str({'observed_status': metric['status'], 'http_status': status, 'observed_bytes': metric['response_bytes'], 'http_bytes': len(raw)})
                            previous = verify(database, fixture, runs, lines, previous)
                            if pair >= 2:
                                samples[route].append({'http_ms': elapsed, 'http_status': status, **metric})
                        print(json.dumps({'lines': lines, 'mode': mode, 'pair': pair + 1, 'reconciled': True}), file=sys.stderr, flush=True)
                    finally:
                        stop(process)
            for route, rows in samples.items():
                durations = sorted(row['http_ms'] for row in rows)
                row = {'route': route, 'lines': lines, 'expenses': lines // 2, 'mode': mode, 'sample_count': len(rows),
                       'http_median_ms': statistics.median(durations), 'http_empirical_p95_ms': durations[math.ceil(.95 * len(durations)) - 1] if len(durations) >= 20 else None,
                       'queries_min': min(row['query_count'] for row in rows), 'queries_max': max(row['query_count'] for row in rows),
                       'php_heap_max_mb': max(row['process_peak_mb'] for row in rows), 'budget_exceeded_samples': sum(bool(row['exceeded']) for row in rows),
                       'all_reconciled': True, 'samples': rows}
                report['rows'].append(row)
            database.unlink()
            baseline.unlink()
print(json.dumps(report, ensure_ascii=False, indent=2))
