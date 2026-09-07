<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Pro Forms action: save submissions to the Exam Success waitlist.
 */
class WPES_Elementor_Waitlist_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {

	public function get_name() {
		return 'wpes_waitlist';
	}

	public function get_label() {
		return esc_html__( 'Waitlist', 'wp-exam-success' );
	}

	public function register_settings_section( $widget ) {
		$widget->start_controls_section(
			'section_wpes_waitlist',
			array(
				'label'     => esc_html__( 'Waitlist', 'wp-exam-success' ),
				'condition' => array(
					'submit_actions' => $this->get_name(),
				),
			)
		);

		$widget->add_control(
			'wpes_waitlist_email_field',
			array(
				'label'       => esc_html__( 'Email Field ID', 'wp-exam-success' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Elementor form field ID for the email address. Leave blank to auto-detect a field named email.', 'wp-exam-success' ),
			)
		);

		$widget->add_control(
			'wpes_waitlist_name_field',
			array(
				'label'       => esc_html__( 'Name Field ID', 'wp-exam-success' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Elementor form field ID for the name. Leave blank to auto-detect a field named name.', 'wp-exam-success' ),
			)
		);

		$widget->end_controls_section();
	}

	public function on_export( $element ) {
		unset(
			$element['settings']['wpes_waitlist_email_field'],
			$element['settings']['wpes_waitlist_name_field']
		);
		return $element;
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run( $record, $ajax_handler ) {
		$settings = $record->get( 'form_settings' );
		$raw      = $record->get( 'fields' );

		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return;
		}

		$fields = array();
		foreach ( $raw as $id => $field ) {
			$fields[ $id ] = array(
				'id'    => isset( $field['id'] ) ? $field['id'] : $id,
				'type'  => isset( $field['type'] ) ? $field['type'] : '',
				'title' => isset( $field['title'] ) ? $field['title'] : '',
				'value' => isset( $field['value'] ) ? $field['value'] : '',
			);
		}

		$email_field = ! empty( $settings['wpes_waitlist_email_field'] ) ? $settings['wpes_waitlist_email_field'] : 'email';
		$name_field  = ! empty( $settings['wpes_waitlist_name_field'] ) ? $settings['wpes_waitlist_name_field'] : 'name';

		$email = $this->get_field_value( $fields, $email_field );
		$name  = $this->get_field_value( $fields, $name_field );

		if ( ! $email ) {
			$email = $this->find_value_by_type( $fields, 'email' );
		}
		if ( ! $name ) {
			$name = $this->find_value_by_keys( $fields, array( 'name', 'full_name', 'fullname', 'your-name' ) );
		}

		$form_id   = isset( $settings['form_id'] ) ? $settings['form_id'] : '';
		$form_name = isset( $settings['form_name'] ) ? $settings['form_name'] : '';

		$result = WPES_Waitlist::insert(
			array(
				'form_id'   => $form_id,
				'form_name' => $form_name,
				'name'      => $name,
				'email'     => $email,
				'fields'    => $fields,
			)
		);

		if ( false === $result && $ajax_handler ) {
			$ajax_handler->add_error_message(
				esc_html__( 'Could not save your waitlist submission. Please try again.', 'wp-exam-success' )
			);
		}
	}

	/**
	 * @param array  $fields Field map.
	 * @param string $key    Field ID.
	 * @return string
	 */
	protected function get_field_value( array $fields, $key ) {
		if ( isset( $fields[ $key ]['value'] ) ) {
			return is_array( $fields[ $key ]['value'] )
				? implode( ', ', $fields[ $key ]['value'] )
				: (string) $fields[ $key ]['value'];
		}
		foreach ( $fields as $field ) {
			if ( isset( $field['id'] ) && $field['id'] === $key ) {
				return is_array( $field['value'] )
					? implode( ', ', $field['value'] )
					: (string) $field['value'];
			}
		}
		return '';
	}

	/**
	 * @param array  $fields Field map.
	 * @param string $type   Field type.
	 * @return string
	 */
	protected function find_value_by_type( array $fields, $type ) {
		foreach ( $fields as $field ) {
			if ( isset( $field['type'] ) && $type === $field['type'] ) {
				return is_array( $field['value'] )
					? implode( ', ', $field['value'] )
					: (string) $field['value'];
			}
		}
		return '';
	}

	/**
	 * @param array $fields Field map.
	 * @param array $keys   Candidate IDs.
	 * @return string
	 */
	protected function find_value_by_keys( array $fields, array $keys ) {
		foreach ( $keys as $key ) {
			$value = $this->get_field_value( $fields, $key );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}
}
