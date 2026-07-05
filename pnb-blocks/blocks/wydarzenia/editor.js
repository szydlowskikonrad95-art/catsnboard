/**
 * Edytor bloku `pnb/wydarzenia` — BEZ builda (czysty JS).
 *
 * Blok = cała podstrona Events. W edytorze klient:
 *  - klika teksty hero i pisze (eyebrow, „Save the date", 2 leady),
 *  - pod spodem widzi PANEL ZARZĄDZANIA wydarzeniami (lista + „Dodaj wydarzenie" + „Edytuj") — HTML
 *    generowany przez PHP i przekazany przez window.pnbWydarzeniaBlok.panel (linki działają, klik → ekrany WP).
 * Front renderuje render.php (woła gotową galerię pnb_kalendarz_render), więc save() = null (blok dynamiczny).
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var RawHTML = wp.element.RawHTML;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var RichText = wp.blockEditor.RichText;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var Button = wp.components.Button;
	var PanelBody = wp.components.PanelBody;

	registerBlockType( 'pnb/wydarzenia', {
		edit: function ( props ) {
			var a = props.attributes;
			var setA = props.setAttributes;
			var blockProps = useBlockProps( { className: 'pnb-wyd-editor' } );
			var panelHTML = ( window.pnbWydarzeniaBlok && window.pnbWydarzeniaBlok.panel ) || '';
			// domyślne zdjęcie hero (gdy klient nie wybrał własnego) — przekazane z PHP
			var heroDomysl = ( window.pnbWydarzeniaBlok && window.pnbWydarzeniaBlok.heroDefault ) || '';
			var heroUrl = a.heroImageUrl || heroDomysl;

			function ustawHero( media ) {
				var url = media ? ( ( media.sizes && media.sizes.large && media.sizes.large.url ) || media.url ) : '';
				setA( { heroImageId: media ? media.id : 0, heroImageUrl: url } );
			}

			return el( 'div', blockProps,
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Events — how it works', 'pnb-toolkit' ), initialOpen: true },
						el( 'p', {}, __( 'Click the heading text in the preview and type. Below you manage events — add, edit, check who signed up. On the page you will see the full events gallery.', 'pnb-toolkit' ) )
					)
				),

				// ── HERO (edytowalne teksty + zdjęcie w tle) ──
				el( 'section', {
					className: 'pnb-wyd-hero' + ( heroUrl ? ' has-img' : '' ),
					style: heroUrl ? { backgroundImage: 'url(' + heroUrl + ')' } : {}
				},
					// przycisk zmiany zdjęcia hero (róg)
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							onSelect: ustawHero,
							allowedTypes: [ 'image' ],
							value: a.heroImageId,
							render: function ( o ) {
								return el( 'div', { className: 'pnb-wyd-hero-btns' },
									el( Button, { variant: 'secondary', className: 'pnb-wyd-hero-btn', onClick: o.open },
										a.heroImageId ? __( 'Change hero image', 'pnb-toolkit' ) : __( 'Choose hero image', 'pnb-toolkit' )
									),
									a.heroImageId ? el( Button, { variant: 'tertiary', isDestructive: true, className: 'pnb-wyd-hero-btn', onClick: function () { ustawHero( null ); } },
										__( 'Default', 'pnb-toolkit' )
									) : null
								);
							}
						} )
					),
					el( RichText, {
						tagName: 'span',
						className: 'pnb-wyd-eyebrow',
						value: a.heroEyebrow,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroEyebrow: v } ); },
						placeholder: __( 'Label…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'h1',
						className: 'pnb-wyd-title',
						value: a.heroTitle,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroTitle: v } ); },
						placeholder: __( 'Heading…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'pnb-wyd-lead',
						value: a.heroLead1,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroLead1: v } ); },
						placeholder: __( 'Sentence 1…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'pnb-wyd-lead2',
						value: a.heroLead2,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroLead2: v } ); },
						placeholder: __( 'Sentence 2…', 'pnb-toolkit' )
					} )
				),

				// ── PANEL ZARZĄDZANIA WYDARZENIAMI (HTML z PHP) ──
				el( 'section', { className: 'pnb-wyd-panel' },
					el( 'span', { className: 'pnb-wyd-sekcja-tag' }, __( 'Event management', 'pnb-toolkit' ) ),
					panelHTML
						? el( RawHTML, {}, panelHTML )
						: el( 'p', { className: 'pnb-wyd-empty' }, __( 'Loading the events list…', 'pnb-toolkit' ) )
				),

				// ── SEKCJA MAPY „Come say hi" ──
				el( 'section', { className: 'pnb-wyd-map' },
					el( 'span', { className: 'pnb-wyd-sekcja-tag' }, __( '"Where to find us" section', 'pnb-toolkit' ) ),
					el( RichText, {
						tagName: 'span',
						className: 'pnb-wyd-map-eyebrow',
						value: a.mapEyebrow,
						allowedFormats: [],
						onChange: function ( v ) { setA( { mapEyebrow: v } ); },
						placeholder: __( 'Map label…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'h2',
						className: 'pnb-wyd-map-title',
						value: a.mapTitle,
						allowedFormats: [ 'core/italic' ],
						onChange: function ( v ) { setA( { mapTitle: v } ); },
						placeholder: __( 'Map heading…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'pnb-wyd-map-lead',
						value: a.mapLead,
						allowedFormats: [],
						onChange: function ( v ) { setA( { mapLead: v } ); },
						placeholder: __( 'Location description…', 'pnb-toolkit' )
					} ),
					// Adres + etykieta pinezki — PRAWDZIWE dane klienta (puste = sekcja bez adresu,
					// zero zmyślonych fallbacków; audyt 2026-07-05).
					el( RichText, {
						tagName: 'p',
						className: 'pnb-wyd-map-address',
						value: a.mapAddress,
						allowedFormats: [],
						onChange: function ( v ) { setA( { mapAddress: v } ); },
						placeholder: __( 'Street address (used for “Get directions”)…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'pnb-wyd-map-label',
						value: a.mapLabel,
						allowedFormats: [],
						onChange: function ( v ) { setA( { mapLabel: v } ); },
						placeholder: __( 'Short label on the map pin…', 'pnb-toolkit' )
					} )
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
