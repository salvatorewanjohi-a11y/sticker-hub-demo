<?php
/**
 * Builds the STICKER HUB store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Product photos are read from /wordpress/sh-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized, so each one is copied straight into uploads and registered with its
// size. (media_handle_sideload opens every image, which made the demo slow to build.)
function sh_sideload( $tmp, $name, $parent, $title ) {
	$up   = wp_upload_dir();
	$file = wp_unique_filename( $up['path'], $name );
	$dest = trailingslashit( $up['path'] ) . $file;
	if ( ! @rename( $tmp, $dest ) && ! copy( $tmp, $dest ) ) {
		return new WP_Error( 'sh_copy', 'Could not copy ' . $name );
	}
	$type = wp_check_filetype( $file );
	$size = @getimagesize( $dest ) ?: [ 0, 0 ];
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type['type'], 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $file ], $dest, $parent, true, false );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	wp_update_attachment_metadata( $id, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
	return $id;
}

// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
if ( defined( 'SH_FAST' ) && SH_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'STICKER HUB',
	'blogdescription'                        => 'Who makes you known? Vinyl decals, car wraps, tints and printing from Rabai Road, Nairobi.',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'Rabai Road Estate, Outer Ring Road',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '3',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_enable_coupons'             => 'yes',
	'woocommerce_calc_discounts_sequentially' => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Service categories (menu order = order in the header and on the home page).
$cat = [];
$i   = 0;
foreach ( [
	'vinyl-decals'    => [ 'Vinyl Decals & Stickers', 'Die-cut, holographic and reflective vinyl: names, logos, portraits, sticker bombs, window patterns and product labels.' ],
	'car-wraps'       => [ 'Car Wraps & Graphics', 'Full and partial wraps, bonnet and roof wraps, windscreen banners, motorbike graphics and fleet branding.' ],
	'tints'           => [ 'Headlight & Window Tints', 'Smoked headlight and taillight film, yellow fog-light tint and chameleon window tint, fitted at our workshop.' ],
	'tshirt-printing' => [ 'T-Shirt & Apparel Printing', 'Your logo or design printed on T-shirts, from one shirt to a full team or company batch.' ],
	'large-format'    => [ 'Large Format & Digital Printing', 'Banners, posters, wall graphics, booklets and documents, printed in-house on our wide-format printer.' ],
	'tools-care'      => [ 'Tools & Care', 'Squeegees and microfiber cloths for fitting and looking after vinyl and tint.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Finish: a global attribute with its own pages (/finish/holographic/), used by the finish filter bar.
if ( ! wc_attribute_taxonomy_id_by_name( 'finish' ) ) {
	wc_create_attribute( [ 'name' => 'Finish', 'slug' => 'finish', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true ] );
}
if ( ! taxonomy_exists( 'pa_finish' ) ) {
	register_taxonomy( 'pa_finish', [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ] );
}
$finish = [];
foreach ( [
	'gloss'        => [ 'Gloss', 'High-shine vinyl in solid colours. The classic look for names, logos and stripes.' ],
	'matte'        => [ 'Matte', 'Flat, glare-free vinyl with a modern, understated look.' ],
	'reflective'   => [ 'Reflective', 'Lights up in headlights at night: safer for bikes, matatus and fleet vehicles.' ],
	'holographic'  => [ 'Holographic', 'Rainbow-shift vinyl that changes colour as the light and your angle move.' ],
	'carbon-fibre' => [ 'Carbon Fibre', 'Textured carbon-weave vinyl for bonnets, mirrors, trims and accents.' ],
	'chameleon'    => [ 'Chameleon', 'Colour-shifting film that turns from green to gold to purple with the light.' ],
	'smoked'       => [ 'Smoked Tint', 'Dark see-through film for headlights and taillights.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'pa_finish' ) ?: wp_insert_term( $name, 'pa_finish', [ 'slug' => $slug, 'description' => $desc ] );
	$finish[ $slug ] = (int) $t['term_id'];
	update_term_meta( $finish[ $slug ], 'order', count( $finish ) );
}
delete_transient( 'wc_attribute_taxonomies' );

// Delivery. PLACEHOLDER prices, to confirm with the client.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
$pickup = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$pickup}_settings", [ 'title' => 'Pick up at our Rabai Road workshop (free)', 'cost' => '0', 'tax_status' => 'none' ] );
$free = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$free}_settings", [ 'title' => 'Free Nairobi express delivery', 'requires' => 'min_amount', 'min_amount' => '3000' ] );
$rider = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$rider}_settings", [ 'title' => 'Nairobi doorstep rider (same day)', 'cost' => '300', 'tax_status' => 'none' ] );

$rest = new WC_Shipping_Zone();
$rest->set_zone_name( 'Rest of Kenya' );
$rest->add_location( 'KE', 'country' );
$rest->save();
$courier = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$courier}_settings", [ 'title' => 'Upcountry courier to your county (1–2 days)', 'cost' => '450', 'tax_status' => 'none' ] );
$rest_pickup = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$rest_pickup}_settings", [ 'title' => 'Pick up at our Rabai Road workshop (free)', 'cost' => '0', 'tax_status' => 'none' ] );

// The theme offers "Pay at the workshop" only with these pickup rates, and cash on delivery only without them.
update_option( 'sh_pickup_rates', [ "flat_rate:$pickup", "flat_rate:$rest_pickup" ] );

// Payment. M-Pesa uses the Paybill on the shop's own sign (PLACEHOLDER until the client confirms it);
// customers pay after the shop confirms the order and price. No live STK push: that needs the shop's Daraja keys.
update_option( 'woocommerce_bacs_settings', [
	'enabled'         => 'yes',
	'title'           => 'M-Pesa (Paybill 247247)',
	'description'     => 'We call to confirm your order, then you pay via M-Pesa Paybill 247247, account 0766 386 934. Send us the M-Pesa message on WhatsApp.',
	'instructions'    => 'Pay via M-Pesa Paybill 247247, account number 0766 386 934, once we have confirmed your order by phone. Then send the M-Pesa confirmation message to +254 766 386 934 on WhatsApp.',
	'account_details' => '',
] );
update_option( 'woocommerce_bacs_accounts', [] );
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Cash or M-Pesa on delivery',
	'description'        => 'Pay the rider in cash or by M-Pesa when your order arrives.',
	'instructions'       => 'We will call to confirm your order and a delivery time. Pay the rider when it arrives.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );
update_option( 'woocommerce_cheque_settings', [
	'enabled'      => 'yes',
	'title'        => 'Pay at the workshop on pickup',
	'description'  => 'Pay in cash or M-Pesa when you collect at Rabai Road Estate (Mon–Sat, 8 am – 7 pm).',
	'instructions' => 'We will call when your order is ready. Collect and pay at our workshop on Rabai Road Estate, Outer Ring Road, Mon–Sat 8 am – 7 pm.',
] );
update_option( 'woocommerce_gateway_order', [ 'bacs' => 0, 'cod' => 1, 'cheque' => 2 ] );

// Products. Photos are frames of STICKER HUB's own Instagram reels and Google listing photos; products marked
// 'illustration' have none yet and show a plain mockup. All prices are PLACEHOLDERS (Nairobi market estimates),
// to confirm with the client. Most items are made to order, so stock is not tracked except for the tools.
// 'options' => [ label => price ] is the main choice (its attribute name is 'attr'); 'finishes' with
// 'pick_finish' => true lets the customer choose the finish, with 'finish_add' as a % on top of the price.
$products = [
	[
		'slug' => 'custom-die-cut-vinyl-decal', 'name' => 'Custom Die-Cut Vinyl Decal', 'cats' => [ 'vinyl-decals' ],
		'badge' => 'Automotive Vinyl', 'life' => '3–5 yr outdoor rating',
		'short' => 'Your name, logo, slogan or portrait, designed and cut from weatherproof vinyl. Like the RATEGO BABA portrait we cut and fitted on this van.',
		'desc' => 'Send us your idea, a photo or your logo and our designers turn it into clean cut lines. We cut it on our plotter, weed it by hand and mask it with application tape, ready to fit. Pick it up and we can fit it for you at the workshop, or take it home with a squeegee and fit it yourself. Prices include simple design work; detailed portraits may need a quick chat on WhatsApp first.',
		'attr' => 'Size', 'options' => [ 'Small (up to 30 cm)' => 1200, 'Medium (up to 60 cm)' => 2500, 'Large (up to 100 cm)' => 4500 ],
		'finishes' => [ 'gloss', 'matte', 'reflective', 'carbon-fibre' ], 'pick_finish' => true, 'finish_add' => [ 'reflective' => 30, 'carbon-fibre' => 20 ],
		'specs' => [ 'Material' => 'Self-adhesive cut vinyl, outdoor grade', 'Outdoor life' => '3–5 years (gloss and matte), 5+ years reflective', 'Surfaces' => 'Glass, paintwork, metal, smooth plastic', 'Colours' => 'White, black, gold, silver and more on request', 'Design' => 'Simple layout included' ],
		'install' => [ 'Clean the surface with soapy water, then wipe with spirit or alcohol. No wax, no polish.', 'Fit in the shade, between 15 °C and 30 °C.', 'Tape the decal in place along the top edge (hinge method).', 'Peel the backing, then press from the centre outwards with a squeegee.', 'Peel the clear application tape back slowly at a sharp angle.', 'Wait 48 hours before washing; no pressure washer on the edges for a week.' ],
		'upsells' => [ 'felt-edge-squeegee', 'microfiber-cloth-pack' ],
	],
	[
		'slug' => 'holographic-sticker-bomb-pack', 'name' => 'Holographic Sticker Bomb Pack', 'cats' => [ 'vinyl-decals' ],
		'badge' => 'Holographic Vinyl', 'life' => '2–3 yr outdoor rating',
		'short' => 'A mixed pack of holographic die-cut stickers to bomb a rear window, laptop, helmet or toolbox.',
		'desc' => 'Rainbow holographic stickers that flash every colour in the sun, all individually die-cut. Cover a rear windscreen like the one in the photos, or share them across laptops, helmets and toolboxes. Want your own logo in the mix? Ask us on WhatsApp.',
		'attr' => 'Pack', 'options' => [ '25 stickers' => 800, '50 stickers' => 1500, '100 stickers' => 2800 ],
		'finishes' => [ 'holographic' ],
		'specs' => [ 'Material' => 'Holographic vinyl, individually die-cut', 'Sizes' => 'About 5–12 cm each', 'Outdoor life' => '2–3 years', 'Surfaces' => 'Glass, paint, laptops, helmets, hard plastic' ],
		'install' => [ 'Clean and dry the surface first.', 'Lay the stickers out before peeling to plan the bomb.', 'Press each one down from the middle outwards; overlap edges by a few millimetres.', 'Leave 48 hours before washing.' ],
		'upsells' => [ 'felt-edge-squeegee' ],
	],
	[
		'slug' => 'geometric-window-pattern-decal', 'name' => 'Geometric Window Pattern Decal', 'cats' => [ 'vinyl-decals' ],
		'badge' => 'Window Graphics', 'life' => '3–5 yr outdoor rating',
		'short' => 'A full-coverage tribal geometric pattern cut for your rear windows. You can still see out from inside.',
		'desc' => 'A bold geometric pattern cut to the exact shape of your windows, as on the van in the photos. It gives the car a custom look and some privacy, and you can still see out from the driver\'s seat. We measure and fit at the workshop.',
		'attr' => 'Coverage', 'options' => [ 'One window' => 2500, 'Rear windscreen' => 3500, 'Two rear side windows' => 4500 ],
		'finishes' => [ 'gloss', 'matte' ], 'pick_finish' => true,
		'specs' => [ 'Material' => 'Cut vinyl, outdoor grade', 'Fit' => 'Cut to your window shape at the workshop', 'Visibility' => 'See-through gaps keep the view from inside', 'Outdoor life' => '3–5 years' ],
		'install' => [ 'Best fitted by us: the pattern is cut to your exact windows.', 'Glass is cleaned with alcohol before fitting.', 'Don\'t wind fitted door windows down for 3 days.', 'Use a soft cloth on the inside of the glass, no scrapers.' ],
		'upsells' => [ 'microfiber-cloth-pack' ],
	],
	[
		'slug' => 'side-window-sticker-set', 'name' => 'Side Window Sticker Set', 'cats' => [ 'vinyl-decals' ],
		'badge' => 'Automotive Vinyl', 'life' => '2–3 yr outdoor rating',
		'short' => 'A ready-made set of car-culture stickers for a quarter window, like the "STANCE" set in the photo.',
		'desc' => 'A themed set of printed and cut stickers made to fill a small side or quarter window. We can swap in your car club name, plate or Instagram handle on request.',
		'price' => 1000, 'finishes' => [ 'gloss' ],
		'specs' => [ 'Material' => 'Printed vinyl, laminated, plus cut vinyl lettering', 'Pieces' => 'About 10–14 per set', 'Outdoor life' => '2–3 years' ],
		'install' => [ 'Clean the glass with alcohol.', 'Fit from the bottom of the window up.', 'Press the edges down well; wait 48 hours before washing.' ],
		'upsells' => [ 'felt-edge-squeegee' ],
	],
	[
		'slug' => 'custom-holographic-logo-stickers', 'name' => 'Custom Holographic Logo Stickers', 'cats' => [ 'vinyl-decals' ],
		'badge' => 'Brand Stickers', 'life' => '2–3 yr outdoor rating',
		'short' => 'Your logo printed and die-cut on holographic vinyl. Great for packaging, giveaways and merch.',
		'desc' => 'Make your brand shine: we print your logo on holographic vinyl and die-cut it to shape, like the panda stickers in the photos. Send your logo (a PNG, PDF or AI file is best) and we send a proof on WhatsApp before printing.',
		'attr' => 'Quantity', 'options' => [ '50 stickers' => 1500, '100 stickers' => 2500, '250 stickers' => 5000 ],
		'finishes' => [ 'holographic' ],
		'specs' => [ 'Material' => 'Printed holographic vinyl, die-cut', 'Size' => 'Up to 7 × 7 cm each', 'Proof' => 'Sent on WhatsApp before printing', 'Turnaround' => 'About 2–3 working days after proof approval' ],
		'install' => [ 'Peel and stick on clean, dry, smooth surfaces.', 'For packaging, apply before filling boxes or bags.' ],
		'upsells' => [ 'product-labels-thank-you-cards' ],
	],
	[
		'slug' => 'product-labels-thank-you-cards', 'name' => 'Product Labels & Thank-You Cards', 'cats' => [ 'vinyl-decals', 'large-format' ],
		'badge' => 'Business Printing', 'life' => 'Indoor & packaging use',
		'short' => 'Branded product labels and thank-you cards for food, cosmetics and online shops.',
		'desc' => 'Labels and thank-you cards with your logo, contacts and QR code, printed in full colour, like the kitchen and bakery labels we printed in these photos. We can design them from scratch or print your artwork.',
		'attr' => 'Quantity', 'options' => [ '100 pieces' => 1000, '250 pieces' => 2000, '500 pieces' => 3500 ],
		'finishes' => [ 'gloss', 'matte' ], 'pick_finish' => true,
		'specs' => [ 'Material' => 'Printed sticker paper or vinyl, cut to shape', 'Design' => 'Simple label design included', 'Proof' => 'Sent on WhatsApp before printing', 'Turnaround' => 'About 1–3 working days' ],
		'install' => [ 'Store labels flat and away from heat.', 'Stick onto clean, dry packaging.' ],
		'upsells' => [ 'custom-holographic-logo-stickers' ],
	],
	[
		'slug' => 'holographic-bonnet-wrap', 'name' => 'Holographic Bonnet Wrap', 'cats' => [ 'car-wraps' ],
		'badge' => 'Partial Wrap', 'life' => '3–5 yr outdoor rating',
		'short' => 'A holographic diamond-pattern partial wrap for your bonnet or roof, fitted at our workshop.',
		'desc' => 'A head-turning partial wrap that flashes rainbow diamonds in the sun, as fitted on this Suzuki Swift. Your paint stays protected underneath, and the wrap can be peeled off cleanly later. Book a fitting slot on WhatsApp after you order.',
		'attr' => 'Coverage', 'options' => [ 'Bonnet' => 8500, 'Bonnet + roof' => 17000 ],
		'finishes' => [ 'holographic' ],
		'specs' => [ 'Material' => 'Holographic wrap vinyl with air-release glue', 'Fitting' => 'At our Rabai Road workshop, about 2–4 hours', 'Paint' => 'Protects the paint; removable later', 'Outdoor life' => '3–5 years' ],
		'install' => [ 'Fitted by us: bring the car washed if you can.', 'No car wash for 5 days after fitting.', 'Hand-wash only; keep pressure washers away from the edges.', 'Wax-free cleaners only.' ],
		'upsells' => [ 'microfiber-cloth-pack' ],
	],
	[
		'slug' => 'windshield-name-banner', 'name' => 'Windscreen Name Banner', 'cats' => [ 'car-wraps', 'vinyl-decals' ],
		'badge' => 'Automotive Vinyl', 'life' => '3–5 yr outdoor rating',
		'short' => 'Your name, crew or business in big letters across the top of the windscreen. The BONDO007 van is ours.',
		'desc' => 'The classic matatu and van banner: a name or slogan in bold letters across the top strip of the windscreen, designed in your style and cut to fit. We fit it at the workshop in about 30 minutes.',
		'attr' => 'Vehicle', 'options' => [ 'Saloon / hatchback' => 2000, 'SUV / van / matatu' => 3000 ],
		'finishes' => [ 'gloss', 'reflective', 'holographic' ], 'pick_finish' => true, 'finish_add' => [ 'reflective' => 30, 'holographic' => 30 ],
		'specs' => [ 'Material' => 'Cut vinyl, outdoor grade', 'Position' => 'Top strip of the windscreen, above the driver\'s view', 'Fitting' => 'About 30 minutes at the workshop', 'Outdoor life' => '3–5 years' ],
		'install' => [ 'Fitted on the outside of the glass, above the wiper sweep.', 'Leave the wipers off the banner for 48 hours.' ],
		'upsells' => [ 'felt-edge-squeegee' ],
	],
	[
		'slug' => 'motorbike-top-box-decals', 'name' => 'Motorbike Top Box & Pannier Decals', 'cats' => [ 'car-wraps', 'vinyl-decals' ],
		'badge' => 'Bike Graphics', 'life' => '3–5 yr outdoor rating',
		'short' => 'Mountain, name and logo graphics cut for your top box and panniers, like this adventure-bike set.',
		'desc' => 'Make your touring boxes yours: mountains, your bike\'s name, your route or your handle, cut in vinyl and fitted to the shape of your boxes.',
		'attr' => 'Set', 'options' => [ 'Top box only' => 1500, 'Top box + 2 panniers' => 3500 ],
		'finishes' => [ 'gloss', 'reflective' ], 'pick_finish' => true, 'finish_add' => [ 'reflective' => 30 ],
		'specs' => [ 'Material' => 'Cut vinyl, outdoor grade', 'Surfaces' => 'Aluminium and plastic boxes', 'Outdoor life' => '3–5 years' ],
		'install' => [ 'Clean the box with alcohol first.', 'Fit on a warm (not hot) day and press the edges well.', 'Wait 48 hours before riding in rain.' ],
		'upsells' => [ 'felt-edge-squeegee' ],
	],
	[
		'slug' => 'fleet-tuk-tuk-branding', 'name' => 'Fleet & Tuk-Tuk Branding', 'cats' => [ 'car-wraps' ],
		'badge' => 'Fleet Branding', 'life' => '3–5 yr outdoor rating',
		'short' => 'Branding for tuk-tuks, taxis, vans and company cars, priced per vehicle. Like the SHALLO tuk-tuk fleet.',
		'desc' => 'Put your business on the road. We design the layout, print or cut the graphics and brand every vehicle the same way, as we did for this tuk-tuk fleet. Branding five or more vehicles? Ask on WhatsApp for a fleet price.',
		'attr' => 'Vehicle', 'options' => [ 'Tuk-tuk (front + sides)' => 5500, 'Saloon (doors + rear)' => 9000, 'Van / matatu (sides + rear)' => 15000 ],
		'finishes' => [ 'gloss', 'reflective' ], 'pick_finish' => true, 'finish_add' => [ 'reflective' => 25 ],
		'specs' => [ 'Material' => 'Printed and laminated vinyl, or cut vinyl', 'Design' => 'Layout and proof included', 'Fitting' => 'At the workshop or on site in Nairobi', 'Outdoor life' => '3–5 years' ],
		'install' => [ 'Vehicles should be washed and dry before fitting.', 'No car wash for 5 days after fitting.' ],
		'upsells' => [ 'microfiber-cloth-pack' ],
	],
	[
		'slug' => 'chameleon-windshield-tint', 'name' => 'Chameleon Window Tint', 'cats' => [ 'tints' ],
		'badge' => 'Tint Film', 'life' => '3–5 yr rating',
		'short' => 'Colour-shifting tint that turns green, gold and purple with the light, fitted at our workshop.',
		'desc' => 'The chameleon film on the BONDO007 van: it shifts colour as you move around the car. Fitted by us on clean glass at the workshop. Please check the NTSA rules on how dark your front windows and windscreen may be before you choose.',
		'attr' => 'Glass', 'options' => [ 'Front door windows (pair)' => 6000, 'Windscreen' => 9500 ],
		'finishes' => [ 'chameleon' ],
		'specs' => [ 'Material' => 'Chameleon tint film', 'Fitting' => 'At our Rabai Road workshop', 'Rules' => 'Check NTSA limits for front glass before fitting' ],
		'install' => [ 'Fitted by us.', 'Don\'t wind fitted windows down for 3 days.', 'Clean with a soft cloth and ammonia-free cleaner.' ],
		'upsells' => [ 'microfiber-cloth-pack' ],
	],
	[
		'slug' => 'headlight-taillight-tint', 'name' => 'Headlight & Taillight Smoke Tint', 'cats' => [ 'tints' ],
		'badge' => 'Tint Film', 'life' => '2–3 yr rating', 'illustration' => true,
		'short' => 'Smoked or yellow film for headlights, fog lights and taillights, cut to your lamps and fitted.',
		'desc' => 'Give the front and back of your car a darker, meaner look, or warm yellow fog lights. We cut the film to your exact lamps and fit it at the workshop. Light smoke keeps your lights bright; dark smoke is for show and taillights.',
		'attr' => 'Lights', 'options' => [ 'Headlights (pair)' => 3500, 'Taillights (pair)' => 3000, 'Headlights + taillights' => 6000 ],
		'attr2' => 'Shade', 'options2' => [ 'Light smoke', 'Dark smoke', 'Yellow' ],
		'finishes' => [ 'smoked' ],
		'specs' => [ 'Material' => 'Self-healing lamp tint film', 'Fitting' => 'About 1 hour at the workshop', 'Road use' => 'Light smoke or yellow recommended on headlights' ],
		'install' => [ 'Fitted by us on clean lamps.', 'No car wash for 3 days.', 'Polish lamps with a soft microfiber cloth only.' ],
		'upsells' => [ 'microfiber-cloth-pack' ],
	],
	[
		'slug' => 'custom-printed-t-shirt', 'name' => 'Custom Printed T-Shirt', 'cats' => [ 'tshirt-printing' ],
		'badge' => 'Custom Apparel', 'life' => 'Wash-tested print', 'illustration' => true,
		'short' => 'Your logo, name or design printed on a quality cotton T-shirt. One shirt or a full team batch.',
		'desc' => 'Printed T-shirts for events, teams, companies and merch. Send your design on WhatsApp and we send a mockup before printing. For 10 or more shirts, use the cost estimator for batch pricing.',
		'attr' => 'Print', 'options' => [ 'Front only' => 1200, 'Front + back' => 1700 ],
		'attr2' => 'Colour', 'options2' => [ 'Black', 'White' ],
		'specs' => [ 'Shirt' => 'Cotton crew-neck, sizes S–XXL', 'Print' => 'Full colour', 'Proof' => 'Mockup on WhatsApp before printing', 'Care' => 'Wash inside out, cool, no tumble dry' ],
		'install' => [ 'Wash inside out in cool water.', 'Don\'t iron directly on the print.' ],
		'upsells' => [ 'custom-holographic-logo-stickers' ],
	],
	[
		'slug' => 'large-format-banner-print', 'name' => 'Large Format Banner & Poster Print', 'cats' => [ 'large-format' ],
		'badge' => 'Large Format', 'life' => 'Indoor & outdoor',
		'short' => 'Banners, posters and wall graphics printed in-house on our wide-format printer.',
		'desc' => 'Shop banners, event backdrops, posters and wall stickers, printed in sharp, bold colour on our own wide-format printer (that\'s it in the photo). Need a different size? Use the estimator or WhatsApp us.',
		'attr' => 'Size', 'options' => [ 'A1 (59 × 84 cm)' => 1200, 'A0 (84 × 119 cm)' => 2200, '1 × 2 m banner' => 4500 ],
		'attr2' => 'Material', 'options2' => [ 'PVC banner', 'Self-adhesive vinyl' ],
		'specs' => [ 'Printer' => 'In-house wide-format printer', 'Materials' => 'PVC banner (with eyelets on request) or self-adhesive vinyl', 'Design' => 'Print-ready files or design on request', 'Turnaround' => 'Usually 1–2 working days' ],
		'install' => [ 'Banners: hang with eyelets or cable ties.', 'Vinyl: apply to clean, smooth, dry walls or boards.' ],
		'upsells' => [ 'felt-edge-squeegee' ],
	],
	[
		'slug' => 'booklet-document-printing', 'name' => 'Booklet & Document Printing', 'cats' => [ 'large-format' ],
		'badge' => 'Digital Printing', 'life' => 'Bound copies',
		'short' => 'Manuals, SOPs, reports and company profiles: printed, covered and bound.',
		'desc' => 'Digital printing for offices and companies, like these standard operating procedure manuals: A4 pages, a colour cover and binding. Prices are for up to 40 pages per copy; send your PDF on WhatsApp for longer documents.',
		'attr' => 'Copies', 'options' => [ '10 copies' => 3500, '25 copies' => 8000, '50 copies' => 15000 ],
		'specs' => [ 'Size' => 'A4', 'Pages' => 'Up to 40 per copy at these prices', 'Cover' => 'Full colour', 'Turnaround' => 'Usually 1–3 working days' ],
		'install' => [ 'Send a PDF so the layout stays exactly as you made it.' ],
		'upsells' => [ 'product-labels-thank-you-cards' ],
	],
	[
		'slug' => 'felt-edge-squeegee', 'name' => 'Felt-Edge Squeegee Applicator', 'cats' => [ 'tools-care' ],
		'badge' => 'Tool', 'life' => 'Reusable', 'illustration' => true, 'stock' => 25,
		'short' => 'The tool we fit with: a firm squeegee with a soft felt edge that won\'t scratch vinyl.',
		'desc' => 'Press decals down without bubbles or scratches. The felt edge glides over vinyl and tint film.',
		'price' => 350,
		'specs' => [ 'Size' => 'About 10 cm wide', 'Edge' => 'Felt, scratch-free' ],
		'install' => [ 'Hold at about 45° and push from the centre outwards with overlapping strokes.' ],
	],
	[
		'slug' => 'microfiber-cloth-pack', 'name' => 'Microfiber Cloth (Pack of 2)', 'cats' => [ 'tools-care' ],
		'badge' => 'Care', 'life' => 'Washable', 'illustration' => true, 'stock' => 25,
		'short' => 'Soft, lint-free cloths for cleaning wraps, tint and lamps without scratches.',
		'desc' => 'The safe way to clean vinyl, wraps, tinted glass and smoked lamps: soft, lint-free and machine washable.',
		'price' => 400,
		'specs' => [ 'Pieces' => '2 cloths', 'Size' => 'About 40 × 40 cm' ],
		'install' => [ 'Use with plain water or a wax-free cleaner.' ],
	],
];

function sh_attach_images( $pid, $slug, $name ) {
	$num   = fn( $f ) => preg_match( '#^' . preg_quote( $slug, '#' ) . '(?:-(\d+))?\.webp$#', basename( $f ), $m ) ? (int) ( $m[1] ?? 1 ) : 0;
	$files = array_values( array_filter( glob( "/wordpress/sh-images/$slug*.webp" ) ?: [], fn( $f ) => $num( $f ) > 0 ) );
	usort( $files, fn( $a, $b ) => $num( $a ) <=> $num( $b ) );
	$ids = [];
	foreach ( $files as $file ) {
		$base = basename( $file );
		$tmp  = wp_tempnam( $base );
		copy( $file, $tmp );
		$n   = $num( $file );
		$att = sh_sideload( $tmp, $base, $pid, $name . ( $n > 1 ? ' – photo ' . $n : '' ) );
		if ( ! is_wp_error( $att ) ) $ids[ $n ] = $att;
	}
	return $ids; // photo number => attachment id
}

function sh_local_attr( $name, array $options, $position, $variation = true ) {
	$a = new WC_Product_Attribute();
	$a->set_name( $name );
	$a->set_options( $options );
	$a->set_position( $position );
	$a->set_visible( true );
	$a->set_variation( $variation );
	return $a;
}

$ids = [];
foreach ( $products as $order => $d ) {
	$finishes = $d['finishes'] ?? [];
	$pick     = ! empty( $d['pick_finish'] ) && count( $finishes ) > 1;
	$variable = ! empty( $d['options'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['short'] );
	$p->set_category_ids( array_map( fn( $c ) => $cat[ $c ], $d['cats'] ) );
	$p->set_featured( true );
	$p->update_meta_data( '_sh_badge', $d['badge'] );
	$p->update_meta_data( '_sh_life', $d['life'] );
	$p->update_meta_data( '_sh_specs', implode( "\n", array_map( fn( $k, $v ) => "$k: $v", array_keys( $d['specs'] ), $d['specs'] ) ) );
	$p->update_meta_data( '_sh_install', implode( "\n", $d['install'] ) );
	if ( ! empty( $d['illustration'] ) ) $p->update_meta_data( '_sh_illustration', 'yes' );

	$attrs = [];
	if ( $variable ) {
		$attrs[] = sh_local_attr( $d['attr'], array_keys( $d['options'] ), 0 );
		if ( ! empty( $d['options2'] ) ) $attrs[] = sh_local_attr( $d['attr2'], $d['options2'], 1 );
	}
	if ( $finishes ) {
		$a = new WC_Product_Attribute();
		$a->set_id( wc_attribute_taxonomy_id_by_name( 'finish' ) );
		$a->set_name( 'pa_finish' );
		$a->set_options( array_map( fn( $f ) => $finish[ $f ], $finishes ) );
		$a->set_position( 2 );
		$a->set_visible( true );
		$a->set_variation( $pick );
		$attrs[] = $a;
	}
	$p->set_attributes( $attrs );
	if ( $variable ) {
		$defaults = [ sanitize_title( $d['attr'] ) => array_key_first( $d['options'] ) ];
		if ( ! empty( $d['options2'] ) ) $defaults[ sanitize_title( $d['attr2'] ) ] = $d['options2'][0];
		if ( $pick ) $defaults['pa_finish'] = $finishes[0];
		$p->set_default_attributes( $defaults );
	} else {
		$p->set_regular_price( $d['price'] );
	}
	if ( ! empty( $d['stock'] ) ) {
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $d['stock'] );
	}
	$pid = $p->save();
	$ids[ $d['slug'] ] = $pid;

	$imgs = sh_attach_images( $pid, $d['slug'], $d['name'] );
	if ( $imgs ) {
		// Written as meta: a second full product save here slowed the import.
		set_post_thumbnail( $pid, $imgs[1] ?? reset( $imgs ) );
		update_post_meta( $pid, '_product_image_gallery', implode( ',', array_values( array_diff_key( $imgs, [ 1 => true ] ) ) ) );
	}

	if ( $variable ) {
		// One variation per combination: main option × second option × finish (when the customer picks it).
		$second = $d['options2'] ?? [ null ];
		$fins   = $pick ? $finishes : [ null ];
		$key    = sanitize_title( $d['attr'] );
		$key2   = ! empty( $d['attr2'] ) ? sanitize_title( $d['attr2'] ) : null;
		$n      = 0;
		foreach ( $d['options'] as $label => $price ) {
			foreach ( $second as $o2 ) {
				foreach ( $fins as $f ) {
					$va = [ $key => $label ];
					if ( $o2 !== null ) $va[ $key2 ] = $o2;
					if ( $f !== null ) $va['pa_finish'] = $f;
					$add = $f !== null ? ( $d['finish_add'][ $f ] ?? 0 ) : 0;
					if ( $o2 === 'Self-adhesive vinyl' ) $add += 20;
					$v = new WC_Product_Variation();
					$v->set_parent_id( $pid );
					$v->set_attributes( $va );
					$v->set_regular_price( (string) ( round( $price * ( 1 + $add / 100 ) / 50 ) * 50 ) );
					$v->set_menu_order( $n++ );
					$v->save();
				}
			}
		}
		WC_Product_Variable::sync( $pid );
	}
}

// "Frequently paired together" on each product page.
foreach ( $products as $d ) {
	if ( ! empty( $d['upsells'] ) ) {
		update_post_meta( $ids[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $ids[ $s ] ?? 0, $d['upsells'] ) ) ) );
	}
}

// PLACEHOLDER promo code for the cart's promo field, to confirm or remove with the client.
if ( ! wc_get_coupon_id_by_code( 'KARIBU10' ) ) {
	$c = new WC_Coupon();
	$c->set_code( 'KARIBU10' );
	$c->set_description( 'Demo: 10% off a first order. Confirm or delete with the client.' );
	$c->set_discount_type( 'percent' );
	$c->set_amount( 10 );
	$c->set_individual_use( true );
	$c->set_usage_limit_per_user( 1 );
	$c->save();
}

// Pages.
function sh_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
sh_page( 'wishlist', 'Your wishlist', "<!-- wp:shortcode -->\n[sh_wishlist]\n<!-- /wp:shortcode -->" );
sh_page( 'estimator', 'Custom decal & wrap cost estimator', "<!-- wp:paragraph -->\n<p>Pick your project, enter the size or quantity and choose a finish to get an instant estimate. Send it to us on WhatsApp and we confirm the final price after seeing your design.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[sh_estimator]\n<!-- /wp:shortcode -->" );
$shop_page = (int) wc_get_page_id( 'shop' );
if ( $shop_page > 0 ) wp_update_post( [ 'ID' => $shop_page, 'post_title' => 'Decals, wraps & prints' ] );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
