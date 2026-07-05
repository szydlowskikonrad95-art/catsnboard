/**
 * Editor for the `catsnboard/services` block.
 *
 * One block = the whole Services subpage. Client edits the hero texts and the
 * pricing button inline (RichText) with the mouse. The 8 service tiles and the
 * training bar render server-side from the theme, so they are shown here as a
 * fixed preview note (not editable in v1). The front is rendered by render.php,
 * so save() is null (dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.css';
import './editor.css';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'cnb-services-editor' } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Services block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz. Kafelki usług (8) i pasek szkolenia renderują się na froncie z motywu — w v1 nie edytujesz ich tutaj.',
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
							placeholder={ __( 'Page title…', 'catsnboard' ) }
						/>
						<RichText
							tagName="p"
							value={ attributes.heroLead }
							allowedFormats={ [] }
							onChange={ ( v ) =>
								setAttributes( { heroLead: v } )
							}
							placeholder={ __( 'Lead…', 'catsnboard' ) }
						/>
					</div>
				</section>

				{ /* ═══ SERVICES GRID (theme-driven) ═══ */ }
				<section className="services">
					<div className="wrap">
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

				{ /* ═══ TRAINING BAR (theme-driven) ═══ */ }
				<section className="services">
					<div className="wrap">
						<p className="cnb-note">
							{ __(
								'Pasek „Kitten training” renderuje się na froncie z motywu (wspólny z Home) — tutaj go nie edytujesz.',
								'catsnboard'
							) }
						</p>
					</div>
				</section>
			</div>
		);
	},
	save: () => null,
} );
