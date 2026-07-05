/**
 * Editor for the `catsnboard/about` block.
 *
 * One block = the whole About subpage. Client edits every text inline
 * (RichText) with the mouse — hero, the "our story" paragraphs, the three value
 * cards and the button. The paw icons and layout are theme-driven, so they show
 * here as a fixed preview. The front is rendered server-side by render.php, so
 * save() is null (dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.css';
import './editor.css';

const VALUES = [
	{ title: 'value1Title', text: 'value1Text' },
	{ title: 'value2Title', text: 'value2Text' },
	{ title: 'value3Title', text: 'value3Text' },
];

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'cnb-about-editor' } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'About block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz. Ikony łapek i układ są stałe (v1) — edytujesz same teksty: nagłówek, historię, trzy wartości i przycisk.',
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

				{ /* ═══ OUR STORY ═══ */ }
				<section className="location">
					<div className="wrap">
						<div className="lgrid">
							<div>
								<RichText
									tagName="span"
									className="eyebrow"
									value={ attributes.storyEyebrow }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { storyEyebrow: v } )
									}
									placeholder={ __(
										'Eyebrow…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="h2"
									className="round"
									value={ attributes.storyTitle }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { storyTitle: v } )
									}
									placeholder={ __(
										'Story title…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									value={ attributes.storyP1 }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { storyP1: v } )
									}
									placeholder={ __(
										'First paragraph…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									value={ attributes.storyP2 }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { storyP2: v } )
									}
									placeholder={ __(
										'Second paragraph…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="span"
									className="btn"
									value={ attributes.ctaText }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { ctaText: v } )
									}
									placeholder={ __(
										'Button…',
										'catsnboard'
									) }
								/>
							</div>
							<div className="cnb-about-values">
								<RichText
									tagName="span"
									className="eyebrow"
									value={ attributes.valuesTitle }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { valuesTitle: v } )
									}
									placeholder={ __(
										'Values title…',
										'catsnboard'
									) }
								/>
								<div className="cnb-values-grid">
									{ VALUES.map( ( v, i ) => (
										<div className="cnb-value" key={ i }>
											<span className="cnb-paw" aria-hidden="true">🐾</span>
											<div className="cnb-value-copy">
												<RichText
													tagName="b"
													value={ attributes[ v.title ] }
													allowedFormats={ [] }
													onChange={ ( val ) =>
														setAttributes( {
															[ v.title ]: val,
														} )
													}
													placeholder={ __(
														'Value…',
														'catsnboard'
													) }
												/>
												<RichText
													tagName="small"
													value={ attributes[ v.text ] }
													allowedFormats={ [] }
													onChange={ ( val ) =>
														setAttributes( {
															[ v.text ]: val,
														} )
													}
													placeholder={ __(
														'Short description…',
														'catsnboard'
													) }
												/>
											</div>
										</div>
									) ) }
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
		);
	},
	save: () => null,
} );
