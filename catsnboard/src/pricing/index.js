/**
 * Editor for the `catsnboard/pricing` block.
 *
 * One block = the whole Pricing subpage. The client edits EVERYTHING inline with
 * the mouse (RichText): hero texts, each card's title, price, unit, the 4 feature
 * lines, and the button label. Prices are plain editable text (e.g. "49 zł") so
 * the client can change them by clicking. The front is rendered server-side by
 * render.php, so save() is null (dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.css';
import './editor.css';

// The 3 cards, described declaratively so the editor markup mirrors render.php
// (Boarding = featured/coral). Each entry maps its RichText fields to attributes.
const CARDS = [
	{
		featured: false,
		title: 'daycareTitle',
		price: 'daycarePrice',
		unit: 'daycareUnit',
		feats: [ 'daycareF1', 'daycareF2', 'daycareF3', 'daycareF4' ],
	},
	{
		featured: true,
		title: 'boardingTitle',
		price: 'boardingPrice',
		unit: 'boardingUnit',
		feats: [ 'boardingF1', 'boardingF2', 'boardingF3', 'boardingF4' ],
	},
	{
		featured: false,
		title: 'sittingTitle',
		price: 'sittingPrice',
		unit: 'sittingUnit',
		feats: [ 'sittingF1', 'sittingF2', 'sittingF3', 'sittingF4' ],
	},
];

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'cnb-pricing-editor' } );

		const field = ( attr, tag, cls, placeholder, formats = [] ) => (
			<RichText
				tagName={ tag }
				className={ cls }
				value={ attributes[ attr ] }
				allowedFormats={ formats }
				onChange={ ( v ) => setAttributes( { [ attr ]: v } ) }
				placeholder={ placeholder }
			/>
		);

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Pricing block info', 'catsnboard' ) }
						initialOpen={ true }
					>
						<p>
							{ __(
								'Kliknij dowolny tekst na podglądzie i pisz — również CENY (np. „49 zł”), jednostki („/ day”) i pozycje w kartach. Wszystko jest edytowalne myszką. Środkowa karta (Boarding) jest wyróżniona jak na froncie.',
								'catsnboard'
							) }
						</p>
					</PanelBody>
				</InspectorControls>

				{ /* ═══ PAGE HERO ═══ */ }
				<section className="page-hero">
					<div className="wrap">
						{ field(
							'heroEyebrow',
							'span',
							'eyebrow',
							__( 'Eyebrow…', 'catsnboard' )
						) }
						{ field(
							'heroTitle',
							'h1',
							'round',
							__( 'Page title…', 'catsnboard' )
						) }
						{ field(
							'heroLead',
							'p',
							undefined,
							__( 'Lead…', 'catsnboard' )
						) }
					</div>
				</section>

				{ /* ═══ PRICING CARDS ═══ */ }
				<section className="pricing-sec">
					<div className="wrap">
						<div className="pgrid">
							{ CARDS.map( ( card, ci ) => (
								<div
									className={
										'pcard' +
										( card.featured ? ' featured' : '' )
									}
									key={ ci }
								>
									{ field(
										card.title,
										'div',
										'ptitle',
										__( 'Plan name…', 'catsnboard' )
									) }
									<div className="pprice">
										{ field(
											card.price,
											'span',
											'cnb-price-amount',
											__( 'Price…', 'catsnboard' )
										) }
										{ ' ' }
										<small>
											{ field(
												card.unit,
												'span',
												'cnb-price-unit',
												__(
													'/ unit…',
													'catsnboard'
												)
											) }
										</small>
									</div>
									<ul className="pfeat">
										{ card.feats.map( ( fAttr, fi ) => (
											<li key={ fi }>
												{ field(
													fAttr,
													'span',
													'cnb-feat',
													__(
														'Feature…',
														'catsnboard'
													)
												) }
											</li>
										) ) }
									</ul>
									{ field(
										'bookLabel',
										'span',
										'btn',
										__( 'Button…', 'catsnboard' )
									) }
								</div>
							) ) }
						</div>
					</div>
				</section>
			</div>
		);
	},
	save: () => null,
} );
