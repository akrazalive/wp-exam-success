( function ( $ ) {
	'use strict';

	var notyf = new Notyf( { duration: 4000, dismissible: true } );

	function post( action, data ) {
		return $.post( WPES_Admin.ajax_url, $.extend( { action: action, nonce: WPES_Admin.nonce }, data ) );
	}

	function serializeObject( $form ) {
		var obj = {};
		$.each( $form.serializeArray(), function() {
			if ( obj[ this.name ] ) {
				if ( ! Array.isArray( obj[ this.name ] ) ) {
					obj[ this.name ] = [ obj[ this.name ] ];
				}
				obj[ this.name ].push( this.value );
			} else {
				obj[ this.name ] = this.value;
			}
		} );
		return obj;
	}

	function getEditorContent( id ) {
		if ( typeof tinymce !== 'undefined' && tinymce.get( id ) ) {
			return tinymce.get( id ).getContent();
		}
		return $( '#' + id ).val() || '';
	}

	function setEditorContent( id, content ) {
		if ( typeof tinymce !== 'undefined' && tinymce.get( id ) ) {
			tinymce.get( id ).setContent( content || '' );
		}
		$( '#' + id ).val( content || '' );
	}

	function dtButtons() {
		return [
			{ extend: 'csv', className: 'btn btn-sm btn-outline-secondary' },
			{ extend: 'excel', className: 'btn btn-sm btn-outline-secondary' },
			{ extend: 'pdf', className: 'btn btn-sm btn-outline-secondary' },
			{ extend: 'print', className: 'btn btn-sm btn-outline-secondary' }
		];
	}

	var tables = {};

	$( function () {

		/* ---- Classes DataTable ---- */
		if ( $( '#wpesClassesTable' ).length ) {
			tables.classes = $( '#wpesClassesTable' ).DataTable( {
				processing: true,
				serverSide: true,
				ajax: {
					url: WPES_Admin.ajax_url,
					type: 'POST',
					data: function ( d ) {
						d.action = 'wpes_datatable_classes';
						d.nonce = WPES_Admin.nonce;
						d.status_filter = $( '#wpesClassStatusFilter' ).val();
					}
				},
				pageLength: 25,
				order: [],
				dom: 'Bfrtip',
				buttons: dtButtons(),
				language: { search: '', processing: '<div class="spinner-border spinner-border-sm"></div> Loading…' }
			} );

			$( '#wpesClassStatusFilter' ).on( 'change', function () {
				tables.classes.ajax.reload();
			} );
		}

		/* ---- Sessions DataTable ---- */
		if ( $( '#wpesSessionsTable' ).length ) {
			tables.sessions = $( '#wpesSessionsTable' ).DataTable( {
				processing: true,
				serverSide: true,
				ajax: {
					url: WPES_Admin.ajax_url,
					type: 'POST',
					data: function ( d ) {
						d.action = 'wpes_datatable_sessions';
						d.nonce = WPES_Admin.nonce;
						d.class_filter = $( '#wpesSessionClassFilter' ).val();
						d.status_filter = $( '#wpesSessionStatusFilter' ).val();
					}
				},
				pageLength: 25,
				order: [],
				dom: 'Bfrtip',
				buttons: dtButtons(),
				columnDefs: [ { orderable: false, targets: -1 } ],
				language: { search: '', processing: '<div class="spinner-border spinner-border-sm"></div> Loading…' }
			} );

			$( '#wpesSessionClassFilter, #wpesSessionStatusFilter' ).on( 'change', function () {
				tables.sessions.ajax.reload();
			} );
		}

		/* ---- Bookings DataTable ---- */
		if ( $( '#wpesBookingsTable' ).length ) {
			tables.bookings = $( '#wpesBookingsTable' ).DataTable( {
				processing: true,
				serverSide: true,
				ajax: {
					url: WPES_Admin.ajax_url,
					type: 'POST',
					data: function ( d ) {
						d.action = 'wpes_datatable_bookings';
						d.nonce = WPES_Admin.nonce;
						d.class_filter = $( '#wpesBookingClassFilter' ).val();
						d.tab_filter = $( '#wpesBookingTabFilter' ).val();
					}
				},
				pageLength: 25,
				order: [],
				dom: 'Bfrtip',
				buttons: dtButtons(),
				language: { search: '', processing: '<div class="spinner-border spinner-border-sm"></div> Loading…' }
			} );

			$( '#wpesBookingClassFilter' ).on( 'change', function () {
				tables.bookings.ajax.reload();
			} );

			$( '#wpesBookingsTabs .nav-link' ).on( 'click', function () {
				$( '#wpesBookingsTabs .nav-link' ).removeClass( 'active' );
				$( this ).addClass( 'active' );
				$( '#wpesBookingTabFilter' ).val( $( this ).data( 'tab' ) );
				tables.bookings.ajax.reload();
			} );
		}

		/* ---- Waitlist DataTable ---- */
		if ( $( '#wpesWaitlistTable' ).length ) {
			tables.waitlist = $( '#wpesWaitlistTable' ).DataTable( {
				processing: true,
				serverSide: true,
				ajax: {
					url: WPES_Admin.ajax_url,
					type: 'POST',
					data: function ( d ) {
						d.action = 'wpes_datatable_waitlist';
						d.nonce = WPES_Admin.nonce;
						d.form_filter = $( '#wpesWaitlistFormFilter' ).val();
					}
				},
				pageLength: 25,
				order: [[ 5, 'desc' ]],
				dom: 'Bfrtip',
				buttons: dtButtons(),
				columnDefs: [
					{ orderable: false, targets: [ 0, 4, 6, 7 ] },
					{ searchable: false, targets: [ 0, 7 ] }
				],
				language: { search: '', processing: '<div class="spinner-border spinner-border-sm"></div> Loading…' },
				drawCallback: function () {
					$( '#wpesWaitlistCheckAll' ).prop( 'checked', false );
				}
			} );

			$( '#wpesWaitlistFormFilter' ).on( 'change', function () {
				tables.waitlist.ajax.reload();
			} );

			$( '#wpesWaitlistCheckAll' ).on( 'change', function () {
				$( '.wpes-waitlist-check' ).prop( 'checked', $( this ).is( ':checked' ) );
			} );

			function openWaitlistMessageModal( ids, allFiltered, label ) {
				$( '#wpesWaitlistMessageIds' ).val( ( ids || [] ).join( ',' ) );
				$( '#wpesWaitlistMessageAllFiltered' ).val( allFiltered ? '1' : '0' );
				$( '#wpesWaitlistMessageRecipients' ).text( label || '' );
				$( '#wpesWaitlistMessageSubject' ).val( '' );
				$( '#wpesWaitlistMessageBody' ).val( '' );
				new bootstrap.Modal( document.getElementById( 'wpesWaitlistMessageModal' ) ).show();
			}

			$( document ).on( 'click', '.wpes-waitlist-message', function () {
				var id = $( this ).data( 'id' );
				var email = $( this ).data( 'email' ) || '';
				var name = $( this ).data( 'name' ) || '';
				var label = name ? ( name + ' <' + email + '>' ) : email;
				openWaitlistMessageModal( [ id ], false, label );
			} );

			$( '#wpesWaitlistBulkMessage' ).on( 'click', function () {
				var ids = $( '.wpes-waitlist-check:checked' ).map( function () {
					return $( this ).val();
				} ).get();
				if ( ! ids.length ) {
					notyf.error( WPES_Admin.i18n.selectRecipients );
					return;
				}
				openWaitlistMessageModal( ids, false, ids.length + ' selected' );
			} );

			$( '#wpesWaitlistBulkFiltered' ).on( 'click', function () {
				if ( ! window.confirm( WPES_Admin.i18n.confirmBulkSend ) ) {
					return;
				}
				openWaitlistMessageModal( [], true, 'All filtered entries' );
			} );

			$( document ).on( 'click', '.wpes-waitlist-delete', function () {
				var $btn = $( this );
				var id = $btn.data( 'id' );
				var name = $btn.data( 'name' ) || '';
				var label = name ? '"' + name + '" (ID ' + id + ')' : 'this waitlist entry (ID ' + id + ')';
				if ( ! window.confirm( WPES_Admin.i18n.confirmDeleteWaitlist + '\n\n' + label ) ) {
					return;
				}
				$btn.prop( 'disabled', true );
				post( 'wpes_waitlist_delete', { id: id } ).done( function ( res ) {
					if ( res.success ) {
						notyf.success( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.deleted );
						tables.waitlist.ajax.reload( null, false );
					} else {
						notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
						$btn.prop( 'disabled', false );
					}
				} ).fail( function () {
					notyf.error( WPES_Admin.i18n.error );
					$btn.prop( 'disabled', false );
				} );
			} );

			$( document ).on( 'click', '.wpes-waitlist-view', function () {
				var b64 = $( this ).attr( 'data-fields-b64' ) || '';
				var raw = '';
				try {
					raw = b64 ? atob( b64 ) : '[]';
				} catch ( e ) {
					raw = '[]';
				}
				var fields;
				try {
					fields = JSON.parse( raw );
				} catch ( e ) {
					fields = [];
				}
				var html = '<dl class="row mb-0">';
				if ( Array.isArray( fields ) || ( fields && typeof fields === 'object' ) ) {
					$.each( fields, function ( key, field ) {
						var title = ( field && field.title ) ? field.title : ( ( field && field.id ) ? field.id : key );
						var value = field && typeof field.value !== 'undefined' ? field.value : '';
						if ( Array.isArray( value ) ) {
							value = value.join( ', ' );
						}
						html += '<dt class="col-sm-4">' + $( '<div>' ).text( title ).html() + '</dt>';
						html += '<dd class="col-sm-8">' + $( '<div>' ).text( String( value ) ).html() + '</dd>';
					} );
				}
				html += '</dl>';
				$( '#wpesWaitlistDetailsBody' ).html( html );
				new bootstrap.Modal( document.getElementById( 'wpesWaitlistDetailsModal' ) ).show();
			} );

			$( '#wpesWaitlistSendBtn' ).on( 'click', function () {
				var $btn = $( this );
				var idsRaw = $( '#wpesWaitlistMessageIds' ).val();
				var ids = idsRaw ? idsRaw.split( ',' ).filter( Boolean ) : [];
				var allFiltered = $( '#wpesWaitlistMessageAllFiltered' ).val() === '1';
				var subject = $( '#wpesWaitlistMessageSubject' ).val();
				var message = $( '#wpesWaitlistMessageBody' ).val();

				if ( ! subject || ! $.trim( message ) ) {
					notyf.error( WPES_Admin.i18n.required );
					return;
				}
				if ( ! allFiltered && ! ids.length ) {
					notyf.error( WPES_Admin.i18n.selectRecipients );
					return;
				}

				$btn.prop( 'disabled', true );
				post( 'wpes_waitlist_send_message', {
					ids: ids,
					all_filtered: allFiltered ? 1 : 0,
					subject: subject,
					message: message,
					search: tables.waitlist.search(),
					form_filter: $( '#wpesWaitlistFormFilter' ).val()
				} ).done( function ( res ) {
					if ( res.success ) {
						notyf.success( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.messageSent );
						bootstrap.Modal.getInstance( document.getElementById( 'wpesWaitlistMessageModal' ) ).hide();
					} else {
						notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
					}
				} ).fail( function () {
					notyf.error( WPES_Admin.i18n.error );
				} ).always( function () {
					$btn.prop( 'disabled', false );
				} );
			} );
		}

		/* ---- Classes CRUD ---- */
		$( '#wpesAddClassBtn' ).on( 'click', function () {
			$( '#wpesClassForm' )[0].reset();
			$( '#wpesClassId' ).val( 0 );
			setEditorContent( 'wpesClassDescription', '' );
		} );

		$( document ).on( 'click', '.wpes-edit-class', function () {
			var $btn = $( this );
			$( '#wpesClassId' ).val( $btn.data( 'id' ) );
			$( '#wpesClassName' ).val( $btn.data( 'name' ) );
			setEditorContent( 'wpesClassDescription', $btn.data( 'description' ) );
			$( '#wpesClassStatus' ).val( $btn.data( 'status' ) );
			new bootstrap.Modal( document.getElementById( 'wpesClassModal' ) ).show();
		} );

		$( '#wpesClassForm' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var data = serializeObject( $( this ) );
			data.description = getEditorContent( 'wpesClassDescription' );
			if ( ! data.name ) {
				notyf.error( WPES_Admin.i18n.required );
				return;
			}
			post( 'wpes_save_class', data ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.saved );
					bootstrap.Modal.getInstance( document.getElementById( 'wpesClassModal' ) ).hide();
					if ( tables.classes ) tables.classes.ajax.reload();
				} else {
					notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
				}
			} );
		} );

		$( document ).on( 'click', '.wpes-archive-class', function () {
			if ( ! confirm( WPES_Admin.i18n.confirm_archive ) ) return;
			post( 'wpes_archive_class', { id: $( this ).data( 'id' ) } ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.saved );
					if ( tables.classes ) tables.classes.ajax.reload();
				} else {
					notyf.error( WPES_Admin.i18n.error );
				}
			} );
		} );

		$( document ).on( 'click', '.wpes-delete-class', function () {
			if ( ! confirm( WPES_Admin.i18n.confirm_delete ) ) return;
			post( 'wpes_delete_class', { id: $( this ).data( 'id' ) } ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.saved );
					if ( tables.classes ) tables.classes.ajax.reload();
				} else {
					notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
				}
			} );
		} );

		/* ---- Sessions CRUD ---- */
		$( '#wpesRepeatSelect' ).on( 'change', function () {
			$( '#wpesRepeatCountWrap' ).toggle( 'none' !== $( this ).val() );
		} );

		$( '#wpesAddSessionBtn' ).on( 'click', function () {
			$( '#wpesSessionForm' )[0].reset();
			$( '#wpesSessionId' ).val( 0 );
			setEditorContent( 'wpesSessionDescription', '' );
			$( '#wpesSessionModalTitle' ).text( WPES_Admin.i18n.addSession );
			$( '#wpesSessionSubmitBtn' ).text( 'Create Session(s)' );
			$( '.wpes-session-recurrence' ).show();
			$( '.wpes-session-datetime input' ).prop( 'required', true );
		} );

		$( document ).on( 'click', '.wpes-edit-session', function () {
			var $btn = $( this );
			var id = $btn.data( 'id' );
			$( '#wpesSessionForm' )[0].reset();
			$( '#wpesSessionId' ).val( id );
			$( '#wpesSessionTitle' ).val( $btn.data( 'title' ) );
			$( '#wpesSessionLevel' ).val( $btn.data( 'level' ) );
			$( '#wpesSessionMax' ).val( $btn.data( 'max' ) );
			$( '#wpesSessionLink' ).val( $btn.data( 'link' ) );
			$( '#wpesSessionModalTitle' ).text( WPES_Admin.i18n.editSession );
			$( '#wpesSessionSubmitBtn' ).text( 'Update Session' );
			$( '.wpes-session-recurrence' ).hide();
			$( '.wpes-session-datetime input' ).prop( 'required', true );
			new bootstrap.Modal( document.getElementById( 'wpesSessionModal' ) ).show();
			post( 'wpes_get_session', { id: id } ).done( function ( res ) {
				if ( res.success && res.data.session ) {
					var s = res.data.session;
					setEditorContent( 'wpesSessionDescription', s.description || '' );
					if ( s.class_id ) {
						$( '#wpesSessionClassId' ).val( String( s.class_id ) );
					}
					if ( s.local_date ) {
						$( '#wpesSessionDate' ).val( s.local_date );
					}
					if ( s.local_start ) {
						$( '#wpesSessionStart' ).val( s.local_start );
					}
					if ( s.local_end ) {
						$( '#wpesSessionEnd' ).val( s.local_end );
					}
					if ( s.local_timezone ) {
						$( '#wpesSessionTimezone' ).val( s.local_timezone );
					}
				}
			} );
		} );

		$( '#wpesSessionForm' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var data = serializeObject( $( this ) );
			data.description = getEditorContent( 'wpesSessionDescription' );
			var id = parseInt( data.id, 10 );
			var action = ( id && id !== 0 ) ? 'wpes_update_session' : 'wpes_save_session';

			if ( ! data.date || ! data.start_time || ! data.end_time ) {
				notyf.error( WPES_Admin.i18n.required );
				return;
			}
			if ( 'wpes_save_session' === action && ! data.class_id ) {
				notyf.error( WPES_Admin.i18n.required );
				return;
			}

			var $btn = $( '#wpesSessionSubmitBtn' );
			$btn.prop( 'disabled', true ).addClass( 'disabled' );

			post( action, data ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.saved );
					bootstrap.Modal.getInstance( document.getElementById( 'wpesSessionModal' ) ).hide();
					if ( tables.sessions ) tables.sessions.ajax.reload();
				} else {
					notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
				}
			} ).always( function () {
				$btn.prop( 'disabled', false ).removeClass( 'disabled' );
			} );
		} );

		$( '#wpesBulkArchiveBtn' ).on( 'click', function () {
			var ids = [];
			$( '.wpes-session-check:checked' ).each( function () {
				ids.push( $( this ).val() );
			} );
			if ( ! ids.length ) {
				notyf.error( 'Select at least one session.' );
				return;
			}
			if ( ! confirm( WPES_Admin.i18n.confirm_bulk_archive ) ) return;
			var $btn = $( this );
			$btn.prop( 'disabled', true );
			post( 'wpes_bulk_archive_sessions', { ids: ids } ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.saved );
					if ( tables.sessions ) tables.sessions.ajax.reload();
				} else {
					notyf.error( WPES_Admin.i18n.error );
				}
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );

		$( document ).on( 'click', '.wpes-enroll-btn', function () {
			$( '#wpesEnrollSessionId' ).val( $( this ).data( 'id' ) );
			$( '#wpesEnrollEmail' ).val( '' );
			new bootstrap.Modal( document.getElementById( 'wpesEnrollModal' ) ).show();
		} );

		$( '#wpesEnrollForm' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var email = $.trim( $( '#wpesEnrollEmail' ).val() );
			if ( ! email ) {
				notyf.error( WPES_Admin.i18n.required );
				return;
			}
			post( 'wpes_manual_enroll', { session_id: $( '#wpesEnrollSessionId' ).val(), email: email } ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.enrolled );
					bootstrap.Modal.getInstance( document.getElementById( 'wpesEnrollModal' ) ).hide();
					if ( tables.sessions ) tables.sessions.ajax.reload();
				} else {
					notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
				}
			} );
		} );

		$( document ).on( 'click', '.wpes-send-link', function () {
			var $btn = $( this );
			var sendType = $btn.data( 'type' );
			var origText = $btn.text();
			if ( 'resend' === sendType && ! confirm( WPES_Admin.i18n.confirm_resend ) ) return;

			$btn.prop( 'disabled', true ).html( '<span class="spinner-border spinner-border-sm"></span>' );
			post( 'wpes_send_meeting_link', { session_id: $btn.data( 'id' ), send_type: sendType } )
				.done( function ( res ) {
					if ( res.success ) {
						notyf.success( res.data.message );
					} else {
						notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
					}
				} )
				.always( function () {
					$btn.prop( 'disabled', false ).text( origText );
				} );
		} );

		$( document ).on( 'click', '.wpes-view-attendees', function () {
			var sessionId = $( this ).data( 'id' );
			var $body = $( '#wpesAttendeesModalBody' );
			$body.html( '<div class="text-center py-4"><div class="spinner-border"></div></div>' );
			new bootstrap.Modal( document.getElementById( 'wpesAttendeesModal' ) ).show();
			post( 'wpes_session_attendees', { session_id: sessionId } ).done( function ( res ) {
				$body.html( res.success ? res.data.html : '<div class="alert alert-danger">' + WPES_Admin.i18n.error + '</div>' );
			} );
		} );

		/* ---- Settings ---- */
		$( '#wpesSettingsForm' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var filters = {};
			$( this ).find( '[name^="filters["]' ).each( function () {
				var match = $( this ).attr( 'name' ).match( /^filters\[([^\]]+)\]$/ );
				if ( match ) {
					filters[ match[1] ] = $( this ).is( ':checked' ) ? 1 : 0;
				}
			} );
			var $btn = $( '#wpesSettingsSaveBtn' );
			$btn.prop( 'disabled', true );
			post( 'wpes_save_settings', { filters: filters } ).done( function ( res ) {
				if ( res.success ) {
					notyf.success( WPES_Admin.i18n.saved );
				} else {
					notyf.error( res.data && res.data.message ? res.data.message : WPES_Admin.i18n.error );
				}
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );

	} );
} )( jQuery );
