// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightLinksValidator from 'starlight-links-validator';

export default defineConfig({
	// Produkční adresa. Starlight z ní staví mapu webu a absolutní odkazy;
	// bez ní se sitemap negeneruje vůbec. Web běží v kořeni domény, takže se
	// `base` nenastavuje.
	site: 'https://napoveda.sokct.cz',
	integrations: [
		starlight({
			title: 'Nápověda k šabloně KČT',
			description:
				'Uživatelská příručka k webové šabloně pro odbory a oblasti Klubu českých turistů.',
			plugins: [starlightLinksValidator()],
			// Jediný jazyk webu je čeština. Locale `root` znamená, že stránky
			// leží přímo v src/content/docs a v adresách není jazyková předpona.
			locales: {
				root: { label: 'Čeština', lang: 'cs' },
			},
			sidebar: [
				{ label: 'Začínáme', items: [{ autogenerate: { directory: 'zaciname' } }] },
				{
					label: 'Základy WordPressu',
					items: [{ autogenerate: { directory: 'zaklady-wordpressu' } }],
				},
				{ label: 'Funkce šablony', items: [{ autogenerate: { directory: 'funkce' } }] },
				{ label: 'Pro správce', items: [{ autogenerate: { directory: 'spravce' } }] },
			],
			pagination: true,
			lastUpdated: true,
		}),
	],
});
