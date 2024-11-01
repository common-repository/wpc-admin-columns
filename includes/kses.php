<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpcac_kses_allowed_html' ) ) {
	add_filter( 'wp_kses_allowed_html', 'wpcac_kses_allowed_html', 99, 2 );

	function wpcac_kses_allowed_html( $allowed_html, $context ) {
		switch ( $context ) {
			case 'wpcac_a':
				return [
					'a' => [
						'id'     => [],
						'class'  => [],
						'style'  => [],
						'href'   => [],
						'target' => [],
						'rel'    => [],
						'title'  => [],
					],
				];
			case 'wpcac_img':
				return [
					'img' => [
						'id'          => [],
						'class'       => [],
						'width'       => [],
						'height'      => [],
						'sizes'       => [],
						'srcset'      => [],
						'data-src'    => [],
						'data-srcset' => [],
						'src'         => [],
						'alt'         => []
					]
				];
			case 'wpcac_button':
				return [
					'button' => [
						'id'       => [],
						'class'    => [],
						'disabled' => [],
						'name'     => [],
						'type'     => [],
						'value'    => [],
					]
				];
			case 'wpcac_price':
				return [
					'del'  => [],
					'ins'  => [],
					'bdi'  => [],
					'span' => [ 'id' => [], 'class' => [] ]
				];
		}

		return $allowed_html;
	}
}