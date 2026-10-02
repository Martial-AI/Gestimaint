<?php
// includes/db.php - Connexion intelligente a MySQL / MariaDB (WAMP / Laragon / XAMPP)

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host    = '127.0.0.1';
        $dbname  = 'gestimaint';
        $user    = 'root';
        $pass    = ''; // Mot de passe vide par defaut sur WAMP/Laragon
        $charset = 'utf8mb4';

        // Detection automatique du port : 3307 (MariaDB par defaut sur WAMP), 3306 (MySQL standard), 3308
        $ports = [3307, 3306, 3308];
        $lastException = null;

        foreach ($ports as $port) {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 2,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                $pdo = new PDO($dsn, $user, $pass, $options);
                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        // Si aucun port n'a fonctionne, affichage convivial de diagnostic
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>GESTIMAINT - Connexion base de données</title>
          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
          <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
          <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
              font-family: 'Inter', system-ui, sans-serif;
              background: #f7faf8;
              color: #1a2a22;
              display: flex;
              align-items: center;
              justify-content: center;
              min-height: 100vh;
              padding: 24px;
            }
            .setup-card {
              background: #ffffff;
              max-width: 580px;
              width: 100%;
              border-radius: 16px;
              border: 1px solid #e1eee6;
              box-shadow: 0 10px 30px rgba(20, 60, 40, 0.06);
              padding: 36px 32px;
            }
            .setup-badge {
              display: inline-flex;
              align-items: center;
              gap: 6px;
              background: #fef2f2;
              color: #dc2626;
              border: 1px solid #fee2e2;
              font-size: 13px;
              font-weight: 600;
              padding: 5px 12px;
              border-radius: 999px;
              margin-bottom: 16px;
            }
            h1 { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 12px; }
            p { font-size: 14px; color: #4b5563; line-height: 1.6; margin-bottom: 16px; }
            .code-box {
              background: #f0faf4;
              border: 1px solid #ccebd9;
              border-radius: 10px;
              padding: 14px 16px;
              font-family: Consolas, monospace;
              font-size: 13px;
              color: #14532d;
              margin-bottom: 20px;
              white-space: pre-wrap;
            }
            .btn {
              display: inline-block;
              background: #259b62;
              color: #ffffff;
              text-decoration: none;
              font-weight: 600;
              font-size: 14px;
              padding: 10px 20px;
              border-radius: 10px;
              box-shadow: 0 4px 12px rgba(37, 155, 98, 0.25);
              transition: background 0.2s;
            }
            .btn:hover { background: #1c7e4e; }
          </style>
        </head>
        <body>
          <div class="setup-card">
            <div class="setup-badge">Connexion MySQL / MariaDB</div>
            <h1>Base de données non accessible</h1>
            <p>Impossible de se connecter a la base <strong>gestimaint</strong> sur <code>127.0.0.1</code> (ports testes : 3307, 3306, 3308).</p>
            <div class="code-box"><?= htmlspecialchars($lastException ? $lastException->getMessage() : 'Erreur inconnue') ?></div>
            <a href="" class="btn" onclick="location.reload(); return false;">Reessayer la connexion</a>
          </div>
        </body>
        </html>
        <?php
        exit;
    }

    return $pdo;
}