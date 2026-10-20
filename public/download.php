<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
	$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

	if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
		$error = 'Het formulier is verlopen. Ververs de pagina en probeer het opnieuw.';
	} else {
		$envPath = dirname(__DIR__) . '/.env';
		$envSettings = is_readable($envPath)
			? parse_ini_file($envPath, false, INI_SCANNER_RAW)
			: false;
		$listPassword = is_array($envSettings) ? ($envSettings['LIST_PASSWORD'] ?? null) : null;

		if (!is_string($listPassword) || $listPassword === '') {
			$error = 'De download is momenteel niet beschikbaar. Probeer het later opnieuw.';
			error_log('List password could not be loaded from .env.');
		} elseif (!hash_equals($listPassword, $password)) {
			$error = 'Het wachtwoord is onjuist.';
		} else {
			$databaseSettings = [
				'connection' => $envSettings['DB_CONNECTION'] ?? '',
				'host' => $envSettings['DB_HOST'] ?? '',
				'port' => filter_var($envSettings['DB_PORT'] ?? null, FILTER_VALIDATE_INT, [
					'options' => ['min_range' => 1, 'max_range' => 65535],
				]),
				'name' => $envSettings['DB_DATABASE'] ?? '',
				'user' => $envSettings['DB_USERNAME'] ?? '',
				'password' => $envSettings['DB_PASSWORD'] ?? '',
			];

			if (
				$databaseSettings['connection'] !== 'mysql'
				|| $databaseSettings['port'] === false
				|| in_array('', [
					$databaseSettings['host'],
					$databaseSettings['name'],
					$databaseSettings['user'],
					$databaseSettings['password'],
				], true)
			) {
				$error = 'De download is momenteel niet beschikbaar. Probeer het later opnieuw.';
				error_log('Registration database settings are incomplete or invalid.');
			} else {
				try {
					$pdo = new PDO(
						'mysql:host=' . $databaseSettings['host'] . ';port=' . $databaseSettings['port'] . ';dbname=' . $databaseSettings['name'] . ';charset=utf8mb4',
						$databaseSettings['user'],
						$databaseSettings['password'],
						[
							PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
							PDO::ATTR_EMULATE_PREPARES => false,
						]
					);
					$participants = $pdo->query(
						'SELECT
							r.id AS registration_id,
							r.created_at,
							r.age_group,
							r.start_time,
							r.group_name,
							r.email,
							r.phone,
							r.adult_name,
							r.remarks,
							p.participant_number,
							p.`name` AS participant_name,
							p.age
						FROM registration_participants p
						INNER JOIN registrations r ON r.id = p.registration_id
						ORDER BY r.start_time, r.group_name, r.id, p.participant_number'
					);

					header('Content-Type: text/csv; charset=UTF-8');
					header('Content-Disposition: attachment; filename="aanmeldingen-' . date('Y-m-d') . '.csv"');
					header('Cache-Control: no-store, no-cache, must-revalidate');
					header('Pragma: no-cache');

					$output = fopen('php://output', 'wb');
					if ($output === false) {
						throw new RuntimeException('Could not open the CSV output stream.');
					}

					fwrite($output, "\xEF\xBB\xBF");
					fputcsv($output, [
						'Aanmeldnummer',
						'Aanmelddatum',
						'Leeftijdsgroep',
						'Starttijd',
						'Groepsnaam',
						'E-mailadres',
						'Telefoonnummer',
						'Naam begeleider',
						'Opmerkingen',
						'Deelnemernummer',
						'Naam deelnemer',
						'Leeftijd',
					], ';', '"', '');

					while ($participant = $participants->fetch(PDO::FETCH_ASSOC)) {
						$row = [];
						foreach ($participant as $field => $value) {
							$text = (string) ($value ?? '');
							if (
								$field !== 'age'
								&& preg_match('/^[\s]*[=+\-@]/u', $text) === 1
							) {
								$text = "'" . $text;
							}
							$row[] = $text;
						}
						fputcsv($output, $row, ';', '"', '');
					}

					fclose($output);
					exit;
				} catch (PDOException | RuntimeException $exception) {
					error_log('Registration export failed: ' . $exception->getMessage());
					http_response_code(500);
					$error = 'De export kon niet worden gemaakt. Probeer het later opnieuw.';
				}
			}
		}
	}
}
?>
<!doctype html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Aanmeldingen downloaden - Halloween spooktocht</title>
	<style>
		body {
			max-width: 38rem;
			margin: 3rem auto;
			padding: 0 1rem;
			color: #f8f2fa;
			background: #170d24;
			font: 1rem/1.6 system-ui, sans-serif;
		}

		main {
			padding: 2rem;
			border: 1px solid #57376b;
			border-radius: 0.75rem;
			background: #241331;
		}

		label {
			display: block;
			margin-bottom: 0.5rem;
			font-weight: 700;
		}

		input {
			box-sizing: border-box;
			width: 100%;
			margin-bottom: 1rem;
			padding: 0.75rem;
			border: 1px solid #8b6c99;
			border-radius: 0.4rem;
			font: inherit;
		}

		button {
			padding: 0.75rem 1rem;
			border: 0;
			border-radius: 0.4rem;
			color: #170d24;
			background: #ffb36b;
			font: inherit;
			font-weight: 700;
			cursor: pointer;
		}

		.error {
			padding: 0.75rem;
			border-left: 4px solid #ff7b7b;
			background: #3a1c2b;
		}

		a {
			color: #ffb36b;
		}
	</style>
</head>
<body>
	<main>
		<h1>Aanmeldingen downloaden</h1>
		<p>Vul het downloadwachtwoord in om een Excel-bestand met alle deelnemers te downloaden.</p>
		<?php if ($error !== ''): ?>
			<p class="error" role="alert"><?= $escape($error) ?></p>
		<?php endif; ?>
		<form method="post">
			<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
			<label for="password">Wachtwoord</label>
			<input id="password" type="password" name="password" autocomplete="current-password" required>
			<button type="submit">Download aanmeldingen</button>
		</form>
		<p><a href="index.php">Terug naar de aanmeldpagina</a></p>
	</main>
</body>
</html>