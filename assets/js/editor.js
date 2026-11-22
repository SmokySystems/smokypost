/* SmokyPost panel for the block editor. Plain ES5, no build step. */
( function ( wp, data ) {
	if ( ! wp || ! wp.plugins || ! data ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var hooks = wp.hooks;
	var C = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var PrePublish = ( wp.editor && wp.editor.PluginPrePublishPanel ) || ( wp.editPost && wp.editPost.PluginPrePublishPanel );
	var hasInstagram = data.targets.some( function ( t ) { return t.platform === 'instagram'; } );

	// The image that goes with the share: featured image, else the first image
	// in the content, else the default image from the settings.
	// Images in the content being edited, then those found by the server in the
	// rendered content (page builders, shortcodes).
	function contentImages( content ) {
		var re = /<img[^>]+src=["']([^"']+)["']/gi;
		var out = [];
		var known = data.contentImages || [];
		var m;
		while ( ( m = re.exec( content || '' ) ) && out.length < 12 ) {
			var url = m[ 1 ].replace( /&amp;/g, '&' );
			if ( /^https?:/i.test( url ) && out.indexOf( url ) === -1 ) {
				out.push( url );
			}
		}
		known.forEach( function ( url ) {
			if ( out.length < 12 && out.indexOf( url ) === -1 ) {
				out.push( url );
			}
		} );
		return out;
	}

	// The image that goes with the share: the one chosen for sharing, else the
	// featured image, else the first image in the content, else the default image.
	function useImage() {
		var key = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var meta = editor.getEditedPostAttribute( 'meta' ) || {};
			if ( meta._smokypost_image ) {
				return 'custom|' + meta._smokypost_image;
			}
			var featured = editor.getEditedPostAttribute( 'featured_media' );
			if ( featured > 0 ) {
				var media = select( 'core' ).getMedia( featured );
				return 'featured|' + ( media && media.source_url ? media.source_url : ( data.image.source === 'featured' ? data.image.url : '' ) );
			}
			var first = contentImages( editor.getEditedPostAttribute( 'content' ) )[ 0 ];
			if ( first ) {
				return 'content|' + first;
			}
			return data.defaultImage ? 'default|' + data.defaultImage : 'none|';
		}, [] );
		var i = key.indexOf( '|' );
		return { source: key.slice( 0, i ), url: key.slice( i + 1 ) };
	}

	function useHasImage() {
		return useImage().source !== 'none';
	}

	function ImagePreview() {
		var image = useImage();
		var L = data.imageLabels;
		var images = useSelect( function ( select ) {
			return contentImages( select( 'core/editor' ).getEditedPostAttribute( 'content' ) ).join( '\n' );
		}, [] );
		var list = images ? images.split( '\n' ) : [];
		var editPost = useDispatch( 'core/editor' ).editPost;
		var MediaUpload = wp.blockEditor && wp.blockEditor.MediaUpload;
		function set( url ) {
			editPost( { meta: { _smokypost_image: url } } );
		}
		var parts = [ el( 'p', { key: 'h', className: 'smokypost-heading' }, L.title ) ];
		if ( image.url ) {
			parts.push( el( 'img', { key: 'img', src: image.url, alt: '', onError: function ( e ) { e.target.style.display = 'none'; } } ) );
		}
		if ( image.source !== 'none' ) {
			parts.push( el( 'p', { key: 'src', className: 'description' }, L[ image.source ] ) );
		}
		if ( list.length > 1 ) {
			parts.push( el( 'p', { key: 'in', className: 'description' }, L.inItem ) );
			parts.push( el( 'div', { key: 'thumbs', className: 'smokypost-thumbs' }, list.map( function ( url ) {
				return el( 'button', {
					key: url,
					type: 'button',
					title: L.pick,
					className: 'smokypost-thumb' + ( url === image.url ? ' is-selected' : '' ),
					onClick: function () { set( url ); }
				}, el( 'img', { src: url, alt: '', onError: function ( e ) { e.target.parentNode.style.display = 'none'; } } ) );
			} ) ) );
		}
		var actions = [];
		if ( MediaUpload ) {
			actions.push( el( MediaUpload, {
				key: 'media',
				title: L.media,
				allowedTypes: [ 'image' ],
				onSelect: function ( media ) { set( media && media.url ? media.url : '' ); },
				render: function ( o ) {
					return el( C.Button, { variant: 'secondary', onClick: o.open }, image.url ? L.change : L.choose );
				}
			} ) );
		}
		if ( image.source === 'custom' ) {
			actions.push( el( C.Button, { key: 'reset', variant: 'link', onClick: function () { set( '' ); } }, L.reset ) );
		}
		parts.push( el( 'div', { key: 'act', className: 'smokypost-image-actions' }, actions ) );
		return el( 'div', { className: 'smokypost-image' }, parts );
	}

	function useChosen() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var saved = meta._smokypost_platforms;
		return Array.isArray( saved ) && saved.length ? saved.filter( function ( p ) { return p !== data.none; } ) : data.defaults;
	}
	if ( ! Panel ) {
		return;
	}

	function StatusList( props ) {
		if ( ! props.lines || ! props.lines.length ) {
			return null;
		}
		return el( 'ul', { className: 'smokypost-status' }, props.lines.map( function ( line ) {
			var text = line.url ? el( 'a', { href: line.url, target: '_blank', rel: 'noopener' }, line.text ) : line.text;
			return el( 'li', { key: line.platform, className: 'is-' + line.status }, el( 'strong', null, line.label + ': ' ), text );
		} ) );
	}

	function SmokyPostPanel() {
		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				id: editor.getCurrentPostId(),
				status: editor.getEditedPostAttribute( 'status' ),
				savedStatus: editor.getCurrentPostAttribute( 'status' ),
				meta: editor.getEditedPostAttribute( 'meta' ) || {}
			};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var lastState = useState( data.last || [] );
		var resultState = useState( data.error || '' );
		var busyState = useState( false );
		var removeState = useState( data.remove || null );
		var confirmState = useState( false );
		var waitingState = useState( !! data.waiting );

		if ( ! data.connected || ! data.targets.length ) {
			return el( Panel, { name: 'smokypost', title: 'SmokyPost' },
				el( 'p', null, __( 'Connect your pages to share this item.', 'smokypost' ) ),
				el( 'a', { href: data.settingsUrl }, __( 'Open SmokyPost', 'smokypost' ) )
			);
		}

		var chosen = useChosen();
		var hasImage = useHasImage();

		function toggle( platform, on ) {
			var next = chosen.filter( function ( p ) { return p !== platform; } );
			if ( on ) {
				next.push( platform );
			}
			editPost( { meta: { _smokypost_platforms: next.length ? next : [ data.none ] } } );
		}

		// While the service sends, ask for the result every few seconds. Leaving the page does not stop it.
		function pollStatus( tries ) {
			if ( tries >= 40 ) {
				resultState[ 1 ]( __( 'Still sending. Check the History tab in a few minutes.', 'smokypost' ) );
				return;
			}
			window.setTimeout( function () {
				var body = new window.FormData();
				body.append( 'action', 'smokypost_share_status' );
				body.append( 'nonce', data.nonce );
				body.append( 'post_id', post.id );
				window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( r ) {
						if ( ! r.success ) {
							return;
						}
						lastState[ 1 ]( r.data.last );
						if ( r.data.finished ) {
							resultState[ 1 ]( r.data.message );
							removeState[ 1 ]( r.data.remove || null );
						} else {
							pollStatus( tries + 1 );
						}
					} )
					.catch( function () { pollStatus( tries + 1 ); } );
			}, 3000 );
		}

		function shareNow() {
			var body = new window.FormData();
			body.append( 'action', 'smokypost_share_now' );
			body.append( 'nonce', data.nonce );
			body.append( 'post_id', post.id );
			body.append( 'message', post.meta._smokypost_message || '' );
			body.append( 'image', post.meta._smokypost_image || '' );
			var shareAtSent = data.scheduling ? ( post.meta._smokypost_share_at || '' ) : '';
			body.append( 'share_at', shareAtSent );
			// Add-ons can send more fields with "Share now".
			var extra = hooks ? hooks.applyFilters( 'smokypost.shareNowFields', {}, post.meta ) : {};
			Object.keys( extra || {} ).forEach( function ( key ) { body.append( key, extra[ key ] ); } );
			chosen.forEach( function ( p ) { body.append( 'platforms[]', p ); } );
			busyState[ 1 ]( true );
			window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					resultState[ 1 ]( r.data && r.data.message ? r.data.message : '' );
					if ( r.data && r.data.last ) {
						lastState[ 1 ]( r.data.last );
					}
					if ( r.success && r.data && r.data.waiting ) {
						waitingState[ 1 ]( true );
					}
					if ( r.success && shareAtSent ) {
						editPost( { meta: { _smokypost_share_at: '' } } );
					}
					if ( hooks ) {
						hooks.doAction( 'smokypost.shareNowDone', r );
					}
					if ( r.success && r.data && false === r.data.finished ) {
						pollStatus( 0 );
					}
				} )
				.catch( function () { resultState[ 1 ]( __( 'SmokyPost could not be reached. Please try again in a few minutes.', 'smokypost' ) ); } )
				.finally( function () { busyState[ 1 ]( false ); } );
		}

		// Take back a share that still waits for its time.
		function cancelScheduled() {
			var body = new window.FormData();
			body.append( 'action', 'smokypost_share_cancel' );
			body.append( 'nonce', data.nonce );
			body.append( 'post_id', post.id );
			busyState[ 1 ]( true );
			window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					resultState[ 1 ]( r.data && r.data.message ? r.data.message : '' );
					if ( r.success ) {
						waitingState[ 1 ]( false );
						editPost( { meta: { _smokypost_share_at: '' } } );
						if ( r.data.last ) {
							lastState[ 1 ]( r.data.last );
						}
					}
				} )
				.catch( function () { resultState[ 1 ]( __( 'SmokyPost could not be reached. Please try again in a few minutes.', 'smokypost' ) ); } )
				.finally( function () { busyState[ 1 ]( false ); } );
		}

		// "Delete shared posts": takes the posts down without deleting the item.
		function unshare() {
			var body = new window.FormData();
			body.append( 'action', 'smokypost_unshare' );
			body.append( 'nonce', data.nonce );
			body.append( 'post_id', post.id );
			busyState[ 1 ]( true );
			resultState[ 1 ]( data.removeLabels.working );
			window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					resultState[ 1 ]( r.data && r.data.message ? r.data.message : '' );
					if ( r.data && r.data.last ) {
						lastState[ 1 ]( r.data.last );
					}
					if ( r.success ) {
						confirmState[ 1 ]( false );
						removeState[ 1 ]( null );
						if ( r.data && false === r.data.finished ) {
							pollStatus( 0 );
						}
					}
				} )
				.catch( function () { resultState[ 1 ]( __( 'SmokyPost could not be reached. Please try again in a few minutes.', 'smokypost' ) ); } )
				.finally( function () { busyState[ 1 ]( false ); } );
		}

		var published = post.savedStatus === 'publish';
		var children = [];
		if ( hasInstagram && ! hasImage ) {
			children.push( el( C.Notice, { key: 'noimage', status: 'warning', isDismissible: false, className: 'smokypost-warning' }, data.noImage ) );
		}
		children.push( el( ImagePreview, { key: 'image' } ) );
		children.push( el( 'p', { key: 'heading', className: 'smokypost-heading' }, published ? __( 'Share now to:', 'smokypost' ) : __( 'Share when published to:', 'smokypost' ) ) );
		data.targets.forEach( function ( t ) {
			var label = t.label + ': ' + t.name + ( t.expired ? ' (' + __( 'reconnect needed', 'smokypost' ) + ')' : '' );
			var disabled = t.platform === 'instagram' && ! hasImage;
			children.push( el( C.CheckboxControl, {
				key: t.platform,
				label: label,
				checked: chosen.indexOf( t.platform ) !== -1 && ! disabled,
				disabled: disabled,
				onChange: function ( on ) { toggle( t.platform, on ); }
			} ) );
		} );

		children.push( el( C.TextareaControl, {
			key: 'message',
			label: __( 'Custom message (optional)', 'smokypost' ),
			help: __( 'Leave empty to use your template.', 'smokypost' ),
			value: post.meta._smokypost_message || '',
			onChange: function ( value ) { editPost( { meta: { _smokypost_message: value } } ); }
		} ) );

		var L = data.labels;
		var shareAt = post.meta._smokypost_share_at || '';
		if ( data.scheduling ) {
			children.push( el( C.TextControl, {
				key: 'shareat',
				label: L.shareAt,
				help: ( shareAt && ! /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test( shareAt ) ) ? L.shareAtBad : L.shareAtHelp,
				placeholder: '2026-12-31 18:30',
				value: shareAt,
				onChange: function ( value ) { editPost( { meta: { _smokypost_share_at: value } } ); }
			} ) );
		}

		if ( ! published ) {
			children.push( el( 'p', { key: 'when', className: 'description' }, __( 'Shared to the ticked platforms when you publish.', 'smokypost' ) ) );
		} else {
			if ( data.alreadyAuto ) {
				children.push( el( 'p', { key: 'auto', className: 'description' }, __( 'Shared automatically when it was published.', 'smokypost' ) ) );
			}
			children.push( el( 'p', { key: 'noupdate', className: 'description' }, data.imageLabels.noUpdate ) );
			children.push( el( C.Button, { key: 'share', variant: 'secondary', isBusy: busyState[ 0 ], disabled: busyState[ 0 ] || ! chosen.length, onClick: shareNow }, shareAt && data.scheduling ? L.schedule : __( 'Share now', 'smokypost' ) ) );
		}
		if ( waitingState[ 0 ] ) {
			children.push( el( C.Button, { key: 'cancelsched', variant: 'secondary', isDestructive: true, disabled: busyState[ 0 ], onClick: cancelScheduled }, L.cancel ) );
		}
		if ( published && removeState[ 0 ] ) {
			var L2 = data.removeLabels;
			children.push( el( 'details', { key: 'delete', className: 'smokypost-confirm smokypost-unshare', open: confirmState[ 0 ] },
				el( 'summary', { className: 'components-button is-secondary', onClick: function ( e ) { e.preventDefault(); confirmState[ 1 ]( true ); } }, L2.open ),
				el( C.Notice, { status: 'warning', isDismissible: false, className: 'smokypost-warning' }, removeState[ 0 ].text ),
				el( 'div', { className: 'smokypost-image-actions' },
					el( C.Button, { variant: 'primary', isDestructive: true, isBusy: busyState[ 0 ], disabled: busyState[ 0 ], onClick: unshare }, L2.yes ),
					el( C.Button, { variant: 'link', onClick: function () { confirmState[ 1 ]( false ); } }, L2.cancel )
				)
			) );
		}
		if ( resultState[ 0 ] ) {
			children.push( el( 'p', { key: 'result', className: 'smokypost-result' }, resultState[ 0 ] ) );
		}
		children.push( el( StatusList, { key: 'status', lines: lastState[ 0 ] } ) );

		return el( Panel, { name: 'smokypost', title: 'SmokyPost', className: 'smokypost-panel' }, children );
	}

	// Shown in the "Are you ready to publish?" step when Instagram would be left out.
	function SmokyPostPrePublish() {
		var chosen = useChosen();
		var hasImage = useHasImage();
		if ( ! PrePublish || ! hasInstagram || hasImage || chosen.indexOf( 'instagram' ) === -1 ) {
			return null;
		}
		return el( PrePublish, { title: 'SmokyPost', initialOpen: true, className: 'smokypost-panel' },
			el( C.Notice, { status: 'warning', isDismissible: false, className: 'smokypost-warning' }, data.noImage )
		);
	}

	wp.plugins.registerPlugin( 'smokypost', { render: SmokyPostPanel, icon: 'share' } );
	wp.plugins.registerPlugin( 'smokypost-prepublish', { render: SmokyPostPrePublish, icon: 'share' } );
} )( window.wp, window.smokypostEditor );
