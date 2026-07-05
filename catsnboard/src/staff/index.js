/**
 * Editor for the `catsnboard/staff` block.
 *
 * One block = the whole "Our Team" subpage. Client edits the hero texts inline
 * (RichText) with the mouse. The 12 team members render server-side from the
 * theme (catsnboard_team), so they are shown here as a fixed preview note (not
 * editable in v1). The front is rendered by render.php, so save() is null
 * (dynamic block).
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
		const blockProps = useBlockProps( { className: 'cnb-staff-editor' } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Team block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz. Zdjęcia i imiona członków zespołu renderują się na froncie z motywu — w v1 nie edytujesz ich tutaj.',
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
							allowedFormats={ [ 'core/italic' ] }
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

				{ /* ═══ TEAM GRID (theme-driven) ═══ */ }
				<section className="team">
					<div className="wrap">
						<p className="cnb-note">
							{ __(
								'Członkowie zespołu (zdjęcia + imiona) renderują się na froncie z motywu — w v1 nie edytujesz ich tutaj.',
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
