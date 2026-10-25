<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    <style>
        :root { --ink: #1f2933; --muted: #5b6573; --line: #dfe5ec; --brand: #16324f; --ok: #157347; --ko: #b02a37; --bg: #f3f6f9; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: var(--bg); }
        header { padding: 1.5rem 1rem; color: #fff; background: var(--brand); }
        header h1 { max-width: 46rem; margin: 0 auto; font-size: 1.4rem; }
        main { max-width: 46rem; margin: 1.5rem auto 3rem; padding: 0 1rem; }
        .card { margin-bottom: 1.25rem; padding: 1.25rem 1.5rem; background: #fff; border: 1px solid var(--line); border-radius: .75rem; }
        h2 { margin: 0 0 .75rem; font-size: 1.1rem; }
        p { margin: .25rem 0 .75rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        label { display: block; margin: .75rem 0 .25rem; font-weight: 600; }
        input[type=text], input[type=url], input[type=email], input[type=password], input[type=number], select { width: 100%; padding: .55rem .7rem; font: inherit; border: 1px solid #b8c2cc; border-radius: .4rem; }
        input:focus, select:focus, button:focus { outline: 3px solid #f4c95d; outline-offset: 1px; }
        .check { display: flex; gap: .5rem; align-items: flex-start; margin: .5rem 0; font-weight: 400; }
        .check input { margin-top: .3rem; }
        .grid { display: grid; gap: 0 1rem; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); }
        .languages-box { margin: 1rem 0 0; padding: .5rem 1rem; }
        .langs { display: grid; gap: 0 1rem; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        td { padding: .3rem .25rem; border-bottom: 1px solid var(--line); }
        td:last-child { color: var(--muted); text-align: right; word-break: break-all; }
        .ok { color: var(--ok); font-weight: 700; }
        .ko { color: var(--ko); font-weight: 700; }
        .alert { padding: .9rem 1.1rem; margin-bottom: 1.25rem; color: #58151c; background: #f8d7da; border: 1px solid #f1aeb5; border-radius: .6rem; }
        .alert ul { margin: .25rem 0 0; padding-left: 1.2rem; }
        .field-error { margin: .25rem 0 0; color: var(--ko); font-size: .875rem; }
        button, .button { display: inline-block; padding: .7rem 1.4rem; font: inherit; font-weight: 700; color: var(--brand); text-decoration: none; background: #f4c95d; border: 0; border-radius: .5rem; cursor: pointer; }
        button[disabled] { opacity: .5; cursor: not-allowed; }
        .button-secondary { color: var(--brand); background: #e6edf5; }
    </style>
</head>
<body>
    <header><h1>@yield('title')</h1></header>
    <main>
        @yield('content')
    </main>
</body>
</html>
