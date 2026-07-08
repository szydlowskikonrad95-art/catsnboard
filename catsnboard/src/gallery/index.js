/**
 * Editor for the `catsnboard/gallery` block.
 *
 * One block = the whole Gallery subpage. Client edits the hero texts inline
 * (RichText) and swaps every mosaic photo (MediaUpload) with the mouse. The
 * front is rendered server-side by render.php, so save() is null (dynamic block).
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

// 12 mosaic slots — fixed layout classes, 1:1 with render.php / page-gallery.php.
const GALLERY_SLOTS = [
	{ cls: 'big', label: 'Big (2×2)' },
	{ cls: 'tall', label: 'Tall (1×2)' },
	{ cls: '', label: 'Small' },
	{ cls: '', label: 'Small' },
	{ cls: 'wide', label: 'Wide (2×1)' },
	{ cls: '', label: 'Small' },
	{ cls: 'tall', label: 'Tall (1×2)' },
	{ cls: '', label: 'Small' },
	{ cls: 'wide', label: 'Wide (2×1)' },
	{ cls: '', label: 'Small' },
	{ cls: 'big', label: 'Big (2×2)' },
	{ cls: '', label: 'Small' },
];

// Default theme photo per slot — 1:1 with $gallery_defaults in render.php.
// Shown in the editor when a slot has no client-uploaded image, so the
// editor preview matches what visitors already see on the front.
const GALLERY_DEFAULTS = [
	{ file: 'kot-1.jpg', alt: 'Cat with toy' },
	{ file: 'kot-6.jpg', alt: 'Cat portrait' },
	{ file: 'kot-3.jpg', alt: 'Cat relaxing' },
	{ file: 'kot-8.jpg', alt: 'Cat playing' },
	{ file: 'kot-2.jpg', alt: 'Cat looking up' },
	{ file: 'kot-15.jpg', alt: 'Cat resting' },
	{ file: 'kot-4.jpg', alt: 'Cat by window' },
	{ file: 'kot-11.jpg', alt: 'Curious cat' },
	{ file: 'kot-7.jpg', alt: 'Sleepy cat' },
	{ file: 'kot-13.jpg', alt: 'Cat on blanket' },
	{ file: 'kot-10.jpg', alt: 'Cat close up' },
	{ file: 'kot-16.jpg', alt: 'Playful kitten' },
];

// Theme image base URL, passed from PHP via wp_localize_script (see
// catsnboard_gallery_editor_assets() in functions.php). Empty when the
// localize call didn't run (e.g. block previewed outside wp-admin).
const IMG_BASE = window.catsnboardGallery?.imgBase || '';
const defaultImgUrl = ( file ) => IMG_BASE + file;

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'cnb-gallery-editor' } );
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
						title={ __( 'Gallery block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij tekst nagłówka na podglądzie i pisz. Każde zdjęcie mozaiki zmieniasz przyciskiem „Zmień” pod nim. Układ kafelków (duży/wysoki/szeroki) jest stały (v1).',
								'catsnboard'
							) }
						</p>
					</PanelBody>
				</InspectorControls>

				{ /* ═══ PAGE HERO ═══ */ }
				<section className="page-hero">
					<div className="wrap">
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
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { heroTitle: v } )
							}
							placeholder={ __( 'Gallery title…', 'catsnboard' ) }
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
					</div>
				</section>

				{ /* ═══ FULL GALLERY (12 editable photos) ═══ */ }
				<section className="gallery">
					<div className="cnb-gallery-grid">
						{ GALLERY_SLOTS.map( ( slot, i ) => {
							const img = gallery[ i ];
							const hasCustomImg = !! ( img && img.url );
							const def = GALLERY_DEFAULTS[ i ];
							const defaultUrl = def && IMG_BASE ? defaultImgUrl( def.file ) : '';
							return (
								<div className="cnb-gslot" key={ i }>
									<span className="cnb-gslot-tag">
										{ `#${ i + 1 } · ${ slot.label }` }
									</span>
									{ hasCustomImg ? (
										<img
											src={ img.url }
											alt={ img.alt || '' }
										/>
									) : defaultUrl ? (
										<img
											className="cnb-default-img"
											src={ defaultUrl }
											alt={ def.alt }
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
				</section>
			</div>
		);
	},
	save: () => null,
} );
