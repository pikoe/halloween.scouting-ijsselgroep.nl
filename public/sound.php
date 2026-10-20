<?php
declare(strict_types=1);

$audioDirectory = __DIR__ . '/audio';
$audioFiles = glob($audioDirectory . '/*.{mp3,MP3}', GLOB_BRACE);
if ($audioFiles === false) {
	http_response_code(500);
	exit('De audiobestanden konden niet worden geladen.');
}

$tracks = [];
foreach ($audioFiles as $audioFile) {
	if (!is_file($audioFile)) {
		continue;
	}

	$fileName = basename($audioFile);
	$tracks[] = [
		'title' => pathinfo($fileName, PATHINFO_FILENAME),
		'url' => 'audio/' . rawurlencode($fileName),
	];
}

usort($tracks, static fn (array $first, array $second): int => strnatcasecmp($first['title'], $second['title']));
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#170d24">
	<title>Soundboard | Scouting IJsselgroep</title>
	<link rel="icon" type="image/svg+xml" href="/img/favicon.svg">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous">
	<style>
		:root {
			color-scheme: dark;
			font-family: system-ui, sans-serif;
			background: #170d24;
			color: #fff8ed;
			font-synthesis: none;
		}

		* {
			box-sizing: border-box;
		}

		body {
			min-height: 100vh;
			margin: 0;
			padding: 2rem 1.25rem;
			background:
				radial-gradient(ellipse at top, rgb(56 32 71 / 75%), rgb(23 13 36 / 92%) 75%),
				url("img/verlaten_kas.png") center / cover fixed;
		}

		main {
			width: min(100%, 1040px);
			margin: 0 auto;
		}

		header {
			margin: 0 auto 2rem;
			text-align: center;
		}

		h1 {
			margin: 0 0 0.5rem;
			font-size: clamp(2rem, 7vw, 3.5rem);
			letter-spacing: 0.02em;
		}

		header p {
			margin: 0;
			color: #d8cbdc;
		}

		.sound-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
			gap: 1rem;
		}

		.sound-tile {
			position: relative;
			display: flex;
			min-height: 100px;
			overflow: hidden;
			border: 1px solid rgb(255 248 237 / 18%);
			border-radius: 0.85rem;
			background: linear-gradient(145deg, #39264a, #261832);
			box-shadow: 0 0.75rem 1.8rem rgb(0 0 0 / 22%);
			transition: border-color 160ms ease, transform 160ms ease, background 160ms ease;
		}

		.sound-tile:hover {
			transform: translateY(-3px);
			border-color: #ed7d31;
		}

		.sound-tile.is-playing {
			border-color: #ff9b50;
			background: linear-gradient(145deg, #573522, #34203e);
			box-shadow: 0 0 0 2px rgb(237 125 49 / 24%), 0 0.75rem 1.8rem rgb(0 0 0 / 22%);
		}

		.sound-play {
			display: flex;
			width: 100%;
			flex: 1;
			min-height: 100px;
			align-items: center;
			gap: 1rem;
			padding: 1rem 4.5rem 1rem 1.25rem;
			border: 0;
			background: transparent;
			color: inherit;
			font: inherit;
			text-align: left;
			cursor: pointer;
		}

		.sound-duration {
			position: absolute;
			top: 0.65rem;
			right: 0.65rem;
			color: #d8cbdc;
			font-size: 0.8rem;
			font-variant-numeric: tabular-nums;
		}

		.sound-progress {
			position: absolute;
			right: 0;
			bottom: 0;
			left: 0;
			display: block;
			height: 6px;
			background: rgb(237 125 49 / 18%);
			pointer-events: none;
		}

		.sound-progress-bar {
			display: block;
			width: 0;
			height: 100%;
			background: #ed7d31;
			transition: width 120ms linear;
		}

		.sound-play:focus-visible,
		.repeat-toggle:focus-visible {
			outline: 3px solid #ffb878;
			outline-offset: -4px;
		}

		.play-icon {
			display: grid;
			width: 2.75rem;
			height: 2.75rem;
			flex: 0 0 auto;
			place-items: center;
			border-radius: 50%;
			background: #ed7d31;
			color: #170d24;
			font-size: 1.15rem;
		}

		.play-icon .fas {
			margin-left: 0.1rem;
		}

		.sound-tile.is-playing .play-icon .fas {
			margin-left: 0;
		}

		.sound-title {
			overflow-wrap: anywhere;
			font-size: 1.1rem;
			font-weight: 700;
			line-height: 1.35;
		}

		.repeat-toggle {
			position: absolute;
			right: 0.65rem;
			bottom: 0.65rem;
			display: grid;
			width: 2rem;
			height: 2rem;
			place-items: center;
			padding: 0;
			border: 1px solid rgb(255 248 237 / 35%);
			border-radius: 50%;
			background: rgb(23 13 36 / 72%);
			color: #fff8ed;
			font: inherit;
			font-size: 1rem;
			cursor: pointer;
		}

		.repeat-toggle:hover,
		.repeat-toggle[aria-pressed="true"] {
			border-color: #ff9b50;
			background: #ed7d31;
			color: #170d24;
		}

		.empty-state {
			padding: 1.5rem;
			border: 1px solid rgb(255 248 237 / 18%);
			border-radius: 0.75rem;
			background: rgb(23 13 36 / 72%);
			text-align: center;
		}

		.status {
			min-height: 1.7em;
			margin: 1.5rem 0 0;
			color: #d8cbdc;
			text-align: center;
		}

		.home-link {
			display: inline-block;
			margin-top: 2rem;
			color: #fff8ed;
		}

		@media (prefers-reduced-motion: reduce) {
			.sound-tile {
				transition: none;
			}
		}
	</style>
</head>
<body>
	<main>
		<header>
			<h1>Soundboard</h1>
			<p>Klik op een tegel om het geluid af te spelen. Met het herhaalknopje speel je een geluid steeds opnieuw.</p>
		</header>

		<?php if ($tracks): ?>
			<section class="sound-grid" aria-label="Audiobestanden">
				<?php foreach ($tracks as $index => $track): ?>
					<article class="sound-tile" data-track="<?= $index ?>">
						<button class="sound-play" type="button" aria-label="Speel <?= $escape($track['title']) ?>">
							<span class="play-icon" aria-hidden="true"><i class="fas fa-play"></i></span>
							<span class="sound-title"><?= $escape($track['title']) ?></span>
						</button>
						<span class="sound-duration" aria-label="Duur wordt geladen">--:--</span>
						<button
							class="repeat-toggle"
							type="button"
							aria-label="Herhaal <?= $escape($track['title']) ?>"
							aria-pressed="false"
							title="Herhalen aan/uit"
						><i class="fas fa-redo-alt" aria-hidden="true"></i></button>
						<audio preload="auto" src="<?= $escape($track['url']) ?>"></audio>
						<span class="sound-progress" aria-hidden="true"><span class="sound-progress-bar"></span></span>
					</article>
				<?php endforeach; ?>
			</section>
		<?php else: ?>
			<p class="empty-state">Er staan nog geen MP3-bestanden in de map audio.</p>
		<?php endif; ?>

		<p class="status" role="status" aria-live="polite"></p>
	</main>

	<script>
		const tiles = [...document.querySelectorAll('.sound-tile')];
		const status = document.querySelector('.status');
		let activeTile = null;

		function setPlaybackState(tile, isPlaying) {
			const playButton = tile.querySelector('.sound-play');
			const icon = playButton.querySelector('.play-icon .fas');
			const title = tile.querySelector('.sound-title').textContent;
			tile.classList.toggle('is-playing', isPlaying);
			icon.classList.toggle('fa-play', !isPlaying);
			icon.classList.toggle('fa-pause', isPlaying);
			playButton.setAttribute('aria-label', `${isPlaying ? 'Pauzeer' : 'Speel'} ${title}`);
		}

		function stopTile(tile) {
			const audio = tile.querySelector('audio');
			audio.pause();
			audio.currentTime = 0;
			tile.querySelector('.sound-progress-bar').style.width = '0';
			audio.loop = false;
			setPlaybackState(tile, false);
			const repeatButton = tile.querySelector('.repeat-toggle');
			repeatButton.setAttribute('aria-pressed', 'false');
			repeatButton.setAttribute('aria-label', `Herhaal ${tile.querySelector('.sound-title').textContent}`);
			repeatButton.title = 'Herhalen staat uit';
			if (activeTile === tile) {
				activeTile = null;
			}
		}

		for (const tile of tiles) {
			const audio = tile.querySelector('audio');
			const playButton = tile.querySelector('.sound-play');
			const repeatButton = tile.querySelector('.repeat-toggle');
			const durationLabel = tile.querySelector('.sound-duration');
			const progressBar = tile.querySelector('.sound-progress-bar');
			const title = tile.querySelector('.sound-title').textContent;

			function updateProgress() {
				if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
					progressBar.style.width = '0';
					return;
				}

				const progress = Math.min(100, Math.max(0, (audio.currentTime / audio.duration) * 100));
				progressBar.style.width = `${progress}%`;
			}

			function updateDuration() {
				if (!Number.isFinite(audio.duration)) {
					return;
				}

				const totalSeconds = Math.floor(audio.duration);
				const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
				const seconds = String(totalSeconds % 60).padStart(2, '0');
				const duration = `${minutes}:${seconds}`;
				durationLabel.textContent = duration;
				durationLabel.setAttribute('aria-label', `Duur ${duration}`);
			}

			audio.addEventListener('loadedmetadata', updateDuration);
			audio.addEventListener('durationchange', updateDuration);
			audio.addEventListener('timeupdate', updateProgress);
			updateDuration();
			updateProgress();

			playButton.addEventListener('click', async () => {
				if (activeTile === tile) {
					if (audio.paused) {
						try {
							audio.currentTime = 0;
							updateProgress();
							await audio.play();
							setPlaybackState(tile, true);
							status.textContent = `${title} ${audio.loop ? 'wordt opnieuw afgespeeld en herhaald' : 'wordt opnieuw afgespeeld'}.`;
						} catch (error) {
							stopTile(tile);
							status.textContent = `Kon ${title} niet afspelen. Probeer het opnieuw.`;
						}
					} else {
						audio.pause();
						setPlaybackState(tile, false);
						status.textContent = `${title} gepauzeerd.`;
					}
					return;
				}

				if (activeTile) {
					stopTile(activeTile);
				}

				activeTile = tile;
				audio.currentTime = 0;
				updateProgress();
				audio.loop = repeatButton.getAttribute('aria-pressed') === 'true';
				setPlaybackState(tile, true);

				try {
					await audio.play();
					status.textContent = `${title} ${audio.loop ? 'wordt herhaald' : 'wordt afgespeeld'}.`;
				} catch (error) {
					stopTile(tile);
					status.textContent = `Kon ${title} niet afspelen. Probeer het opnieuw.`;
				}
			});

			repeatButton.addEventListener('click', () => {
				const repeatEnabled = repeatButton.getAttribute('aria-pressed') !== 'true';
				repeatButton.setAttribute('aria-pressed', String(repeatEnabled));
				repeatButton.setAttribute('aria-label', `${repeatEnabled ? 'Stop herhalen' : 'Herhaal'} ${title}`);
				audio.loop = repeatEnabled;
				repeatButton.title = repeatEnabled ? 'Herhalen staat aan' : 'Herhalen staat uit';

				if (activeTile === tile && !audio.paused) {
					status.textContent = `${title} ${repeatEnabled ? 'wordt herhaald' : 'speelt nog één keer af'}.`;
				}
			});
			audio.addEventListener('ended', () => {
				updateProgress();
				setPlaybackState(tile, false);
				if (activeTile === tile) {
					activeTile = null;
				}
				status.textContent = `${title} afgespeeld.`;
			});

			audio.addEventListener('error', () => {
				if (activeTile === tile) {
					stopTile(tile);
					status.textContent = `Kon ${title} niet laden.`;
				}
			});
		}
	</script>
</body>
</html>