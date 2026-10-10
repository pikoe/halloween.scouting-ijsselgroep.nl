<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#170d24">
	<title>Halloween spooktocht | Scouting IJsselgroep</title>
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

		.tuinman-foto {
			float: right;
			width: min(30%, 150px);
			margin: 0 0 1rem 1.5rem;
		}

		p {
			margin: 0 auto 1rem;
			font-size: clamp(1rem, 2.5vw, 1.2rem);
			line-height: 1.7;
		}

		@media (max-width: 480px) {
			body {
				padding: 1.25rem 1rem;
			}

			.tuinman-foto {
				width: min(40%, 130px);
				margin: 0 0 0.75rem 1rem;
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

		<div class="info-block">
			<h2>Wanneer: 24 oktober</h2>
			<p>De spooktocht start op twee momenten, ingedeeld op leeftijd.</p>
			<p>18.30 uur start de eerste goep (7 tot ca 12 jaar) en 20.00 uur start de tweede groep (ca 12 tot 16 jaar).<br />
				De eerste goep zal rond 20.15 terug zijn de tweede groep rond 21.30 uur.</p>
			<p>Voor de aanmeldingen van groepjes met kinderen tot 12 jaar vragen we om één volwassen begeleider mee te laten lopen.</p>
			<p>We beginnen met een verhaal, waarna de tocht in kleine groepjes van ca 5 kinderen wordt gelopen. Bij terugkomst is wat te drinken en wat lekkers bij het kampvuur.</p>
		</div>

		<div class="info-block">
			<h2>Waar: Kwekerijweg 5 Gorssel</h2>
			<p>De spooktocht wordt georganiseerd door Scouting IJsselgroep en Scouting Kompasnaald. De start is bij het scouting gebouw van de IJsselgroep aan de <a href="https://www.google.com/maps/place/Scouting+IJsselgroep/@52.1998177,6.212443,641m/data=!3m1!1e3!4m15!1m8!3m7!1s0x47c7eb4d62bce631:0xd4dfd625da6e06d3!2sKwekerijweg+5,+7213+AX+Gorssel!3b1!8m2!3d52.1998177!4d6.2150233!16s%2Fg%2F11n07mhxrh!3m5!1s0x47c7eb4d62b3aa65:0x3e3e33a31c3c43ba!8m2!3d52.1998772!4d6.21503!16s%2Fg%2F1thfdp7v?entry=ttu&g_ep=EgoyMDI2MTAwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank" rel="noopener noreferrer">Kwekerijweg 5 in Gorssel</a>.</p>
		</div>

		<div class="info-block">
			<h2>Voor wie: iedereen van 7 tot 16 jaar</h2>
			<p>De spooktocht is voor iedereen van 7 tot 16 jaar. Ook als je geen lid bent van scouting ben je welkom!</p>
		</div>

		<div class="info-block">
			<h2>Wat kost het: € 5,- per persoon</h2>
			<p>De kosten voor deelname zijn € 5,- per persoon. Dit kan contant worden betaald bij de start van de spooktocht.</p>
		</div>

		<div class="info-block">
			<img class="tuinman-foto" src="img/tuinman_foto.png" alt="De tuinman van de verlaten kas" />
			<h2>Meer informatie</h2>
			<p>Heb je nog vragen, mail ons via <a href="mailto:info@scouting-ijsselgroep.nl">info@scouting-ijsselgroep.nl</a></p>
		</div>
		
		<h2>Aanmelden</h2>

	</main>
</body>
</html>