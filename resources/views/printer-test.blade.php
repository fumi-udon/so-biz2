<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Söya. — Epson sanity check</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/epos-2.27.0.js') }}"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-6">
    <div class="max-w-lg w-full text-center space-y-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Infra proof · isolated route</p>
            <h1 class="mt-2 text-2xl font-semibold text-white">Epson TM-m30II</h1>
            <p class="mt-1 text-sm text-slate-400">192.168.1.101:8043 · HTTPS · dev <code class="text-amber-200/90">local_printer</code></p>
        </div>

        <button
            type="button"
            id="print-btn"
            class="w-full rounded-2xl bg-amber-500 px-8 py-5 text-lg font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition hover:bg-amber-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-300/60 disabled:cursor-not-allowed disabled:opacity-40"
        >
            Imprimer (テスト印刷)
        </button>

        <div
            id="status"
            class="min-h-[4.5rem] rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-left text-sm leading-relaxed text-slate-200"
            role="status"
            aria-live="polite"
        >
            Prêt. Appuyez sur le bouton pour envoyer « HELLO FROM SOYA TEST PAGE » + coupe.
        </div>
    </div>

    <script>
        (function () {
            var IP = '192.168.1.101';
            var PORT = 8043;
            var DEVICE_ID = 'local_printer';
            var BODY = 'HELLO FROM SOYA TEST PAGE\n\n';

            var btn = document.getElementById('print-btn');
            var statusEl = document.getElementById('status');

            function escHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function setStatus(html) {
                statusEl.innerHTML = html;
            }

            function running(msg) {
                btn.disabled = true;
                setStatus(msg);
            }

            function ready(msg) {
                btn.disabled = false;
                if (msg) setStatus(msg);
            }

            function alertErr(title, detail) {
                window.alert(title + (detail ? '\n\n' + detail : ''));
            }

            btn.addEventListener('click', function () {
                if (!window.epson || typeof window.epson.ePOSDevice !== 'function') {
                    setStatus('<span class="text-rose-400 font-medium">ePOS indéfini</span> — <code>window.epson.ePOSDevice</code> introuvable (SDK chargé ?).');
                    alertErr('ePOS 未定義', 'window.epson.ePOSDevice が見つかりません。');
                    return;
                }

                var eposDev = new window.epson.ePOSDevice();
                running('<span class="text-amber-300">Connexion…</span> (SSL, port ' + PORT + ')');

                eposDev.connect(IP, PORT, function (connectResult) {
                    if (connectResult !== 'OK' && connectResult !== 'SSL_CONNECT_OK') {
                        setStatus('<span class="text-rose-400 font-medium">Échec connexion</span> — code : <code>' + String(connectResult) + '</code>');
                        alertErr('接続失敗', String(connectResult));
                        ready();
                        return;
                    }

                    setStatus(
                        '<span class="text-emerald-400 font-medium">Connexion OK</span> (' + String(connectResult) +
                            ') — ouverture du périphérique…'
                    );

                    eposDev.createDevice(DEVICE_ID, eposDev.DEVICE_TYPE_PRINTER, { crypto: true }, function (printer, code) {
                        if (!printer || code !== 'OK') {
                            setStatus(
                                '<span class="text-rose-400 font-medium">Échec ouverture imprimante</span> — <code>' +
                                    String(code || 'null') +
                                    '</code>'
                            );
                            alertErr('プリンターオープン失敗', String(code || 'device null'));
                            try {
                                eposDev.disconnect();
                            } catch (e) {}
                            ready();
                            return;
                        }

                        var finished = false;

                        function cleanupAfterPrint() {
                            if (finished) return;
                            finished = true;
                            try {
                                eposDev.deleteDevice(printer, function () {
                                    try {
                                        eposDev.disconnect();
                                    } catch (e2) {}
                                    ready();
                                });
                            } catch (e) {
                                try {
                                    eposDev.disconnect();
                                } catch (e2) {}
                                ready();
                            }
                        }

                        printer.onreceive = function (res /* , sq */) {
                            if (res && res.success) {
                                setStatus(
                                    '<span class="text-emerald-400 font-medium">Impression terminée</span> — status ' +
                                        String(res.status) +
                                        ', code <code>' +
                                        String(res.code || '') +
                                        '</code>'
                                );
                                window.alert('印刷完了（success, status=' + String(res.status) + '）');
                            } else {
                                var c = res && res.code != null ? String(res.code) : 'EPOS_FAIL';
                                var st = res && res.status != null ? String(res.status) : '';
                                setStatus('<span class="text-rose-400 font-medium">Erreur d’impression</span> — <code>' + c + '</code> status ' + st);
                                window.alert('印刷エラー: ' + c + (st ? ' / status=' + st : ''));
                            }
                            cleanupAfterPrint();
                        };

                        printer.onerror = function (err /* , sq */) {
                            var st = err && err.status != null ? String(err.status) : '';
                            var txt = err && err.responseText ? String(err.responseText).slice(0, 500) : '';
                            setStatus(
                                '<span class="text-rose-400 font-medium">Erreur transport</span> — status ' +
                                    st +
                                    (txt ? '<br><span class="text-slate-400 break-all">' + escHtml(txt) + '</span>' : '')
                            );
                            alertErr('通信エラー', 'status=' + st + (txt ? '\n' + txt : ''));
                            cleanupAfterPrint();
                        };

                        try {
                            printer.addText(BODY);
                            printer.addCut();
                            printer.send();
                        } catch (e) {
                            setStatus('<span class="text-rose-400 font-medium">Exception JS</span> — ' + String(e && e.message ? e.message : e));
                            alertErr('JS 例外', String(e && e.message ? e.message : e));
                            cleanupAfterPrint();
                        }
                    });
                });
            });
        })();
    </script>
</body>
</html>
