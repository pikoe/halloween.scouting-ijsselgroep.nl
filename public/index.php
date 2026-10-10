<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$formValues = $_POST;
$errors = [];
$formMessage = $_SESSION['form_message'] ?? '';
unset($_SESSION['form_message']);
$value = static function (string $key) use (&$formValues): string {
	$value = $formValues[$key] ?? '';
	return is_string($value) ? $value : '';
};
$participantCountValue = filter_var($value('participant_count'), FILTER_VALIDATE_INT);
$participantCount = is_int($participantCountValue) && $participantCountValue >= 1 && $participantCountValue <= 5
	? $participantCountValue
	: 1;

$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!hash_equals($_SESSION['csrf_token'], $value('csrf_token'))) {
		$errors[] = 'Het formulier is verlopen. Ververs de pagina en probeer het opnieuw.';
	} else {
		$ageGroup = $value('age_group');
		$groupName = trim($value('group_name'));
		$email = trim($value('email'));
		$phone = trim($value('phone'));
		$adultName = trim($value('adult_name'));
		$remarks = trim($value('remarks'));
		$participantsInput = $formValues['participants'] ?? [];
		$participants = [];
		$startTime = $ageGroup === '7-12' ? '18:30' : '20:00';

		if (!is_int($participantCountValue) || $participantCountValue < 1 || $participantCountValue > 5) {
			$errors[] = 'Kies een geldig aantal deelnemers (1 tot en met 5).';
		}
		if (!in_array($ageGroup, ['7-12', '12-16'], true)) {
			$errors[] = 'Kies voor welke leeftijdsgroep de aanmelding is.';
		}
		if ($groupName === '' || mb_strlen($groupName) > 100) {
			$errors[] = 'Vul een groepsnaam in van maximaal 100 tekens.';
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
			$errors[] = 'Vul een geldig e-mailadres in.';
		}
		if ($phone === '' || mb_strlen($phone) > 30) {
			$errors[] = 'Vul een telefoonnummer in van maximaal 30 tekens.';
		}
		if ($ageGroup === '7-12' && ($adultName === '' || mb_strlen($adultName) > 120)) {
			$errors[] = 'Vul de naam van de volwassen begeleider in (maximaal 120 tekens).';
		}
		if (mb_strlen($remarks) > 2500) {
			$errors[] = 'Opmerkingen mogen maximaal 2500 tekens zijn.';
		}
		if (!is_array($participantsInput)) {
			$participantsInput = [];
		}

		for ($number = 1; $number <= $participantCount; $number++) {
			$participantInput = $participantsInput[$number] ?? [];
			if (!is_array($participantInput)) {
				$participantInput = [];
			}

			$ageInput = is_string($participantInput['age'] ?? null) ? trim($participantInput['age']) : '';

			if ($ageInput === '' || filter_var($ageInput, FILTER_VALIDATE_INT) === false) {
				$errors[] = 'Vul een geldige leeftijd in voor iedere geselecteerde deelnemer.';
			}

			if ($ageInput !== '' && filter_var($ageInput, FILTER_VALIDATE_INT) !== false) {
				$age = (int) $ageInput;
				$minimumAge = $ageGroup === '7-12' ? 7 : 12;
				$maximumAge = $ageGroup === '7-12' ? 12 : 16;
				if ($age < $minimumAge || $age > $maximumAge) {
					$errors[] = "De leeftijd van deelnemer {$number} past niet bij de gekozen leeftijdsgroep.";
				}
				$participants[] = [
					'number' => $number,
					'age' => $age,
				];
			}
		}

		if (!$errors) {
			$databaseSettings = [
				'host' => getenv('DB_HOST'),
				'name' => getenv('DB_NAME'),
				'user' => getenv('DB_USER'),
				'password' => getenv('DB_PASSWORD'),
			];

			if (in_array(false, $databaseSettings, true) || in_array('', $databaseSettings, true)) {
				$errors[] = 'Aanmelden is momenteel niet beschikbaar. Probeer het later opnieuw of mail naar info@scouting-ijsselgroep.nl.';
				error_log('Registration database settings are incomplete.');
			} else {
				try {
					$pdo = new PDO(
						'mysql:host=' . $databaseSettings['host'] . ';dbname=' . $databaseSettings['name'] . ';charset=utf8mb4',
						$databaseSettings['user'],
						$databaseSettings['password'],
						[
							PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
							PDO::ATTR_EMULATE_PREPARES => false,
						]
					);
					$pdo->exec(
						'CREATE TABLE IF NOT EXISTS registrations (
							id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
							age_group VARCHAR(5) NOT NULL,
							start_time TIME NOT NULL,
							group_name VARCHAR(100) NOT NULL,
							email VARCHAR(254) NOT NULL,
							phone VARCHAR(30) NOT NULL,
							adult_name VARCHAR(120) NULL,
							remarks VARCHAR(2500) NOT NULL DEFAULT \'\',
							created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
					);
					$remarksColumn = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'remarks'");
					if (!$remarksColumn->fetch()) {
						$pdo->exec('ALTER TABLE registrations ADD COLUMN remarks VARCHAR(2500) NOT NULL DEFAULT \'\'');
					}
					$pdo->exec(
						'CREATE TABLE IF NOT EXISTS registration_participants (
							id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
							registration_id BIGINT UNSIGNED NOT NULL,
							participant_number TINYINT UNSIGNED NOT NULL,
							age TINYINT UNSIGNED NOT NULL,
							dietary_notes VARCHAR(500) NOT NULL DEFAULT \'\',
							CONSTRAINT fk_registration_participants_registration
								FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
							UNIQUE KEY uq_registration_participant (registration_id, participant_number)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
					);

					$pdo->beginTransaction();
					$registrationQuery = $pdo->prepare(
						'INSERT INTO registrations (age_group, start_time, group_name, email, phone, adult_name, remarks)
						VALUES (:age_group, :start_time, :group_name, :email, :phone, :adult_name, :remarks)'
					);
					$registrationQuery->execute([
						'age_group' => $ageGroup,
						'start_time' => $startTime,
						'group_name' => $groupName,
						'email' => $email,
						'phone' => $phone,
						'adult_name' => $ageGroup === '7-12' ? $adultName : null,
						'remarks' => $remarks,
					]);
					$registrationId = $pdo->lastInsertId();
					$participantQuery = $pdo->prepare(
						'INSERT INTO registration_participants (registration_id, participant_number, age, dietary_notes)
						VALUES (:registration_id, :participant_number, :age, :dietary_notes)'
					);
					foreach ($participants as $participant) {
						$participantQuery->execute([
							'registration_id' => $registrationId,
							'participant_number' => $participant['number'],
							'age' => $participant['age'],
							'dietary_notes' => '',
						]);
					}
					$pdo->commit();

					$mailBody = [
						"Nieuwe aanmelding voor de Halloween spooktocht",
						'Leeftijdsgroep: ' . $ageGroup . ' jaar (start ' . $startTime . ')',
						'Groepsnaam: ' . $groupName,
						'E-mail: ' . $email,
						'Telefoonnummer: ' . $phone,
					];
					if ($ageGroup === '7-12') {
						$mailBody[] = 'Volwassen begeleider: ' . $adultName;
					}
					$mailBody[] = '';
					$mailBody[] = 'Deelnemers:';
					foreach ($participants as $participant) {
						$mailBody[] = 'Deelnemer ' . $participant['number'] . ': ' . $participant['age'] . ' jaar';
					}
					$mailBody[] = 'Opmerkingen (allergieën/diëten): ' . ($remarks !== '' ? $remarks : 'Geen opgegeven');

					$mailSent = mail(
						'info@scouting-ijsselgroep.nl',
						'Nieuwe aanmelding Halloween spooktocht',
						implode("\n", $mailBody),
						[
							'From' => 'Halloween spooktocht <no-reply@scouting-ijsselgroep.nl>',
							'Reply-To' => $email,
							'Content-Type' => 'text/plain; charset=UTF-8',
						]
					);

					$_SESSION['form_message'] = $mailSent
						? 'Bedankt! De aanmelding is opgeslagen en doorgestuurd.'
						: 'De aanmelding is opgeslagen, maar de e-mailmelding kon niet worden verzonden. Neem contact op via info@scouting-ijsselgroep.nl.';
					$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
					header('Location: ' . $_SERVER['SCRIPT_NAME'] . '#aanmelden', true, 303);
					exit;
				} catch (PDOException $exception) {
					if (isset($pdo) && $pdo->inTransaction()) {
						$pdo->rollBack();
					}
					error_log('Registration database error: ' . $exception->getMessage());
					$errors[] = 'De aanmelding kon niet worden opgeslagen. Probeer het later opnieuw of mail naar info@scouting-ijsselgroep.nl.';
				}
			}
		}
	}
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#170d24">
	<title>Halloween spooktocht | Scouting IJsselgroep</title>`
	
	<link rel="icon" type="image/png" href="/img/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="/img/favicon.svg" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="MyWebSite" />
	<link rel="manifest" href="/img/site.webmanifest" />

	<style>
		:root {
			color-scheme: dark;
			font-family: system-ui, sans-serif;
			background: #170d24;
			color: #fff8ed;
		}

		* {
			box-sizing: border-box;
		}

		body {
			min-height: 100vh;
			margin: 0;
			padding: 2rem 1rem;
			display: grid;
			place-items: center;
			background-color: #170d24;
			background-image:
				radial-gradient(ellipse at top, rgb(56 32 71 / 75%), rgb(23 13 36 / 65%) 75%),
				url("img/verlaten_kas.png");
			background-position: center, center;
			background-size: cover, cover;
			background-attachment: scroll, fixed;
		}

		main {
			width: min(100%, 760px);
			text-align: left;
		}

		h1,
		main > p:nth-of-type(-n + 2) {
			text-align: center;
		}

		h2 {
			margin: 0 0 1rem 0;
		}

		a {
			color: #fff;
		}

		a:hover,
		a:active {
			color: #e6e6e6;
		}

		img {
			display: block;
			width: 100%;
			height: auto;
			margin: 0 auto 2rem;
			border-radius: 0.75rem;
			box-shadow: 0 1rem 3rem rgb(0 0 0 / 35%);
		}

		.info-block {
			display: flow-root;
			margin: 4rem 0 0 0;
		}

		.info-block.logos {
			display: flex;
			flex-wrap: wrap;
			justify-content: center;
			gap: 1rem;
			text-align: center;
		}

		.info-block.logos h2 {
			flex-basis: 100%;
		}

		.info-block.logos > a {
			width: min(35vw, 150px);
		}

		.info-block.logos img {
			margin: 0;
		}

		img.zandloper {
			float: right;
			width: min(25%, 100px);
			margin: 0 1.5rem 1rem 0;
		}
		img.oude-kaart {
			float: left;
			width: min(30%, 150px);
			margin: 0 1.5rem 1rem 0;
		}
		img.pompoen {
			float: right;
			width: min(30%, 150px);
			margin: 0 0 1rem 1.5rem;
		}
		img.pesos {
			float: left;
			width: min(25%, 110px);
			margin: 0 1.5rem 1rem 0;
		}
		img.tuinman-foto {
			float: right;
			width: min(30%, 150px);
			margin: 0 0 1rem 1.5rem;
		}

		p {
			margin: 0 auto 1rem;
			font-size: clamp(1rem, 2.5vw, 1.2rem);
			line-height: 1.7;
		}

		.registration-form fieldset {
			margin: 0 0 1.5rem;
			padding: 1rem;
			border: 1px solid rgb(255 248 237 / 35%);
			border-radius: 0.5rem;
		}

		.registration-form legend {
			padding: 0 0.4rem;
			font-size: 1.15rem;
			font-weight: 700;
		}

		.form-field {
			margin: 0 0 1rem;
		}

		.form-field:last-child {
			margin-bottom: 0;
		}

		.form-field > label,
		.participant-fields label {
			display: block;
			margin-bottom: 0.35rem;
			font-weight: 600;
		}

		.registration-form input[type="text"],
		.registration-form input[type="email"],
		.registration-form input[type="tel"],
		.registration-form input[type="number"],
		.registration-form select,
		.registration-form textarea {
			width: 100%;
			padding: 0.7rem;
			border: 1px solid #b9aaba;
			border-radius: 0.35rem;
			background: #fff8ed;
			color: #170d24;
			font: inherit;
		}

		.registration-form textarea {
			min-height: 5rem;
			resize: vertical;
		}

		.radio-option {
			display: flex;
			align-items: center;
			gap: 0.6rem;
			margin: 0.5rem 0;
			font-size: 1.05rem;
		}

		.participant-fields {
			max-width: 12rem;
		}

		.form-errors,
		.form-message {
			margin: 0 0 1.25rem;
			padding: 1rem;
			border-radius: 0.5rem;
			background: #fff8ed;
			color: #170d24;
		}

		.form-errors {
			border-left: 0.35rem solid #d62828;
		}

		.form-message {
			border-left: 0.35rem solid #69a86b;
		}

		.submit-button {
			display: inline-block;
			padding: 0.8rem 1.2rem;
			border: 0;
			border-radius: 0.4rem;
			background: #ed7d31;
			color: #170d24;
			font: inherit;
			font-weight: 700;
			text-decoration: none;
			cursor: pointer;
		}

		.submit-button:hover {
			background: #ff9b50;
		}

		.registration-cta {
			margin: 1.5rem 0 0;
			text-align: center;
		}

		@media (max-width: 480px) {
			body {
				padding: 1.25rem 1rem;
			}

			img {
				margin-bottom: 1.5rem;
				border-radius: 0.5rem;
			}
		}
	</style>
</head>
<body>
	<main>
		<h1>Halloween spooktocht</h1>
		<p>Halloween zit er aan te komen, de tijd om te griezelen! Daarom organiseert scouting IJsselgroep op zaterdagavond 24 oktober een spooktocht voor iedereen van 7 tot 16 jaar.</p>
		<p>Iedereen mag meedoen, ook als je niet lid bent van scouting!</p>

		<p class="registration-cta"><a class="submit-button" href="#aanmelden">Direct aanmelden</a></p>

		<div class="info-block">
			<img class="zandloper" src="img/zandloper.png" alt="Zandloper" />
			<h2>Wanneer: 24 oktober</h2>
			<p>De spooktocht start op twee momenten, ingedeeld op leeftijd.</p>
			<p>18.30 uur start de eerste goep (7 tot ca 12 jaar) en 20.00 uur start de tweede groep (ca 12 tot 16 jaar).<br />
				De eerste goep zal rond 20.15 terug zijn de tweede groep rond 21.30 uur.</p>
		</div>

		<div class="info-block">
			<h2>Waar: Kwekerijweg 5 Gorssel</h2>
			<img class="oude-kaart" src="img/oude_kaart_gorssel.png" alt="Oude kaart van Gorssel" />
			<p>De spooktocht wordt georganiseerd door Scouting IJsselgroep en Scouting Kompasnaald. De start is bij het scouting gebouw van de IJsselgroep aan de <a href="https://www.google.com/maps/place/Scouting+IJsselgroep/@52.1998177,6.212443,641m/data=!3m1!1e3!4m15!1m8!3m7!1s0x47c7eb4d62bce631:0xd4dfd625da6e06d3!2sKwekerijweg+5,+7213+AX+Gorssel!3b1!8m2!3d52.1998177!4d6.2150233!16s%2Fg%2F11n07mhxrh!3m5!1s0x47c7eb4d62b3aa65:0x3e3e33a31c3c43ba!8m2!3d52.1998772!4d6.21503!16s%2Fg%2F1thfdp7v?entry=ttu&g_ep=EgoyMDI2MTAwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank" rel="noopener noreferrer">Kwekerijweg 5 in Gorssel</a>.</p>
		</div>

		<div class="info-block">
			<h2>Voor wie: iedereen van 7 tot 16 jaar</h2>
			<img class="pompoen" src="img/pompoen.png" alt="Pompoen" />
			<p>De spooktocht is voor iedereen van 7 tot 16 jaar. Ook als je geen lid bent van scouting ben je welkom!</p>
		</div>

		<div class="info-block">
			<h2>Wat kost het: € 5,- per persoon</h2>
			<img class="pesos" src="img/pesos-mexican.png" alt="Pesos Mexican" />
			<p>De kosten voor deelname zijn € 5,- per persoon. Dit kan contant worden betaald bij de start van de spooktocht.</p>
		</div>


		<div class="info-block">
			<h2>Hoe ziet de avond er uit</h2>
			<p>Voor de aanmeldingen van groepjes met kinderen tot 12 jaar vragen we om één volwassen begeleider mee te laten lopen.</p>
			<p>We beginnen met een verhaal, waarna de tocht in kleine groepjes van ca 5 kinderen wordt gelopen. Bij terugkomst is wat te drinken en wat lekkers bij het kampvuur.</p>
			<p>De wandeling zelf is nog geen 3 kilometer dus voor iedereen goed te doen.<br />
				De spooktocht is buiten, dus trek warme kleding aan en schoenen die tegen een beetje modder kunnen.</p>
			<p>Tijdens de wandeling willen we niet dat er zaklampen of mobieltjes worden gebruikt, zodat iedereen de ervaring van de 	tocht beleeft.</p>
		</div>

		<div class="info-block">
			<img class="tuinman-foto" src="img/tuinman_foto.png" alt="De tuinman van de verlaten kas" />
			<h2>Meer informatie</h2>
			<p>Heb je nog vragen, mail ons via <a href="mailto:info@scouting-ijsselgroep.nl">info@scouting-ijsselgroep.nl</a></p>
		</div>
		
		<div class="info-block" id="aanmelden">
			<h2>Aanmelden</h2>
			<?php if ($formMessage !== ''): ?>
				<p class="form-message" role="status"><?= $escape($formMessage) ?></p>
			<?php endif; ?>
			<?php if ($errors): ?>
				<div class="form-errors" role="alert">
					<p>Controleer de volgende gegevens:</p>
					<ul>
						<?php foreach (array_unique($errors) as $error): ?>
							<li><?= $escape($error) ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<form class="registration-form" method="post" action="#aanmelden">
				<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">

				<fieldset>
					<legend>Voor welke leeftijdsgroep meld je aan?</legend>
					<label class="radio-option">
						<input type="radio" name="age_group" value="7-12" required <?= $value('age_group') === '7-12' ? 'checked' : '' ?>>
						7 tot 12 jaar — start om 18.30 uur
					</label>
					<label class="radio-option">
						<input type="radio" name="age_group" value="12-16" required <?= $value('age_group') === '12-16' ? 'checked' : '' ?>>
						12 tot 16 jaar — start om 20.00 uur
					</label>
				</fieldset>

				<fieldset>
					<legend>Groep en contactgegevens</legend>
					<div class="form-field">
						<label for="group-name">Groepsnaam</label>
						<input id="group-name" type="text" name="group_name" maxlength="100" value="<?= $escape($value('group_name')) ?>" required>

						<p>Verzin een leuke en herkenbare groepsnaam, zodat we het aanmelden aan het begin van de spooktocht soepel kunnen laten verlopen</p>
					</div>
					<div class="form-field">
						<label for="email">E-mailadres</label>
						<input id="email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= $escape($value('email')) ?>" required>
					</div>
					<div class="form-field">
						<label for="phone">Telefoonnummer</label>
						<input id="phone" type="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= $escape($value('phone')) ?>" required>
					</div>
					<div class="form-field" id="adult-field">
						<label for="adult-name">Naam volwassen begeleider (verplicht bij 7 tot 12 jaar)</label>
						<input id="adult-name" type="text" name="adult_name" maxlength="120" value="<?= $escape($value('adult_name')) ?>">
					</div>
					<p>De gegevens worden alleen gebruikt voor communicatie over de spooktocht en worden niet gedeeld met derden.</p>
				</fieldset>

				<fieldset>
					<legend>Deelnemers</legend>
					<div class="form-field">
						<label for="participant-count">Aantal deelnemers</label>
						<select id="participant-count" name="participant_count" required>
							<?php for ($count = 1; $count <= 5; $count++): ?>
								<option value="<?= $count ?>" <?= $participantCount === $count ? 'selected' : '' ?>><?= $count ?></option>
							<?php endfor; ?>
						</select>
					</div>
					<?php for ($number = 1; $number <= 5; $number++):
						$participantValues = $formValues['participants'][$number] ?? [];
						if (!is_array($participantValues)) {
							$participantValues = [];
						}
						$participantAge = is_string($participantValues['age'] ?? null) ? $participantValues['age'] : '';
					?>
						<div class="form-field participant-field" <?= $number > $participantCount ? 'hidden' : '' ?>>
							<div class="participant-fields">
								<label for="participant-<?= $number ?>-age">Leeftijd deelnemer <?= $number ?></label>
								<input id="participant-<?= $number ?>-age" type="number" name="participants[<?= $number ?>][age]" min="7" max="16" value="<?= $escape($participantAge) ?>" <?= $number <= $participantCount ? 'required' : 'disabled' ?>>
							</div>
						</div>
					<?php endfor; ?>
					<p>Je mag je ook individueel of als duo opgeven, kleine groepjes van 3 of minder deelnemers kunnen we samenvoegen zodat iedereen deel uitmaakt van een groep om mee samen te lopen.</p>
					<p>Mocht een van de groepen veel groter zijn dan de andere gebruiken we de leeftijden om te vragen mee te doen met het andere tijdblok.</p>

					<div class="form-field">
						<label for="remarks">Opmerkingen</label>
						<textarea id="remarks" name="remarks" maxlength="2500"><?= $escape($value('remarks')) ?></textarea>
						<p>Vermeld ook allergieën en diëten hier.</p>
					</div>
				</fieldset>

				<button class="submit-button" type="submit">Aanmelding versturen</button>
			</form>
		</div>

		<div class="info-block logos">
			<h2>Mogelijk gemaakt door</h2>

			<a href="https://www.scouting-ijsselgroep.nl" target="_blank">
				<img class="" src="img/ijsselgroep.webp" alt="Scouting IJsselgroep logo" />
			</a>
			<a href="https://scoutingkompasnaald.nl" target="_blank">
				<img class="" src="img/scouting-kompasnaald.png" alt="Scouting Kompasnaald logo" />
			</a>
		</div>
	</main>
	<script>
		const ageGroupInputs = document.querySelectorAll('input[name="age_group"]');
		const adultField = document.getElementById('adult-field');
		const adultNameInput = document.getElementById('adult-name');
		const participantCountInput = document.getElementById('participant-count');
		const participantFields = document.querySelectorAll('.participant-field');
		const participantAgeInputs = document.querySelectorAll('input[name^="participants"][name$="[age]"]');

		function updateAgeGroupFields() {
			const selectedGroup = document.querySelector('input[name="age_group"]:checked')?.value;
			const needsAdult = selectedGroup === '7-12';
			adultField.hidden = selectedGroup === '12-16';
			adultNameInput.required = needsAdult;

			const participantCount = Number(participantCountInput.value);
			for (const [index, field] of participantFields.entries()) {
				const input = field.querySelector('input');
				const isSelected = index < participantCount;
				field.hidden = !isSelected;
				input.disabled = !isSelected;
				input.required = isSelected;
			}

			for (const input of participantAgeInputs) {
				input.min = selectedGroup === '12-16' ? '12' : '7';
				input.max = selectedGroup === '7-12' ? '12' : '16';
			}
		}

		ageGroupInputs.forEach((input) => input.addEventListener('change', updateAgeGroupFields));
		participantCountInput.addEventListener('change', updateAgeGroupFields);
		updateAgeGroupFields();
	</script>
</body>
</html>