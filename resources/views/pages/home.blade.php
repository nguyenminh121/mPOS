<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onivaco</title>
    <style>
        :root {
            --bg: #f7f8fc;
            --card: #ffffff;
            --text: #1a2540;
            --muted: #5b6b8c;
            --brand: #0f766e;
            --brand-2: #0ea5e9;
            --ring: rgba(14, 165, 233, 0.2);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background: radial-gradient(1200px 600px at 10% -20%, #dff7f3, transparent),
                        radial-gradient(900px 500px at 90% 0%, #dff1ff, transparent),
                        var(--bg);
        }

        .wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(920px, 100%);
            background: var(--card);
            border: 1px solid #e7ebf3;
            border-radius: 20px;
            box-shadow: 0 20px 40px -20px rgba(18, 37, 68, 0.25);
            padding: 40px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(28px, 5vw, 46px);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.6;
            max-width: 65ch;
        }

        .actions {
            margin-top: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid transparent;
            transition: 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(120deg, var(--brand), var(--brand-2));
            color: #fff;
            box-shadow: 0 8px 20px -10px var(--ring);
        }

        .btn-primary:hover { transform: translateY(-1px); }

        .btn-outline {
            border-color: #d8e2f0;
            color: var(--text);
            background: #fff;
        }

        .meta {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px dashed #dbe3ef;
            color: #7183a8;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .card { padding: 24px; }
            p { font-size: 16px; }
        }
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <h1>Onivaco ERP Platform</h1>
        <p>
            Nền tảng vận hành cho multi-tenant và sẵn sàng mở rộng multi-store: quản lý bán hàng, kho,
            hóa đơn, công nợ và cấu hình theo từng doanh nghiệp.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="/login">Đăng nhập</a>
            <a class="btn btn-outline" href="/dashboard">Đi đến Dashboard</a>
        </div>

        <div class="meta">
            Landing page tạm thời cho giai đoạn triển khai nền tảng.
        </div>
    </section>
</main>
</body>
</html>