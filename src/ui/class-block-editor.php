<?php
/**
 * Duplicate Post class to manage the block editor UI.
 *
 * @package Duplicate_Post
 */

namespace Yoast\WP\Duplicate_Post\UI;

use Yoast\WP\Duplicate_Post\Permissions_Helper;
use Yoast\WP\Duplicate_Post\Utils;

/**
 * Represents the Block_Editor class.
 */
class Block_Editor {

	/**
	 * Holds the object to create the action link to duplicate.
	 *
	 * @var Link_Builder
	 */
	protected $link_builder;

	/**
	 * Holds the permissions helper.
	 *
	 * @var Permissions_Helper
	 */
	protected $permissions_helper;

	/**
	 * Initializes the class.
	 *
	 * @param Link_Builder       $link_builder       The link builder.
	 * @param Permissions_Helper $permissions_helper The permissions helper.
	 */
	public function __construct( Link_Builder $link_builder, Permissions_Helper $permissions_helper ) {
		$this->link_builder       = $link_builder;
		$this->permissions_helper = $permissions_helper;

		$this->register_hooks();
	}

	/**
	 * Adds hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		\add_action( 'admin_enqueue_scripts', [ $this, 'should_previously_used_keyword_assessment_run' ], 9 );
		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_scripts' ] );
	}

	/**
	 * Disables the Yoast SEO PreviouslyUsedKeyword assessment for posts duplicated for Rewrite & Republish.
	 *
	 * @return void
	 */
	public function should_previously_used_keyword_assessment_run() {
		global $pagenow;
		if ( ! \in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$post = \get_post();

		if ( \is_null( $post ) ) {
			return;
		}

		$skip_assessment = \get_post_meta( $post->ID, '_dp_is_rewrite_republish_copy', true );

		if ( ! empty( $skip_assessment ) ) {
			\add_filter( 'wpseo_previously_used_keyword_active', '__return_false' );
		}
	}

	/**
	 * Enqueues the necessary JavaScript code for the block editor.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_scripts() {
		$post = \get_post();

		if ( \is_null( $post ) ) {
			return;
		}

		\wp_enqueue_script(
			'duplicate_post_edit_script',
			\plugins_url( \sprintf( 'js/dist/duplicate-post-edit-%s.js', Utils::flatten_version( DUPLICATE_POST_CURRENT_VERSION ) ), DUPLICATE_POST_FILE ),
			[
				'wp-blocks',
				'wp-element',
				'wp-i18n',
			],
			DUPLICATE_POST_CURRENT_VERSION,
			true
		);

		\wp_localize_script(
			'duplicate_post_edit_script',
			'duplicatePost',
			[
				'new_draft_link'             => $this->get_new_draft_permalink(),
				'rewrite_and_republish_link' => $this->get_rewrite_republish_permalink(),
				'rewriting'                  => \get_post_meta( $post->ID, '_dp_is_rewrite_republish_copy', true ) ? 1 : 0,
				'originalEditURL'            => $this->get_original_post_edit_url(),
				'republished'                => ( ! empty( $_REQUEST['dprepublished'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'republishedText'            => ( ! empty( $_REQUEST['dprepublished'] ) ) ? Utils::get_republished_notice_text() : '', // phpcs:ignore WordPress.Security.NonceVerification
			]
		);
	}

	/**
	 * Generates a New Draft permalink for the current post.
	 *
	 * @return string The permalink. Returns empty if the post can't be copied.
	 */
	public function get_new_draft_permalink() {
		$post = \get_post();

		/** This filter is documented in class-row-actions.php */
		if ( ! apply_filters( 'duplicate_post_show_link', $this->permissions_helper->should_link_be_displayed( $post ), $post ) ) {
			return '';
		}

		return $this->link_builder->build_new_draft_link( $post );
	}

	/**
	 * Generates a Rewrite & Republish permalink for the current post.
	 *
	 * @return string The permalink. Returns empty if the post cannot be copied for Rewrite & Republish.
	 */
	public function get_rewrite_republish_permalink() {
		$post = \get_post();

		/** This filter is documented in class-row-actions.php */
		if ( $post->post_status !== 'publish' || ! apply_filters( 'duplicate_post_show_link', $this->permissions_helper->should_link_be_displayed( $post ), $post ) ) {
			return '';
		}

		return $this->link_builder->build_rewrite_and_republish_link( $post );
	}

	/**
	 * Generates a URL to the original post edit screen.
	 *
	 * @return string The URL. Empty if the copy post doesn't have an original.
	 */
	public function get_original_post_edit_url() {
		$post = \get_post();

		if ( \is_null( $post ) ) {
			return '';
		}

		$original_post_id = Utils::get_original_post_id( $post->ID );

		if ( ! $original_post_id ) {
			return '';
		}

		return \add_query_arg(
			[
				'dprepublished' => 1,
				'dpcopy'        => $post->ID,
				'nonce'         => \wp_create_nonce( 'dp-republish' ),
			],
			\admin_url( 'post.php?action=edit&post=' . $original_post_id )
		);
	}
}
