
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang Maintenance</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #eaf3ff, #f7f9fc);
            color: #243247;
        }

        .container {
            width: 100%;
            max-width: 560px;
            padding: 48px 35px;
            background: #ffffff;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 15px 50px rgba(29, 64, 110, 0.10);
        }

        .icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f1ff;
            border-radius: 50%;
            font-size: 48px;
            animation: float 3s ease-in-out infinite;
        }

        h1 {
            font-size: 29px;
            margin-bottom: 16px;
            color: #173b70;
        }

        .status {
            display: inline-block;
            padding: 8px 16px;
            margin-bottom: 22px;
            border-radius: 30px;
            background: #fff3d6;
            color: #956500;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        p {
            font-size: 15px;
            line-height: 1.8;
            color: #68768a;
        }

        .notice {
            margin-top: 25px;
            padding: 16px;
            background: #f4f7fb;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.7;
            color: #53657c;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #edf0f5;
            font-size: 12px;
            color: #94a0b1;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @media (max-width: 480px) {
            .container {
                padding: 36px 22px;
            }

            h1 {
                font-size: 24px;
            }

            p {
                font-size: 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .icon {
                animation: none;
            }
        }
    </style>
</head>
<body>

    <main class="container">
        <div class="icon" aria-hidden="true">🛠️</div>

        <div class="status">● DALAM PEMELIHARAAN</div>

        <h1>Sistem Sedang Maintenance</h1>

        <p>
            Mohon maaf atas ketidaknyamanan ini.
            Saat ini sistem sedang menjalani pemeliharaan
            dan peningkatan layanan untuk memastikan
            performa serta keamanan yang lebih baik.
        </p>

        <div class="notice">
            <strong>Informasi:</strong><br>
            Silakan mencoba kembali beberapa saat lagi.
            Terima kasih atas pengertian dan kesabaran Anda.
        </div>

        <div class="footer">
            &copy; <span id="year"></span> Properti milik Bank Ekadharma
        </div>
    </main>

    <script>
        document.getElementById("year").textContent =
            new Date().getFullYear();
    </script>

</body>
</html>
