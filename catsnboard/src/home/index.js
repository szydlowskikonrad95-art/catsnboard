/**
 * Editor for the `catsnboard/home` block.
 *
 * One block = the whole Home page. Client edits texts inline (RichText) and
 * swaps hero + gallery photos (MediaUpload) with the mouse. The front is
 * rendered server-side by render.php, so save() is null (dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
} from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.css';
import './editor.css';

const GALLERY_SLOTS = [
	{ cls: 'big', label: 'Big (2×2)' },
	{ cls: 'tall', label: 'Tall (1×2)' },
	{ cls: '', label: 'Small' },
	{ cls: '', label: 'Small' },
	{ cls: 'wide', label: 'Wide (2×1)' },
	{ cls: '', label: 'Small' },
];

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'cnb-home-editor' } );
		const gallery = Array.isArray( attributes.galleryImages )
			? attributes.galleryImages
			: [];

		const setGallery = ( index, media ) => {
			const next = gallery.slice();
			next[ index ] = media
				? { url: media.url, alt: media.alt || '', id: media.id }
				: undefined;
			setAttributes( { galleryImages: next } );
		};

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Home block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz. Zdjęcia zmieniasz przyciskiem „Zmień zdjęcie”. Ikony usług i układ galerii są stałe (v1).',
								'catsnboard'
							) }
						</p>
					</PanelBody>
				</InspectorControls>

				{ /* ═══ HERO ═══ */ }
				<header className="hero" style={ { minHeight: 'auto' } }>
					<div className="hero-copy">
						<RichText
							tagName="span"
							className="eyebrow"
							value={ attributes.heroEyebrow }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { heroEyebrow: v } )
							}
							placeholder={ __( 'Eyebrow…', 'catsnboard' ) }
						/>
						<RichText
							tagName="h1"
							className="round"
							value={ attributes.heroTitle }
							allowedFormats={ [ 'core/italic' ] }
							onChange={ ( v ) =>
								setAttributes( { heroTitle: v } )
							}
							placeholder={ __( 'Hero title…', 'catsnboard' ) }
						/>
						<RichText
							tagName="p"
							value={ attributes.heroLead }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { heroLead: v } )
							}
							placeholder={ __( 'Hero lead…', 'catsnboard' ) }
						/>
						<RichText
							tagName="span"
							className="btn"
							value={ attributes.heroCta }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { heroCta: v } )
							}
							placeholder={ __( 'Button…', 'catsnboard' ) }
						/>
					</div>
					<div className="hero-photo">
						{ attributes.heroImageUrl ? (
							<img
								className="graded"
								src={ attributes.heroImageUrl }
								alt={ attributes.heroImageAlt }
							/>
						) : (
							<div className="cnb-ph">
								{ __(
									'Domyślne zdjęcie hero (kot-hero.jpg)',
									'catsnboard'
								) }
							</div>
						) }
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										heroImageUrl: media.url,
										heroImageAlt:
											media.alt ||
											attributes.heroImageAlt,
									} )
								}
								allowedTypes={ [ 'image' ] }
								render={ ( { open } ) => (
									<div className="cnb-imgbtns">
										<Button
											variant="secondary"
											onClick={ open }
										>
											{ __(
												'Zmień zdjęcie hero',
												'catsnboard'
											) }
										</Button>
										{ attributes.heroImageUrl && (
											<Button
												variant="tertiary"
												isDestructive
												onClick={ () =>
													setAttributes( {
														heroImageUrl: '',
													} )
												}
											>
												{ __(
													'Przywróć domyślne',
													'catsnboard'
												) }
											</Button>
										) }
									</div>
								) }
							/>
						</MediaUploadCheck>
					</div>
				</header>

				{ /* ═══ SERVICES ═══ */ }
				<section className="services">
					<div className="wrap">
						<div className="head">
							<RichText
								tagName="h2"
								className="round"
								value={ attributes.servicesTitle }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { servicesTitle: v } )
								}
								placeholder={ __(
									'Services title…',
									'catsnboard'
								) }
							/>
						</div>
						<p className="cnb-note">
							{ __(
								'Kafelki usług (8) i ich ikony renderują się na froncie z motywu — w v1 nie edytujesz ich tutaj.',
								'catsnboard'
							) }
						</p>
						<div className="cta-row">
							<RichText
								tagName="span"
								className="btn"
								value={ attributes.servicesCta }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { servicesCta: v } )
								}
								placeholder={ __( 'Button…', 'catsnboard' ) }
							/>
						</div>
					</div>
				</section>

				{ /* ═══ GALLERY ═══ */ }
				<section className="gallery">
					<div className="wrap">
						<div className="head">
							<RichText
								tagName="span"
								className="eyebrow"
								value={ attributes.galleryEyebrow }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { galleryEyebrow: v } )
								}
								placeholder={ __(
									'Eyebrow…',
									'catsnboard'
								) }
							/>
							<RichText
								tagName="h2"
								className="round"
								value={ attributes.galleryTitle }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { galleryTitle: v } )
								}
								placeholder={ __(
									'Gallery title…',
									'catsnboard'
								) }
							/>
						</div>
						<div className="cnb-gallery-grid">
							{ GALLERY_SLOTS.map( ( slot, i ) => {
								const img = gallery[ i ];
								return (
									<div className="cnb-gslot" key={ i }>
										{ img && img.url ? (
											<img
												src={ img.url }
												alt={ img.alt || '' }
											/>
										) : (
											<div className="cnb-ph small">
												{ slot.label }
											</div>
										) }
										<MediaUploadCheck>
											<MediaUpload
												onSelect={ ( media ) =>
													setGallery( i, media )
												}
												allowedTypes={ [ 'image' ] }
												render={ ( { open } ) => (
													<Button
														variant="secondary"
														className="cnb-gbtn"
														onClick={ open }
													>
														{ __(
															'Zmień',
															'catsnboard'
														) }
													</Button>
												) }
											/>
										</MediaUploadCheck>
										{ img && img.url && (
											<Button
												variant="tertiary"
												isDestructive
												className="cnb-gbtn"
												onClick={ () =>
													setGallery( i, null )
												}
											>
												{ __(
													'Domyślne',
													'catsnboard'
												) }
											</Button>
										) }
									</div>
								);
							} ) }
						</div>
						<div className="cta-row">
							<RichText
								tagName="span"
								className="btn"
								value={ attributes.galleryCta }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { galleryCta: v } )
								}
								placeholder={ __( 'Button…', 'catsnboard' ) }
							/>
						</div>
					</div>
				</section>

				{ /* ═══ FINAL / CTA ═══ */ }
				<section className="final">
					<div className="wrap">
						<RichText
							tagName="span"
							className="eyebrow"
							value={ attributes.finalEyebrow }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { finalEyebrow: v } )
							}
							placeholder={ __( 'Eyebrow…', 'catsnboard' ) }
						/>
						<RichText
							tagName="h2"
							className="round"
							value={ attributes.finalTitle }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { finalTitle: v } )
							}
							placeholder={ __( 'Final title…', 'catsnboard' ) }
						/>
						<RichText
							tagName="p"
							value={ attributes.finalLead }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { finalLead: v } )
							}
							placeholder={ __( 'Final lead…', 'catsnboard' ) }
						/>
						<RichText
							tagName="span"
							className="btn"
							value={ attributes.finalCta }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { finalCta: v } )
							}
							placeholder={ __( 'Button…', 'catsnboard' ) }
						/>
					</div>
				</section>
			</div>
		);
	},
	save: () => null,
} );
