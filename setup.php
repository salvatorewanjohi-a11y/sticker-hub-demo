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
	'blogdescription'                        => 'Who makes you known? Stickers, tints, signs, vinyl and printing from Rabai Road, Nairobi.',
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

// Categories: the shop's own list and order (Asaph's price list, WhatsApp, 20 October 2026). Menu order = order
// of the tiles on the home page. Each gets its tile picture (images/cat-<slug>.webp) as the category image.
$cat = [];
$i   = 0;
foreach ( [
	'large-format'     => [ 'Large Format Printing', 'Banners, one way vision, clear, frost, satin, hologram and light box prints, printed in-house and priced per square metre.' ],
	'ceramic-tints'    => [ 'Nano Ceramic Tints', 'Black presidential and chameleon nano ceramic window tint for saloons, SUVs and vans, fitted at our workshop.' ],
	'stickers-decals'  => [ 'Stickers, Decals & Product Labels', 'Printed stickers and product labels by the square metre, custom names and decals, quarter panel window stickers, UV and dome stickers.' ],
	'show-plates'      => [ 'Custom Show Plates', 'Custom show plates for cars and bikes, and USA-style plates with any text you like.' ],
	'signs-cnc'        => [ 'Signs & CNC Services', 'Signs made to your size on cellular board, forever board, perspex and aluminium composite panel, and CNC cutting.' ],
	'vinyl-wraps-ppf'  => [ 'Vinyl, Wraps & PPF Sales', 'Vinyl by the metre (normal colours, chrome, hologram, glitter, luminous, 3D carbon fibre, AK47), full wrap rolls and PPF.' ],
	'sound-deadening'  => [ 'Sound Deadening Material', 'Sound deadening material for car doors, floors and boots.' ],
	'vehicle-branding' => [ 'Shop & Vehicle Branding', 'Branding for shops, offices and walls, and for fleets, tuk-tuks, cars and bikes: designed, printed and fitted by us.' ],
	'digital-printing' => [ 'Digital Printing', 'Garage service tags, flyers, posters, sticker paper, booklets, business cards, funeral programmes and certificates.' ],
	'headlight-tints'  => [ 'Headlight Tinting', 'Front and back light tint for saloons, SUVs and vans, cut and fitted at our workshop.' ],
	'screen-printing'  => [ 'Screen Printing', 'T-shirts and bags, screen printed in full colour, one colour, half tone or corporate style.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
	$img = "/wordpress/sh-images/cat-$slug.webp";
	if ( file_exists( $img ) ) {
		$tmp = wp_tempnam( "cat-$slug.webp" );
		copy( $img, $tmp );
		$att = sh_sideload( $tmp, "cat-$slug.webp", 0, $name );
		if ( ! is_wp_error( $att ) ) update_term_meta( $cat[ $slug ], 'thumbnail_id', $att );
	}
}

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

// Products. Prices are the shop's own (Asaph's price list, WhatsApp, 20 October 2026). Where the list gives no
// price the product has none: it shows "Price on request" and an "Ask for the price" WhatsApp button.
// Photos are frames of STICKER HUB's own Instagram reels and Google listing photos; products marked
// 'illustration' have none yet and show a plain drawing.
//
// 'price' => a simple product's price. 'options' => [ label => price ] is the main choice ('attr' names it);
// [ label => [ label2 => price ] ] prices every pair of the main and second choice ('attr2' names the second).
// 'attr2' / 'options2' with 'add2' => [ label2 => % ] adds a percentage instead; 'attr3' / 'options3' is an extra
// choice that doesn't change the price. 'unit' follows the price ("per m²"), 'from' => true puts "From" before it.
// Made to order, so stock isn't tracked.
$on_request = 'Tell us what you need on WhatsApp or call 0766 386 934 and we send the price.';
$products = [
	// Large format printing
	[
		'slug' => 'large-format-printing', 'name' => 'Large Format Printing', 'cats' => [ 'large-format' ],
		'badge' => 'Per square metre', 'unit' => 'per m²', 'featured' => true,
		'short' => 'Banners, window graphics and backlit prints, printed in-house on our wide-format printer. Priced per square metre (1 m × 1 m).',
		'desc' => 'Choose the material, then set the quantity to the number of square metres you need: a banner 2 m wide and 1 m high is 2. Not sure about the size? Use the price calculator, or send us a photo of the space on WhatsApp and we work it out for you.',
		'attr' => 'Material', 'options' => [ 'White' => 350, 'Black back banner' => 350, 'Light box' => 400, 'One way vision' => 600, 'Clear' => 600, 'Frost' => 600, 'Satin' => 800, 'Hologram' => 1200 ],
		'specs' => [ 'Price' => 'Per square metre (1 m × 1 m)', 'Materials' => 'White, black back banner, light box, one way vision, clear, frost, satin, hologram', 'One way vision' => 'Printed outside, see-through from inside: for shop and car windows', 'Design' => 'Send your file, or we design it for you' ],
		'install' => [ 'Pick the material and enter the square metres as the quantity.', 'Send your design (or your idea) on WhatsApp after you order.', 'We send a proof, print it and call you when it is ready.' ],
	],
	// Nano ceramic tints
	[
		'slug' => 'presidential-ceramic-tint', 'name' => 'Black Presidential Nano Ceramic Tint', 'cats' => [ 'ceramic-tints' ],
		'badge' => 'Nano ceramic tint', 'illustration' => true, 'featured' => true,
		'short' => 'Nano ceramic window tint that keeps your car cooler and cuts glare. Choose your vehicle, the windows and the shade: 5% is very dark, 45% is very light.',
		'desc' => 'Fitted by us at the workshop on Rabai Road Estate. "Whole car" is the windscreen and all the other windows together. Before choosing a dark shade for the windscreen or front windows, please check the NTSA rules for your vehicle.',
		'attr' => 'Vehicle', 'attr2' => 'Windows', 'options' => [
			'Saloon' => [ 'Front windscreen' => 3500, 'All other windows' => 4000, 'Whole car' => 7500 ],
			'SUV'    => [ 'Front windscreen' => 5500, 'All other windows' => 7000, 'Whole car' => 12500 ],
			'Van'    => [ 'Front windscreen' => 4000, 'All other windows' => 6500, 'Whole car' => 10500 ],
		],
		'attr3' => 'Shade', 'options3' => [ '5% (very dark)', '15%', '35%', '45% (very light)' ],
		'specs' => [ 'Film' => 'Black presidential nano ceramic tint', 'Shades' => '5% (very dark), 15%, 35%, 45% (very light)', 'Vehicles' => 'Saloons, SUVs and vans', 'Fitting' => 'At our Rabai Road workshop' ],
		'install' => [ 'Order, then WhatsApp or call us to book a fitting time.', 'Bring the car to Rabai Road Estate; we clean the glass and fit the film.', 'Don\'t wind the windows down for 3 days.', 'Clean with a soft cloth only.' ],
		'upsells' => [ 'headlight-taillight-tint' ],
	],
	[
		'slug' => 'chameleon-ceramic-tint', 'name' => 'Chameleon Nano Ceramic Tint', 'cats' => [ 'ceramic-tints' ],
		'badge' => 'Nano ceramic tint',
		'short' => 'Colour-shifting tint in Purple Pink, Sunset Red, Blue Purple or Green Yellow, like the one on this van. Call or WhatsApp us for the price for your car.',
		'desc' => 'Chameleon tint changes colour as the light and your angle change. Ask us which windows it can go on for your vehicle and the price. ' . $on_request,
		'specs' => [ 'Colours' => 'Purple Pink, Sunset Red, Blue Purple, Green Yellow', 'Price' => 'On request: call or WhatsApp us', 'Fitting' => 'At our Rabai Road workshop' ],
		'install' => [ 'Tell us your car and the colour you like on WhatsApp.', 'We send the price and book a fitting time.' ],
		'upsells' => [ 'presidential-ceramic-tint' ],
	],
	// Stickers, decals & product labels
	[
		'slug' => 'printed-stickers-labels', 'name' => 'Printed Stickers & Product Labels', 'cats' => [ 'stickers-decals' ],
		'badge' => 'Per square metre', 'unit' => 'per m²', 'featured' => true,
		'short' => 'Your stickers or product labels printed and cut, priced per square metre. One square metre is about 100 stickers.',
		'desc' => 'Labels for food, cosmetics and online shops, logo stickers and thank-you stickers, like the ones in the photos. White is the standard sticker; special colours cost more. Send your design on WhatsApp, or we design it for you.',
		'attr' => 'Colour', 'options' => [ 'White' => 1500, 'Special colours' => 4500 ],
		'specs' => [ 'Price' => 'Per square metre (1 m × 1 m)', 'About' => '100 stickers per square metre', 'Proof' => 'Sent on WhatsApp before printing' ],
		'install' => [ 'Choose white or special colours and enter the square metres as the quantity.', 'Send your design on WhatsApp; we send a proof before printing.' ],
	],
	[
		'slug' => 'special-decals', 'name' => 'Special Decals', 'cats' => [ 'stickers-decals' ],
		'badge' => 'Per piece', 'unit' => 'per piece',
		'short' => 'Special decals for cars, bikes, helmets and laptops at KSh 300 a piece. Send us the design you want on WhatsApp.',
		'desc' => 'Pick a design from our work or send your own, then set the quantity to the number of decals you want.',
		'price' => 300,
		'specs' => [ 'Price' => 'KSh 300 per piece', 'Surfaces' => 'Cars, bikes, helmets, laptops, phones' ],
		'install' => [ 'Clean and dry the surface first.', 'Press the decal down from the middle outwards.', 'Wait 48 hours before washing.' ],
	],
	[
		'slug' => 'custom-name-decals', 'name' => 'Custom Names & Decals', 'cats' => [ 'stickers-decals' ],
		'badge' => 'Custom vinyl', 'from' => true, 'featured' => true,
		'short' => 'Your name, slogan, logo or portrait cut in vinyl for your car, van, matatu or bike. From KSh 1,500; bigger designs cost more.',
		'desc' => 'Send us your idea, a photo or your logo. We design it, cut it and can fit it for you at the workshop, like the RATEGO BABA portrait and the windscreen name banners in the photos. The final price depends on the size: we confirm it on WhatsApp before we start.',
		'price' => 1500,
		'specs' => [ 'Price' => 'From KSh 1,500, depending on size', 'Material' => 'Outdoor vinyl', 'Fitting' => 'At our Rabai Road workshop, or take it home' ],
		'install' => [ 'Clean the surface with soapy water, then wipe with spirit. No wax.', 'Tape the decal in place along the top edge.', 'Peel the backing and press from the centre outwards.', 'Peel the clear tape back slowly; wait 48 hours before washing.' ],
		'upsells' => [ 'quarter-panel-window-stickers' ],
	],
	[
		'slug' => 'quarter-panel-window-stickers', 'name' => 'Quarter Panel Window Stickers', 'cats' => [ 'stickers-decals' ],
		'badge' => 'Window stickers',
		'short' => 'Sticker designs for the small quarter panel window, from KSh 1,500 to KSh 2,500 depending on the size.',
		'desc' => 'A set of stickers made to fill your car\'s small side window, like the STANCE set in the photo. We can put in your car club, plate or Instagram name.',
		'attr' => 'Size', 'options' => [ 'Small' => 1500, 'Medium' => 2000, 'Large' => 2500 ],
		'specs' => [ 'Price' => 'KSh 1,500 – 2,500, by size', 'Fitting' => 'At the workshop, or fit it yourself' ],
		'install' => [ 'Clean the glass with spirit.', 'Fit from the bottom of the window up.', 'Wait 48 hours before washing.' ],
	],
	[
		'slug' => 'uv-stickers', 'name' => 'UV Stickers', 'cats' => [ 'stickers-decals' ],
		'badge' => 'UV printed', 'illustration' => true,
		'short' => 'UV printed stickers with bright, raised colours. KSh 3,500.',
		'desc' => 'Send us your design or logo on WhatsApp and tell us the size and quantity you need; we confirm everything before printing.',
		'price' => 3500,
		'specs' => [ 'Price' => 'KSh 3,500', 'Proof' => 'Sent on WhatsApp before printing' ],
		'install' => [ 'Send your design on WhatsApp after you order.', 'Approve the proof; we print and call you when ready.' ],
	],
	[
		'slug' => 'dome-stickers', 'name' => 'Dome Stickers (3D Resin)', 'cats' => [ 'stickers-decals' ],
		'badge' => '3D dome stickers', 'illustration' => true,
		'short' => 'Your logo printed and topped with a clear, raised resin dome: glossy, 3D and tough. Ask us for the price.',
		'desc' => 'Dome stickers are printed stickers coated with clear resin that sets into a raised, glossy dome. Great for cars, bikes, helmets, products and giveaways. ' . $on_request,
		'specs' => [ 'Look' => 'Raised 3D gloss', 'Shapes' => 'Round, square, oval or cut to your logo', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your logo, the size and how many you need on WhatsApp.', 'We send the price and a proof before printing.' ],
	],
	// Custom show plates
	[
		'slug' => 'car-show-plate', 'name' => 'Custom Car Show Plate', 'cats' => [ 'show-plates' ],
		'badge' => 'Show plate', 'illustration' => true, 'featured' => true,
		'short' => 'A custom show plate for your car with the text you want. KSh 1,500 for the front or the back.',
		'desc' => 'Tell us the text and style on WhatsApp after you order. Show plates are for car shows, display and private grounds: on public roads your car still needs its official number plates.',
		'attr' => 'Plate', 'options' => [ 'Front' => 1500, 'Back' => 1500 ],
		'specs' => [ 'Price' => 'KSh 1,500 each (front or back)', 'Text' => 'Your choice', 'Use' => 'Shows and display, not instead of official plates' ],
		'install' => [ 'Order, then send us the text you want on WhatsApp.', 'We send a preview before making it.' ],
		'upsells' => [ 'usa-custom-plate' ],
	],
	[
		'slug' => 'bike-show-plate', 'name' => 'Custom Bike Show Plate', 'cats' => [ 'show-plates' ],
		'badge' => 'Show plate', 'illustration' => true,
		'short' => 'A custom show plate for your motorbike in yellow or white. KSh 1,000.',
		'desc' => 'Tell us the text you want on WhatsApp after you order. Show plates are for shows and display: on public roads your bike still needs its official number plate.',
		'attr' => 'Colour', 'options' => [ 'Yellow' => 1000, 'White' => 1000 ],
		'specs' => [ 'Price' => 'KSh 1,000', 'Colours' => 'Yellow or white', 'Use' => 'Shows and display, not instead of official plates' ],
		'install' => [ 'Order, then send us the text you want on WhatsApp.', 'We send a preview before making it.' ],
	],
	[
		'slug' => 'usa-custom-plate', 'name' => 'USA & Foreign Custom Plate', 'cats' => [ 'show-plates' ],
		'badge' => 'Show plate', 'illustration' => true,
		'short' => 'A USA-style custom plate in any design you choose. KSh 2,500.',
		'desc' => 'Pick any American state style (or another country) and your own text. Send us a picture of the style you like on WhatsApp. For display and shows only.',
		'price' => 2500,
		'specs' => [ 'Price' => 'KSh 2,500', 'Style' => 'Any USA state, or another country', 'Use' => 'Display and shows' ],
		'install' => [ 'Order, then send the style and text on WhatsApp.', 'We send a preview before making it.' ],
	],
	// Signs & CNC services
	[
		'slug' => 'cellular-forever-board', 'name' => 'Cellular & Forever Board Signs', 'cats' => [ 'signs-cnc' ],
		'badge' => 'Signs', 'illustration' => true,
		'short' => 'Signs and cut letters on cellular board (8 mm or 18 mm) or 3 mm forever board, made to your size.',
		'desc' => 'Light, strong boards for shop signs, office signs, 3D letters and displays. Tell us the size and design on WhatsApp and we confirm the final price for your sign.',
		'attr' => 'Board', 'options' => [ '3 mm forever board' => 1500, '8 mm cellular board' => 4000, '18 mm cellular board' => 6500 ],
		'specs' => [ 'Boards' => '3 mm forever board, 8 mm and 18 mm cellular board', 'Size' => 'Made to your size', 'Design' => 'We design it, or send your file' ],
		'install' => [ 'Order, then send the size and design on WhatsApp.', 'We confirm the price and send a proof.' ],
		'upsells' => [ 'cnc-cutting' ],
	],
	[
		'slug' => 'perspex-acrylic', 'name' => 'Perspex (Acrylic), Any Colour', 'cats' => [ 'signs-cnc' ],
		'badge' => 'Signs', 'illustration' => true,
		'short' => 'Perspex in any colour for signs, letters and displays: 3 mm, 5 mm or 10 mm thick.',
		'desc' => 'Glossy, strong perspex for signs, reception logos, name plates and displays, cut to shape on our CNC. Tell us the size and design on WhatsApp.',
		'attr' => 'Thickness', 'options' => [ '3 mm' => 4000, '5 mm' => 13000, '10 mm' => 24000 ],
		'specs' => [ 'Thickness' => '3 mm, 5 mm or 10 mm', 'Colour' => 'Any colour', 'Cutting' => 'CNC cut to your shape' ],
		'install' => [ 'Order, then send the size and design on WhatsApp.', 'We confirm the price and send a proof.' ],
		'upsells' => [ 'cnc-cutting' ],
	],
	[
		'slug' => 'aluminium-composite-panel', 'name' => 'Aluminium Composite Panel (ACP)', 'cats' => [ 'signs-cnc' ],
		'badge' => 'Per piece', 'unit' => 'per piece', 'illustration' => true,
		'short' => 'ACP panels, 3 mm or 4 mm: black, red or normal colours, or gold and silver chrome.',
		'desc' => 'Aluminium composite panel is the strong, weatherproof board used for shop fronts and outdoor signs. Choose the colour and thickness; we can cut it and add your lettering.',
		'attr' => 'Colour', 'options' => [ 'Black, red or normal colours' => 5000, 'Gold or silver chrome' => 7000 ],
		'attr2' => 'Thickness', 'options2' => [ '3 mm', '4 mm' ],
		'specs' => [ 'Price' => 'Per piece', 'Thickness' => '3 mm or 4 mm', 'Colours' => 'Black, red, normal colours, gold or silver chrome' ],
		'install' => [ 'Order, then tell us the size and design on WhatsApp.' ],
		'upsells' => [ 'cnc-cutting' ],
	],
	[
		'slug' => 'cnc-cutting', 'name' => 'CNC Cutting Service', 'cats' => [ 'signs-cnc' ],
		'badge' => 'CNC', 'from' => true, 'illustration' => true,
		'short' => 'All CNC cutting jobs cost from KSh 1,000 up to KSh 2,000, depending on the design.',
		'desc' => 'Letters, logos and shapes cut on our CNC machine from board, perspex or ACP. Send your design on WhatsApp and we confirm the price before cutting.',
		'price' => 1000,
		'specs' => [ 'Price' => 'KSh 1,000 – 2,000 per job', 'Materials' => 'Cellular board, forever board, perspex, ACP' ],
		'install' => [ 'Send your design or logo on WhatsApp.', 'We confirm the price and cut it.' ],
	],
	[
		'slug' => 'custom-signs', 'name' => 'Custom Signs (Any Size)', 'cats' => [ 'signs-cnc' ],
		'badge' => 'Signs', 'illustration' => true,
		'short' => 'Shop, office and building signs made to your size. Ask us for the price.',
		'desc' => 'From a small door sign to a full shop front: tell us where it goes, the size and your design. ' . $on_request,
		'specs' => [ 'Size' => 'Any size', 'Materials' => 'Board, perspex, ACP, vinyl', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send a photo of the wall or shop front and your logo on WhatsApp.', 'We send the price and a design.' ],
	],
	// Vinyl, wraps & PPF sales
	[
		'slug' => 'vinyl-by-the-metre', 'name' => 'Vinyl by the Metre', 'cats' => [ 'vinyl-wraps-ppf' ],
		'badge' => 'Per metre', 'unit' => 'per metre', 'featured' => true,
		'short' => 'Buy vinyl for your own projects. Choose the type, then set the quantity to the number of metres.',
		'desc' => 'Normal colours and chrome come 2 ft wide; hologram, glitter hologram and luminous 4 ft; 3D carbon fibre and AK47 5 ft. Pick up at the workshop or we deliver.',
		'attr' => 'Vinyl', 'options' => [ '2 ft normal colours' => 200, '2 ft gold / silver chrome' => 350, '4 ft luminous colours' => 800, '4 ft hologram' => 1000, '5 ft 3D carbon fibre' => 1000, '5 ft AK47' => 1000, '4 ft glitter hologram' => 1500 ],
		'specs' => [ 'Price' => 'Per metre', 'Widths' => '2 ft, 4 ft or 5 ft, by type' ],
		'install' => [ 'Choose the vinyl and enter the metres as the quantity.', 'Tell us the colour on WhatsApp after you order.' ],
		'upsells' => [ 'wrap-vinyl-roll' ],
	],
	[
		'slug' => 'ppf-paint-protection-film', 'name' => 'PPF Paint Protection Film (5 ft, 7 micron)', 'cats' => [ 'vinyl-wraps-ppf' ],
		'badge' => 'Per metre', 'unit' => 'per metre', 'illustration' => true,
		'short' => 'Clear paint protection film, 5 ft wide and 7 micron thick, sold by the metre.',
		'desc' => 'Clear film that protects paint from scratches and stone chips. Set the quantity to the number of metres you need.',
		'price' => 3500,
		'specs' => [ 'Price' => 'Per metre', 'Width' => '5 ft', 'Thickness' => '7 micron' ],
		'install' => [ 'Enter the metres as the quantity.', 'Ask us on WhatsApp if you want it fitted.' ],
	],
	[
		'slug' => 'wrap-vinyl-roll', 'name' => 'Wrap Vinyl, Full Roll', 'cats' => [ 'vinyl-wraps-ppf' ],
		'badge' => 'Per roll', 'unit' => 'per roll', 'illustration' => true,
		'short' => 'A full roll of normal wrap vinyl. KSh 50,000.',
		'desc' => 'A full roll of normal wrap vinyl for wrapping a car. Tell us the colour you want on WhatsApp after you order.',
		'price' => 50000,
		'specs' => [ 'Price' => 'KSh 50,000 per roll', 'Type' => 'Normal wrap vinyl' ],
		'install' => [ 'Order, then tell us the colour on WhatsApp.' ],
		'upsells' => [ 'vinyl-by-the-metre' ],
	],
	// Sound deadening
	[
		'slug' => 'sound-deadening-material', 'name' => 'Sound Deadening Material', 'cats' => [ 'sound-deadening' ],
		'badge' => 'Sound deadening', 'illustration' => true,
		'short' => 'Sound deadening material for car doors, floors and boots. Ask us for the price.',
		'desc' => 'Makes the car quieter inside and the sound system clearer. ' . $on_request,
		'specs' => [ 'For' => 'Doors, floors, boots', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Tell us your car and where you want it on WhatsApp.', 'We send the price and book a time.' ],
	],
	// Shop & vehicle branding
	[
		'slug' => 'shop-office-branding', 'name' => 'Shop, Office & Wall Branding', 'cats' => [ 'vehicle-branding' ],
		'badge' => 'Branding',
		'short' => 'Wall graphics, window branding and signs for shops and offices, like the wall in our studio. Ask us for the price.',
		'desc' => 'We design, print and fit it. ' . $on_request,
		'specs' => [ 'Includes' => 'Wall graphics, window branding, signs', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send photos of the space and your logo on WhatsApp.', 'We send the price and a design.' ],
	],
	[
		'slug' => 'fleet-tuk-tuk-branding', 'name' => 'Fleet & Tuk-Tuk Branding', 'cats' => [ 'vehicle-branding' ],
		'badge' => 'Vehicle branding',
		'short' => 'Branding for tuk-tuks, taxis, vans and company cars, like this tuk-tuk fleet. Ask us for the price.',
		'desc' => 'We design the layout and brand every vehicle the same way. ' . $on_request,
		'specs' => [ 'Vehicles' => 'Tuk-tuks, cars, vans, matatus', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Tell us the vehicles and send your logo on WhatsApp.', 'We send the price and a design.' ],
	],
	[
		'slug' => 'car-wraps', 'name' => 'Car Wraps (Full & Partial)', 'cats' => [ 'vehicle-branding' ],
		'badge' => 'Vehicle branding',
		'short' => 'Full and partial wraps, like this holographic bonnet. Ask us for the price for your car.',
		'desc' => 'Change the colour of your car or wrap just the bonnet, roof or mirrors; the paint stays protected underneath. ' . $on_request,
		'specs' => [ 'Wraps' => 'Full or partial: bonnet, roof, mirrors', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send a photo of your car and the colour you want on WhatsApp.', 'We send the price and book a time.' ],
		'upsells' => [ 'wrap-vinyl-roll' ],
	],
	[
		'slug' => 'motorbike-top-box-decals', 'name' => 'Motorbike Graphics & Top Box Decals', 'cats' => [ 'vehicle-branding' ],
		'badge' => 'Vehicle branding',
		'short' => 'Graphics for your bike, top box and panniers, like this adventure-bike set. Ask us for the price.',
		'desc' => 'Mountains, your bike\'s name, your route or your handle, cut in vinyl and fitted. ' . $on_request,
		'specs' => [ 'For' => 'Motorbikes, top boxes, panniers', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send a photo of your bike and your idea on WhatsApp.' ],
	],
	// Digital printing
	[
		'slug' => 'garage-service-tags', 'name' => 'Garage Service Tags', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true, 'featured' => true,
		'short' => 'Service reminder tags with your garage name and contacts. 200 for KSh 2,000.',
		'desc' => 'Hang them on the mirror after every service so customers know when to come back, and who to call.',
		'attr' => 'Quantity', 'options' => [ '200 tags' => 2000, '400 tags' => 4000 ],
		'specs' => [ 'Price' => '200 tags KSh 2,000 · 400 tags KSh 4,000', 'Design' => 'With your garage name, phone and logo' ],
		'install' => [ 'Send your garage name, phone and logo on WhatsApp.', 'Approve the proof; we print.' ],
	],
	[
		'slug' => 'business-cards', 'name' => 'Business Cards', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true,
		'short' => 'Business cards in matt, laminated frost or glossy. Ask us for the price.',
		'desc' => 'We design your card from your logo and details, or print your file. ' . $on_request,
		'specs' => [ 'Finishes' => 'Matt, laminated frost, glossy', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your logo, name, phone and email on WhatsApp.', 'We send the price and a proof.' ],
		'upsells' => [ 'flyers' ],
	],
	[
		'slug' => 'flyers', 'name' => 'Flyers', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true,
		'short' => 'Flyers in A3, A4 or A5 for events, offers, menus and notices. Ask us for the price.',
		'desc' => 'Send your design, or tell us what you need and we lay it out. ' . $on_request,
		'specs' => [ 'Sizes' => 'A3, A4, A5', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your text, logo and photos on WhatsApp.', 'We send the price and a proof.' ],
		'upsells' => [ 'posters' ],
	],
	[
		'slug' => 'posters', 'name' => 'Posters', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true,
		'short' => 'Posters in A3, A4 or A5. Ask us for the price.',
		'desc' => $on_request,
		'specs' => [ 'Sizes' => 'A3, A4, A5', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your design or idea on WhatsApp.' ],
	],
	[
		'slug' => 'sticker-paper-printing', 'name' => 'Sticker Paper Printing', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true,
		'short' => 'Stickers printed on A3, A4 or A5 sticker paper. Ask us for the price.',
		'desc' => $on_request,
		'specs' => [ 'Sizes' => 'A3, A4, A5', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your design on WhatsApp.' ],
	],
	[
		'slug' => 'booklet-document-printing', 'name' => 'Booklets', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing',
		'short' => 'Booklets, manuals and company profiles in A4 or A5, like these SOP manuals. Ask us for the price.',
		'desc' => 'Send your PDF and tell us how many copies. ' . $on_request,
		'specs' => [ 'Sizes' => 'A4, A5', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send a PDF on WhatsApp so the layout stays exactly as you made it.' ],
	],
	[
		'slug' => 'funeral-programmes', 'name' => 'Funeral Programmes', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true,
		'short' => 'Funeral programmes in A4 or A5, designed and printed with care. Ask us for the price.',
		'desc' => 'Send the photos, the order of service and how many copies you need, and we take care of the layout. ' . $on_request,
		'specs' => [ 'Sizes' => 'A4, A5', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send the photos and text on WhatsApp.', 'We send the price and a proof to check.' ],
	],
	[
		'slug' => 'certificates', 'name' => 'Certificates', 'cats' => [ 'digital-printing' ],
		'badge' => 'Digital printing', 'illustration' => true,
		'short' => 'Certificates in embossed, glossy or matt, with your logo and names. Ask us for the price.',
		'desc' => 'For schools, churches, trainings and awards. Send the wording and the list of names. ' . $on_request,
		'specs' => [ 'Finishes' => 'Embossed, glossy, matt', 'Names' => 'Printed from your list', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your logo, wording and the list of names on WhatsApp.', 'Check the proof carefully for spellings.' ],
	],
	// Headlight tinting
	[
		'slug' => 'headlight-taillight-tint', 'name' => 'Headlight & Back Light Tint', 'cats' => [ 'headlight-tints' ],
		'badge' => 'Headlight tint', 'illustration' => true, 'featured' => true,
		'short' => 'Tint film for your front headlights, back lights or both, cut to your lights and fitted at our workshop.',
		'desc' => 'Choose your vehicle and which lights. We fit it at Rabai Road Estate in about an hour.',
		'attr' => 'Vehicle', 'attr2' => 'Lights', 'options' => [
			'Saloon' => [ 'Front headlights' => 1500, 'Back lights' => 1500, 'Front + back' => 3000 ],
			'SUV'    => [ 'Front headlights' => 2500, 'Back lights' => 2000, 'Front + back' => 4500 ],
			'Van'    => [ 'Front headlights' => 1500, 'Back lights' => 1500, 'Front + back' => 3000 ],
		],
		'specs' => [ 'Vehicles' => 'Saloons, SUVs and vans', 'Fitting' => 'About 1 hour at the workshop' ],
		'install' => [ 'Order, then WhatsApp or call us to book a time.', 'No car wash for 3 days after fitting.' ],
		'upsells' => [ 'presidential-ceramic-tint' ],
	],
	// Screen printing
	[
		'slug' => 'tshirt-printing', 'name' => 'T-Shirt Screen Printing', 'cats' => [ 'screen-printing' ],
		'badge' => 'Screen printing', 'illustration' => true,
		'short' => 'T-shirts printed in full colour, one colour, half tone or corporate style. Ask us for the price.',
		'desc' => 'For events, teams, churches, schools and companies. Tell us the design, how many shirts and the sizes. ' . $on_request,
		'specs' => [ 'Print' => 'Full colour, one colour, half tone, corporate', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your design, the number of shirts and sizes on WhatsApp.', 'We send the price and a mockup.' ],
		'upsells' => [ 'bag-printing' ],
	],
	[
		'slug' => 'bag-printing', 'name' => 'Bag Printing', 'cats' => [ 'screen-printing' ],
		'badge' => 'Screen printing', 'illustration' => true,
		'short' => 'Your logo printed on bags in full colour or one colour. Ask us for the price.',
		'desc' => 'Branded bags for shops, events and giveaways. ' . $on_request,
		'specs' => [ 'Print' => 'Full colour or one colour', 'Price' => 'On request: call or WhatsApp us' ],
		'install' => [ 'Send your logo and how many bags on WhatsApp.' ],
		'upsells' => [ 'tshirt-printing' ],
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
	$variable = ! empty( $d['options'] );
	$matrix   = $variable && is_array( reset( $d['options'] ) );
	$second   = $matrix ? array_keys( reset( $d['options'] ) ) : ( $d['options2'] ?? [] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['short'] );
	$p->set_category_ids( array_map( fn( $c ) => $cat[ $c ], $d['cats'] ) );
	$p->set_featured( ! empty( $d['featured'] ) );
	$p->update_meta_data( '_sh_badge', $d['badge'] );
	$p->update_meta_data( '_sh_specs', implode( "\n", array_map( fn( $k, $v ) => "$k: $v", array_keys( $d['specs'] ), $d['specs'] ) ) );
	$p->update_meta_data( '_sh_install', implode( "\n", $d['install'] ) );
	if ( ! empty( $d['unit'] ) ) $p->update_meta_data( '_sh_unit', $d['unit'] );
	if ( ! empty( $d['from'] ) ) $p->update_meta_data( '_sh_from', 'yes' );
	if ( ! empty( $d['illustration'] ) ) $p->update_meta_data( '_sh_illustration', 'yes' );

	if ( $variable ) {
		$attrs = [ sh_local_attr( $d['attr'], array_keys( $d['options'] ), 0 ) ];
		if ( $second ) $attrs[] = sh_local_attr( $d['attr2'], $second, 1 );
		if ( ! empty( $d['options3'] ) ) $attrs[] = sh_local_attr( $d['attr3'], $d['options3'], 2 );
		$p->set_attributes( $attrs );
		$defaults = [ sanitize_title( $d['attr'] ) => array_key_first( $d['options'] ) ];
		if ( $second ) $defaults[ sanitize_title( $d['attr2'] ) ] = $second[0];
		if ( ! empty( $d['options3'] ) ) $defaults[ sanitize_title( $d['attr3'] ) ] = $d['options3'][0];
		$p->set_default_attributes( $defaults );
	} elseif ( isset( $d['price'] ) ) {
		$p->set_regular_price( $d['price'] );
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
		// One variation per combination of the choices.
		$key  = sanitize_title( $d['attr'] );
		$key2 = $second ? sanitize_title( $d['attr2'] ) : null;
		$key3 = ! empty( $d['options3'] ) ? sanitize_title( $d['attr3'] ) : null;
		$n    = 0;
		foreach ( $d['options'] as $label => $price ) {
			foreach ( $second ?: [ null ] as $o2 ) {
				foreach ( $d['options3'] ?? [ null ] as $o3 ) {
					$va = [ $key => $label ];
					if ( $o2 !== null ) $va[ $key2 ] = $o2;
					if ( $o3 !== null ) $va[ $key3 ] = $o3;
					$amount = $matrix ? $price[ $o2 ] : $price * ( 1 + ( $o2 !== null ? $d['add2'][ $o2 ] ?? 0 : 0 ) / 100 );
					$v = new WC_Product_Variation();
					$v->set_parent_id( $pid );
					$v->set_attributes( $va );
					$v->set_regular_price( (string) ( round( $amount / 50 ) * 50 ) );
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
sh_page( 'price-list', 'Price list', "<!-- wp:paragraph -->\n<p>All our prices in one place. Tap any item to order it, or ask us on WhatsApp. Items marked \"Ask for price\" depend on your size and design: send us the details and we reply with the price.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[sh_price_list]\n<!-- /wp:shortcode -->" );
sh_page( 'estimator', 'Price calculator', "<!-- wp:paragraph -->\n<p>Work out the price of a banner, stickers or vinyl from its size. Then order it, or send the details to us on WhatsApp.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[sh_estimator]\n<!-- /wp:shortcode -->" );
sh_page( 'about', 'About STICKER HUB', '' ); // Content comes from the theme's page-about.html template.
sh_page( 'faqs', 'FAQs', '' ); // Content comes from page-faqs.html.
$shop_page = (int) wc_get_page_id( 'shop' );
if ( $shop_page > 0 ) wp_update_post( [ 'ID' => $shop_page, 'post_title' => 'All products' ] );

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
