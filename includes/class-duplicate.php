<?php
defined( 'ABSPATH' ) || exit;

class Wpcac_Duplicate {
	protected static $instance = null;

	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {

	}

	public function createDuplicate( $post, $parentId = '' ) {
		$newPostAuthor   = wp_get_current_user();
		$newPostAuthorId = $newPostAuthor->ID;
		$title           = '';

		if ( 'attachment' !== $post->post_type ) {
			$title = trim( $post->post_title );
		}

		// empty title
		if ( '' === $title ) {
			$title = __( 'Untitled', 'wpc-admin-columns' );
		}

		$newPost = [
			'menu_order'            => $post->menu_order,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_author'           => $newPostAuthorId,
			'post_content'          => $post->post_content,
			'post_content_filtered' => $post->post_content_filtered,
			'post_excerpt'          => $post->post_excerpt,
			'post_mime_type'        => $post->post_mime_type,
			'post_parent'           => empty( $parentId ) ? $post->post_parent : $parentId,
			'post_password'         => $post->post_password,
			'post_status'           => 'draft',
			'post_title'            => $title,
			'post_type'             => $post->post_type,
			'post_name'             => $post->post_name,
			'post_date'             => $post->post_date,
			'post_date_gmt'         => get_gmt_from_date( $post->post_date ),
		];

		$newPostId = wp_insert_post( wp_slash( $newPost ) );

		// Duplicate postmeta, comment, attachment, children, taxonomies,
		if ( 0 !== $newPostId && ! is_wp_error( $newPostId ) ) {
			$this->duplicateDetails( $newPostId, $post );
		}

		return $newPostId;
	}

	// Run all function to copy details
	private function duplicateDetails( $newPostId, $post ) {
		$this->copyPostMeta( $newPostId, $post );
		$this->copyChildren( $newPostId, $post );
		$this->copyComments( $newPostId, $post );
		$this->copyTaxonomies( $newPostId, $post );
		$this->copyOrderDetails( $newPostId, $post );
	}

	// Duplicate post meta
	private function copyPostMeta( $newPostId, $post ) {
		$metaKeys = get_post_custom_keys( $post->ID );

		if ( empty( $metaKeys ) ) {
			return;
		}

		foreach ( $metaKeys as $metaKey ) {
			$metaValues = get_post_custom_values( $metaKey, $post->ID );

			foreach ( $metaValues as $metaValue ) {
				$metaValue = maybe_unserialize( $metaValue );
				add_post_meta( $newPostId, $metaKey, wp_slash( $metaValue ) );
			}
		}
	}

	// Duplicate all children post
	private function copyChildren( $newPostId, $post ) {
		$postChildren = get_posts(
			[
				'post_type'   => 'any',
				'numberposts' => - 1,
				'post_status' => 'any',
				'post_parent' => $post->ID,
			]
		);

		foreach ( $postChildren as $children ) {
			if ( 'attachment' === $children->post_type ) {
				continue;
			}

			$this->createDuplicate( $children, $newPostId );
		}
	}

	// Duplicate all comments of post
	private function copyComments( $newPostId, $post ) {
		$comments = get_comments(
			[
				'post_id' => $post->ID,
				'order'   => 'ASC',
				'orderby' => 'comment_date_gmt',
			]
		);

		$parentId = [];

		foreach ( $comments as $comment ) {
			// do not copy pingbacks or trackbacks
			if ( 'pingback' === $comment->comment_type || 'trackback' === $comment->comment_type ) {
				continue;
			}

			$parent                           = ( $comment->comment_parent && $parentId[ $comment->comment_parent ] ) ? $parentId[ $comment->comment_parent ] : 0;
			$newComment                       = [
				'comment_post_ID'      => $newPostId,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_content'      => $comment->comment_content,
				'comment_type'         => $comment->comment_type,
				'comment_parent'       => $parent,
				'user_id'              => $comment->user_id,
				'comment_author_IP'    => $comment->comment_author_IP,
				'comment_agent'        => $comment->comment_agent,
				'comment_karma'        => $comment->comment_karma,
				'comment_approved'     => $comment->comment_approved,
				'comment_date'         => $comment->comment_date,
				'comment_date_gmt'     => get_gmt_from_date( $comment->comment_date ),
			];
			$newCommentId                     = wp_insert_comment( $newComment );
			$parentId[ $comment->comment_ID ] = $newCommentId;
		}
	}

	// Duplicate post taxonomies
	private function copyTaxonomies( $newPostId, $post ) {
		global $wpdb;

		if ( isset( $wpdb->terms ) ) {
			wp_set_object_terms( $newPostId, null, 'category' );
			$taxonomies = get_object_taxonomies( $post->post_type );

			if ( post_type_supports( $post->post_type, 'post-formats' ) && ! in_array( 'post_format', $taxonomies ) ) {
				$taxonomies[] = 'post_format';
			}

			foreach ( $taxonomies as $taxonomy ) {
				$postTerms = wp_get_object_terms( $post->ID, $taxonomy, [ 'orderby' => 'term_order' ] );
				$terms     = [];
				for ( $i = 0; $i < count( $postTerms ); $i ++ ) {
					$terms[] = $postTerms[ $i ]->slug;
				}
				wp_set_object_terms( $newPostId, $terms, $taxonomy );
			}
		}
	}

	// Duplicate order details
	private function copyOrderDetails( $newPostId, $post ) {
		$order_id = $post->ID;

		if ( $order = wc_get_order( $order_id ) ) {
			if ( ! $copyOrder = wc_get_order( $newPostId ) ) {
				$copyOrder = wc_create_order();
			}

			foreach ( $order->get_items() as $item ) {
				$products = wc_get_product( $item->get_product_id() );
				$quantity = $item->get_quantity();
				$copyOrder->add_product( $products, $quantity );
			}

			$orderMetaFields = [
				'customer_id',
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'billing_email',
				'billing_phone',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
				'shipping_country',
			];

			foreach ( $orderMetaFields as $metaField ) {
				$setMetaFunction = "set_{$metaField}";
				$getMetaFunction = "get_{$metaField}";
				$copyOrder->$setMetaFunction( $order->$getMetaFunction() );
			}

			$orderTax      = $order->get_items( 'tax' );
			$orderShipping = $order->get_items( 'shipping' );
			$orderFee      = $order->get_items( 'fee' );
			$orderCoupon   = $order->get_items( 'coupon' );

			foreach ( $orderTax as $tax ) {
				$copyOrder->add_item( $tax );
			}

			foreach ( $orderShipping as $shipping ) {
				$copyOrder->add_item( $shipping );
			}

			foreach ( $orderFee as $fee ) {
				$copyOrder->add_item( $fee );
			}

			foreach ( $orderCoupon as $coupon ) {
				$copyOrder->add_item( $coupon );
			}

			if ( apply_filters( 'wpcac_duplicate_order_add_note', true ) ) {
				$copyOrder->add_order_note( sprintf( /*translators: order id */ esc_html__( 'Cloned Order from #%d', 'wpc-admin-columns' ), $order_id ) );
				$order->add_order_note( sprintf( /*translators: order id */ esc_html__( 'Order cloned to #%d', 'wpc-admin-columns' ), $copyOrder->get_id() ) );
			}

			$copyOrder->calculate_totals();
			$copyOrder->update_status( 'on-hold' );
			$copyOrder->save();
		}
	}
}
