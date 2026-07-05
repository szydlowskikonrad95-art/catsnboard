/**
 * Editor for the `catsnboard/contact` block.
 *
 * One block = the whole Contact page. Client edits every text inline (RichText)
 * with the mouse — hero, contact details (phone/email/address), and the form's
 * labels + button + note. The front is rendered server-side by render.php, so
 * save() is null (dynamic block).
 *
 * The FORM structure is fixed (ids/types/placeholders stay identical to
 * page-contact.php); the inputs are shown disabled here as a realistic preview.
 * Only the visible texts around them are editable — so the working demo form is
 * never broken by the editor.
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
		const blockProps = useBlockProps( { className: 'cnb-contact-editor' } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Contact block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz — nagłówki, telefon, e-mail, adres oraz etykiety formularza. Pola formularza (okienka do wpisywania) są stałe. Formularz to wersja demo — żeby realnie wysyłał, podłącz wtyczkę (Contact Form 7 / WPForms).',
								'catsnboard'
							) }
						</p>
					</PanelBody>
				</InspectorControls>

				{ /* ═══ HERO ═══ */ }
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

				{ /* ═══ CONTACT INFO + FORM ═══ */ }
				<section className="contact-sec">
					<div className="wrap">
						<div className="cgrid">
							<div className="cinfo">
								<RichText
									tagName="span"
									className="eyebrow"
									value={ attributes.infoEyebrow }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { infoEyebrow: v } )
									}
									placeholder={ __(
										'Eyebrow…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="h2"
									className="round"
									value={ attributes.infoTitle }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { infoTitle: v } )
									}
									placeholder={ __(
										'Title…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									value={ attributes.infoLead }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { infoLead: v } )
									}
									placeholder={ __(
										'Intro…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									className="cnb-cinfo-line"
									value={ attributes.infoPhone }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { infoPhone: v } )
									}
									placeholder={ __(
										'Phone…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									className="cnb-cinfo-line"
									value={ attributes.infoEmail }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { infoEmail: v } )
									}
									placeholder={ __(
										'Email…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="p"
									value={ attributes.infoAddress }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { infoAddress: v } )
									}
									placeholder={ __(
										'Address…',
										'catsnboard'
									) }
								/>
							</div>

							{ /* Form preview — inputs disabled, labels/button/note editable. */ }
							<div className="cform cnb-form-preview">
								<RichText
									tagName="label"
									value={ attributes.formName }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { formName: v } )
									}
									placeholder={ __(
										'Field label…',
										'catsnboard'
									) }
								/>
								<input
									type="text"
									placeholder="Jane Doe"
									disabled
								/>
								<RichText
									tagName="label"
									value={ attributes.formEmail }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { formEmail: v } )
									}
									placeholder={ __(
										'Field label…',
										'catsnboard'
									) }
								/>
								<input
									type="email"
									placeholder="jane@example.com"
									disabled
								/>
								<RichText
									tagName="label"
									value={ attributes.formCat }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { formCat: v } )
									}
									placeholder={ __(
										'Field label…',
										'catsnboard'
									) }
								/>
								<input
									type="text"
									placeholder="Whiskers"
									disabled
								/>
								<RichText
									tagName="label"
									value={ attributes.formMsg }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { formMsg: v } )
									}
									placeholder={ __(
										'Field label…',
										'catsnboard'
									) }
								/>
								<textarea
									placeholder="Tell us a little about your cat and the dates you have in mind…"
									disabled
								/>
								<RichText
									tagName="span"
									className="btn"
									value={ attributes.formSend }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { formSend: v } )
									}
									placeholder={ __(
										'Button…',
										'catsnboard'
									) }
								/>
								<RichText
									tagName="span"
									className="note"
									value={ attributes.formNote }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { formNote: v } )
									}
									placeholder={ __(
										'Small note…',
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
