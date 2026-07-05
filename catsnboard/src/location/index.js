/**
 * Editor for the `catsnboard/location` block.
 *
 * One block = the whole "Our Facilities / Our Location" subpage. Client edits
 * texts inline (RichText) with the mouse. The illustrated MAP (streets + pin)
 * is a decorative SVG kept 1:1 from the theme — only its label and the address
 * line are editable text. The front is rendered server-side by render.php, so
 * save() is null (dynamic block).
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
		const blockProps = useBlockProps( { className: 'cnb-location-editor' } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Location block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz. Ilustrowana mapa (uliczki + pinezka) jest stała — edytujesz tylko jej podpis oraz adres.',
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
					</div>
				</section>

				{ /* ═══ LOCATION + MAP ═══ */ }
				<section className="location">
					<div className="wrap">
						<div className="lgrid">
							<div>
								<RichText
									tagName="span"
									className="eyebrow"
									value={ attributes.secEyebrow }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { secEyebrow: v } )
									}
									placeholder={ __(
										'Eyebrow…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="h2"
									className="round"
									value={ attributes.secTitle }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { secTitle: v } )
									}
									placeholder={ __(
										'Section title…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									value={ attributes.secP1 }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { secP1: v } )
									}
									placeholder={ __(
										'Paragraph 1…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									value={ attributes.secP2 }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { secP2: v } )
									}
									placeholder={ __(
										'Paragraph 2…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="div"
									className="addr"
									value={ attributes.address }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { address: v } )
									}
									placeholder={ __(
										'Address…',
										'catsnboard'
									) }
								/>
							</div>

							{ /* Illustrated map — decorative SVG kept 1:1, only .lab is editable. */ }
							<div className="map">
								<svg
									className="streets"
									viewBox="0 0 400 275"
									preserveAspectRatio="none"
									aria-hidden="true"
								>
									<rect className="park" x="24" y="24" width="96" height="66" rx="14" />
									<rect className="park" x="290" y="180" width="86" height="70" rx="14" />
									<path className="road big" d="M0 92 C130 84 240 70 400 66" />
									<path className="road big" d="M0 188 C120 196 250 210 400 202" />
									<path className="road sm" d="M110 0 C102 90 96 190 92 275" />
									<path className="road sm" d="M272 0 C282 90 292 190 300 275" />
									<path className="road sm" d="M0 140 C120 132 180 200 400 150" />
									<path className="route" d="M300 200 C240 175 210 150 200 121" />
								</svg>
								<svg
									className="pin"
									viewBox="0 0 24 24"
									fill="currentColor"
									aria-hidden="true"
								>
									<path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z" />
									<circle cx="12" cy="9" r="2.6" fill="#fff" />
								</svg>
								<RichText
									tagName="div"
									className="lab"
									value={ attributes.mapLabel }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { mapLabel: v } )
									}
									placeholder={ __(
										'Map label…',
										'catsnboard'
									) }
								/>
							</div>
						</div>
					</div>
				</section>
			</div>
		);
	},
	save: () => null,
} );
