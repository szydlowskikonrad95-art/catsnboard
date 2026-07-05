/**
 * Edytor bloku `pnb/galeria` — BEZ builda (czysty JS, wp.element.createElement).
 *
 * Blok = cała galeria kotów (taśma kinowa na froncie). W edytorze klient:
 *  - klika teksty hero i pisze (RichText: eyebrow, tytuł, watermark, podpowiedź),
 *  - dodaje / usuwa / porządkuje zdjęcia (MediaUpload — multi).
 * Front renderuje render.php (woła gotową taśmę pnb_galeria_render), więc save() = null (blok dynamiczny).
 * Zdjęcia zapisują się w atrybucie imageIds; render.php synchronizuje je z opcją pnb_galeria_zdjecia
 * (jedno źródło — ta sama pula co panel admina i front).
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var RichText = wp.blockEditor.RichText;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var Button = wp.components.Button;
	var PanelBody = wp.components.PanelBody;

	registerBlockType( 'pnb/galeria', {
		edit: function ( props ) {
			var a = props.attributes;
			var setA = props.setAttributes;
			var blockProps = useBlockProps( { className: 'pnb-galeria-editor' } );

			// dane wybranych zdjęć (URL miniatur) — przekazane z PHP (localize) po ID.
			var media = ( window.pnbGaleriaBlok && window.pnbGaleriaBlok.media ) || {};
			var ids = Array.isArray( a.imageIds ) ? a.imageIds : [];         // TAŚMA (górna karuzela)
			var momentIds = Array.isArray( a.momentsIds ) ? a.momentsIds : []; // RZEKI „Moments that stay" (osobny zestaw)
			var heroUrl = a.heroImageUrl || '';

			function ustawHero( m ) {
				var url = m ? ( ( m.sizes && m.sizes.large && m.sizes.large.url ) || m.url ) : '';
				setA( { heroImageId: m ? m.id : 0, heroImageUrl: url } );
			}

			// zmiana zestawu zdjęć (multi-select z biblioteki)
			function onSelect( sel ) {
				var newIds = ( sel || [] ).map( function ( m ) { return m.id; } );
				// dopisz URL-e do cache media (żeby od razu pokazać bez reloadu)
				( sel || [] ).forEach( function ( m ) {
					var url = ( m.sizes && m.sizes.thumbnail && m.sizes.thumbnail.url ) || m.url;
					media[ m.id ] = url;
				} );
				setA( { imageIds: newIds } );
			}
			function usun( id ) {
				setA( { imageIds: ids.filter( function ( x ) { return x !== id; } ) } );
			}
			function przesun( id, kier ) {
				var i = ids.indexOf( id );
				var j = i + kier;
				if ( i < 0 || j < 0 || j >= ids.length ) { return; }
				var next = ids.slice();
				var tmp = next[ i ]; next[ i ] = next[ j ]; next[ j ] = tmp;
				setA( { imageIds: next } );
			}

			// ── to samo dla RZEK „Moments" (drugi, niezależny zestaw = momentsIds) ──
			function onSelectMoments( sel ) {
				var newIds = ( sel || [] ).map( function ( m ) { return m.id; } );
				( sel || [] ).forEach( function ( m ) {
					var url = ( m.sizes && m.sizes.thumbnail && m.sizes.thumbnail.url ) || m.url;
					media[ m.id ] = url;
				} );
				setA( { momentsIds: newIds } );
			}
			function usunM( id ) {
				setA( { momentsIds: momentIds.filter( function ( x ) { return x !== id; } ) } );
			}
			function przesunM( id, kier ) {
				var i = momentIds.indexOf( id );
				var j = i + kier;
				if ( i < 0 || j < 0 || j >= momentIds.length ) { return; }
				var next = momentIds.slice();
				var tmp = next[ i ]; next[ i ] = next[ j ]; next[ j ] = tmp;
				setA( { momentsIds: next } );
			}

			// ── kafelki zdjęć (wspólny generator dla obu zestawów: taśma i Moments) ──
			function zrobKafelki( lista, onPrzesun, onUsun ) {
				return lista.map( function ( id ) {
					var url = media[ id ];
					return el( 'div', { className: 'pnb-blk-tile', key: id },
						url
							? el( 'img', { src: url, alt: '' } )
							: el( 'div', { className: 'pnb-blk-ph' }, '#' + id ),
						el( 'div', { className: 'pnb-blk-tile-btns' },
							el( Button, { className: 'pnb-blk-mini', onClick: function () { onPrzesun( id, -1 ); }, label: __( 'Move left', 'pnb-toolkit' ) }, '←' ),
							el( Button, { className: 'pnb-blk-mini', onClick: function () { onPrzesun( id, 1 ); }, label: __( 'Move right', 'pnb-toolkit' ) }, '→' ),
							el( Button, { className: 'pnb-blk-mini pnb-blk-x', isDestructive: true, onClick: function () { onUsun( id ); }, label: __( 'Remove', 'pnb-toolkit' ) }, '×' )
						)
					);
				} );
			}
			var kafelki  = zrobKafelki( ids, przesun, usun );        // taśma
			var kafelkiM = zrobKafelki( momentIds, przesunM, usunM ); // Moments

			// ── SEKCJA „Moments" — EDYTOWALNE zdjęcia (drugi, osobny zestaw = momentsIds) ──
				// Zamiast statycznego podglądu: prawdziwa siatka kafelków + własny „Choose photos"
				// (tryb APPEND: gallery:true + value:momentIds — jak taśma, nie kasuje przy dodawaniu).
				// FALLBACK opisany klientowi: pusto → na froncie „Moments" pokaże zdjęcia taśmy.
				// Podgląd 1:1 z frontem: front dzieli pulę NA PÓŁ na dwie przeciwbieżne rzeki,
				// więc edytor pokazuje te same dwa rządki (góra = rzeka →, dół = rzeka ←).
				var polM = Math.ceil( kafelkiM.length / 2 );
				var momentsEdytor = el( 'div', { className: 'pnb-blk-moments-edit' },
					kafelkiM.length
						? el( 'div', {},
							el( 'p', { style: { margin: '0 0 4px', fontSize: '11px', opacity: 0.6, textAlign: 'center' } }, __( 'Strip 1 (flows right →)', 'pnb-toolkit' ) ),
							el( 'div', { className: 'pnb-blk-grid pnb-blk-grid--rzeka' }, kafelkiM.slice( 0, polM ) ),
							el( 'p', { style: { margin: '10px 0 4px', fontSize: '11px', opacity: 0.6, textAlign: 'center' } }, __( 'Strip 2 (flows left ←)', 'pnb-toolkit' ) ),
							el( 'div', { className: 'pnb-blk-grid pnb-blk-grid--rzeka' }, kafelkiM.slice( polM ) )
						)
						: el( 'div', { className: 'pnb-blk-grid' },
							el( 'p', { className: 'pnb-blk-empty' },
								__( 'No separate photos for “Moments” — the page will reuse the filmstrip photos. Add photos below to make this section independent.', 'pnb-toolkit' ) )
						),
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							onSelect: onSelectMoments,
							allowedTypes: [ 'image' ],
							multiple: true,
							gallery: true,
							value: momentIds,
							render: function ( o ) {
								return el( Button, { variant: 'primary', className: 'pnb-blk-choose', onClick: o.open },
									__( 'Choose “Moments” photos', 'pnb-toolkit' ) + ' (' + momentIds.length + ')'
								);
							}
						} )
					)
				);

				return el( 'div', blockProps,
					// panel boczny — pomoc
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Gallery — how it works', 'pnb-toolkit' ), initialOpen: true },
						el( 'p', {}, __( 'Click the heading text in the preview and type. Add or change photos with the "Choose photos" button. Arrows ← → set the order, × removes. On the page you will see an animated filmstrip.', 'pnb-toolkit' ) )
					)
				),

				// ── HERO (edytowalne teksty + opcjonalne zdjęcie w tle) ──
				el( 'section', {
					className: 'pnb-blk-hero' + ( heroUrl ? ' has-img' : '' ),
					style: heroUrl ? { backgroundImage: 'url(' + heroUrl + ')' } : {}
				},
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							onSelect: ustawHero,
							allowedTypes: [ 'image' ],
							value: a.heroImageId,
							render: function ( o ) {
								return el( 'div', { className: 'pnb-blk-hero-btns' },
									el( Button, { variant: 'secondary', className: 'pnb-blk-hero-btn', onClick: o.open },
										a.heroImageId ? __( 'Change hero image', 'pnb-toolkit' ) : __( 'Add hero image', 'pnb-toolkit' )
									),
									a.heroImageId ? el( Button, { variant: 'tertiary', isDestructive: true, className: 'pnb-blk-hero-btn', onClick: function () { ustawHero( null ); } },
										__( 'Remove image', 'pnb-toolkit' )
									) : null
								);
							}
						} )
					),
					// Pole „Meow" (heroWatermark) USUNIĘTE 2026-07-05: edytor pokazywał je, ale render frontu
					// go NIE drukuje (hero ma tylko strip-word „Gallery"). Klient edytował coś bez efektu →
					// mylące. Zgłoszenie z testów: „w edytorze jest stare Meow którego już nie ma na stronie".
					el( RichText, {
						tagName: 'span',
						className: 'pnb-blk-eyebrow',
						value: a.heroEyebrow,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroEyebrow: v } ); },
						placeholder: __( 'Label…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'h1',
						className: 'pnb-blk-title',
						value: a.heroTitle,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroTitle: v } ); },
						placeholder: __( 'Gallery heading…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'pnb-blk-hint',
						value: a.heroHint,
						allowedFormats: [],
						onChange: function ( v ) { setA( { heroHint: v } ); },
						placeholder: __( 'Hint…', 'pnb-toolkit' )
					} )
				),

				// ── ZDJĘCIA ──
				el( 'section', { className: 'pnb-blk-gallery' },
					el( 'div', { className: 'pnb-blk-grid' },
						kafelki.length ? kafelki : el( 'p', { className: 'pnb-blk-empty' }, __( 'No photos chosen yet — the page temporarily shows the theme’s demo cats. Click “Choose photos” to use your own.', 'pnb-toolkit' ) )
					),
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							onSelect: onSelect,
							allowedTypes: [ 'image' ],
							multiple: true,
							gallery: true,
							value: ids,
							render: function ( o ) {
								return el( Button, { variant: 'primary', className: 'pnb-blk-choose', onClick: o.open },
									__( 'Choose photos', 'pnb-toolkit' ) + ' (' + ids.length + ')'
								);
							}
						} )
					)
				),

				// ── SEKCJA ŚRODKOWA „Moments that stay" (nagłówek + WŁASNE edytowalne zdjęcia) ──
				el( 'section', { className: 'pnb-blk-mid' },
					el( 'span', { className: 'pnb-blk-sekcja-tag' }, __( 'Middle section — “Moments” photos', 'pnb-toolkit' ) ),
					el( RichText, {
						tagName: 'h2',
						className: 'pnb-blk-h2',
						value: a.midTitle,
						allowedFormats: [ 'core/italic' ],
						onChange: function ( v ) { setA( { midTitle: v } ); },
						placeholder: __( 'Middle heading…', 'pnb-toolkit' )
					} ),
					// EDYTOWALNE zdjęcia rzek — osobny zestaw (momentsIds), własny „Choose photos".
					momentsEdytor
				),

				// ── SEKCJA KOŃCOWA (CTA) ──
				el( 'section', { className: 'pnb-blk-cta' },
					el( 'span', { className: 'pnb-blk-sekcja-tag' }, __( 'Closing section (invitation)', 'pnb-toolkit' ) ),
					el( RichText, {
						tagName: 'h2',
						className: 'pnb-blk-h2',
						value: a.ctaTitle,
						allowedFormats: [ 'core/italic' ],
						onChange: function ( v ) { setA( { ctaTitle: v } ); },
						placeholder: __( 'Closing heading…', 'pnb-toolkit' )
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'pnb-blk-cta-lead',
						value: a.ctaLead,
						allowedFormats: [],
						onChange: function ( v ) { setA( { ctaLead: v } ); },
						placeholder: __( 'Encouraging sentence…', 'pnb-toolkit' )
					} ),
					el( 'div', { className: 'pnb-blk-cta-btnwrap' },
						el( RichText, {
							tagName: 'span',
							className: 'pnb-blk-cta-btn',
							value: a.ctaBtn,
							allowedFormats: [],
							onChange: function ( v ) { setA( { ctaBtn: v } ); },
							placeholder: __( 'Button…', 'pnb-toolkit' )
						} )
					)
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
