/* SmokyPost: "Share now" links and buttons; broken avatars. */
( function () {
	// Platform picture links can expire; hide a picture that does not load instead of showing a broken image.
	document.addEventListener( 'error', function ( event ) {
		var img = event.target;
		if ( img && img.classList && img.classList.contains( 'smokypost-avatar' ) ) {
			img.style.display = 'none';
		}
	}, true );
	// Pictures that already failed before this script loaded.
	function hideBroken() {
		document.querySelectorAll( 'img.smokypost-avatar' ).forEach( function ( img ) {
			if ( img.complete && 0 === img.naturalWidth ) {
				img.style.display = 'none';
			}
		} );
	}
	hideBroken();
	document.addEventListener( 'DOMContentLoaded', hideBroken );
	window.addEventListener( 'load', hideBroken );

	var cfg = window.smokypostAdmin || {};

	// Classic editor: choose the image that goes with the share.
	function setImage( wrap, url ) {
		wrap.querySelector( 'input[name="smokypost_image"]' ).value = url;
		var preview = wrap.querySelector( '.smokypost-image-preview' );
		if ( ! preview && url ) {
			preview = document.createElement( 'img' );
			preview.className = 'smokypost-avatar smokypost-image-preview';
			preview.alt = '';
			wrap.querySelector( '.smokypost-heading' ).after( preview );
		}
		if ( preview ) {
			preview.style.display = url ? '' : 'none';
			if ( url ) {
				preview.src = url;
			}
		}
		var note = preview && preview.nextElementSibling && preview.nextElementSibling.classList.contains( 'description' ) ? preview.nextElementSibling : null;
		if ( note ) {
			note.textContent = url ? wrap.getAttribute( 'data-custom' ) : '';
		}
		wrap.querySelectorAll( '.smokypost-thumb' ).forEach( function ( t ) {
			t.classList.toggle( 'is-selected', t.getAttribute( 'data-url' ) === url );
		} );
	}

	function handleImage( event ) {
		var t = event.target.closest ? event.target : null;
		var wrap = t ? t.closest( '.smokypost-image' ) : null;
		if ( ! wrap ) {
			return false;
		}
		var thumb = t.closest( '.smokypost-thumb' );
		if ( thumb ) {
			setImage( wrap, thumb.getAttribute( 'data-url' ) );
			return true;
		}
		if ( t.closest( '.smokypost-image-reset' ) ) {
			setImage( wrap, '' );
			return true;
		}
		if ( t.closest( '.smokypost-image-choose' ) && window.wp && window.wp.media ) {
			var frame = window.wp.media( {
				title: wrap.getAttribute( 'data-title' ),
				button: { text: wrap.getAttribute( 'data-button' ) },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				setImage( wrap, a.url );
			} );
			frame.open();
			return true;
		}
		return false;
	}

	document.addEventListener( 'click', function ( event ) {
		var cancel = event.target.closest ? event.target.closest( '.smokypost-confirm-cancel' ) : null;
		if ( cancel ) {
			cancel.closest( 'details' ).open = false;
			return;
		}
		if ( handleImage( event ) ) {
			return;
		}
		var cancelBtn = event.target.closest ? event.target.closest( '.smokypost-share-cancel' ) : null;
		if ( cancelBtn ) {
			event.preventDefault();
			var cout = cancelBtn.parentNode.querySelector( '.smokypost-share-result' );
			var cbody = new window.FormData();
			cbody.append( 'action', 'smokypost_share_cancel' );
			cbody.append( 'nonce', cancelBtn.getAttribute( 'data-nonce' ) );
			cbody.append( 'post_id', cancelBtn.getAttribute( 'data-post' ) );
			cancelBtn.setAttribute( 'aria-disabled', 'true' );
			window.fetch( cfg.ajaxUrl || window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: cbody } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( cout ) {
						cout.textContent = ' ' + ( r.data && r.data.message ? r.data.message : '' );
					}
					if ( r.success ) {
						cancelBtn.style.display = 'none';
						var sf = document.getElementById( 'smokypost_share_at' );
						if ( sf ) {
							sf.value = '';
						}
					}
				} )
				.finally( function () { cancelBtn.removeAttribute( 'aria-disabled' ); } );
			return;
		}
		var unshare = event.target.closest ? event.target.closest( '.smokypost-unshare-now' ) : null;
		if ( unshare ) {
			event.preventDefault();
			var uout = unshare.parentNode.querySelector( '.smokypost-share-result' );
			var ubody = new window.FormData();
			ubody.append( 'action', 'smokypost_unshare' );
			ubody.append( 'nonce', unshare.getAttribute( 'data-nonce' ) );
			ubody.append( 'post_id', unshare.getAttribute( 'data-post' ) );
			if ( uout ) {
				uout.textContent = ' ' + ( cfg.deleting || '…' );
			}
			unshare.disabled = true;
			window.fetch( cfg.ajaxUrl || window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: ubody } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( uout ) {
						uout.textContent = ' ' + ( r.data && r.data.message ? r.data.message : '' );
					}
					if ( r.success ) {
						var rowCancel = unshare.parentNode.querySelector( '.smokypost-confirm-cancel' );
						if ( rowCancel ) {
							rowCancel.style.display = 'none';
						}
						if ( false === r.data.finished ) {
							poll( unshare, uout, 0 );
						}
						if ( r.data.last ) {
							showLines( unshare, r.data.last );
						}
					} else {
						unshare.disabled = false;
					}
				} )
				.catch( function () { unshare.disabled = false; } );
			return;
		}
		var btn = event.target.closest ? event.target.closest( '.smokypost-share-now' ) : null;
		if ( ! btn ) {
			return;
		}
		event.preventDefault();
		var out = btn.parentNode.querySelector( '.smokypost-share-result' );
		var body = new window.FormData();
		body.append( 'action', 'smokypost_share_now' );
		body.append( 'nonce', btn.getAttribute( 'data-nonce' ) );
		body.append( 'post_id', btn.getAttribute( 'data-post' ) );
		var box = document.querySelector( '#smokypost.postbox' );
		if ( box ) {
			body.append( 'platforms[]', '' );
			box.querySelectorAll( 'input[name="smokypost_platforms[]"]:checked' ).forEach( function ( input ) {
				body.append( 'platforms[]', input.value );
			} );
			var image = box.querySelector( 'input[name="smokypost_image"]' );
			if ( image ) {
				body.append( 'image', image.value );
			}
			var message = document.getElementById( 'smokypost_message' );
			if ( message ) {
				body.append( 'message', message.value );
			}
			box.querySelectorAll( 'textarea[name^="smokypost_messages["]' ).forEach( function ( area ) {
				body.append( area.name.replace( 'smokypost_messages', 'messages' ), area.value );
			} );
			var shareAt = document.getElementById( 'smokypost_share_at' );
			if ( shareAt ) {
				body.append( 'share_at', shareAt.value );
			}
		}
		if ( out ) {
			out.textContent = ' ' + ( cfg.working || '…' );
		}
		btn.setAttribute( 'aria-disabled', 'true' );
		window.fetch( cfg.ajaxUrl || window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( r ) {
				if ( out ) {
					out.textContent = ' ' + ( r.data && r.data.message ? r.data.message : '' );
				}
				if ( r.success && r.data && false === r.data.finished ) {
					poll( btn, out, 0 );
				}
			} )
			.finally( function () { btn.removeAttribute( 'aria-disabled' ); } );
	} );

	// While the service sends, ask for the result every few seconds. Leaving the page does not stop it.
	function poll( btn, out, tries ) {
		if ( tries >= 40 ) {
			if ( out ) {
				out.textContent = ' ' + ( cfg.stillSending || '' );
			}
			return;
		}
		window.setTimeout( function () {
			var body = new window.FormData();
			body.append( 'action', 'smokypost_share_status' );
			body.append( 'nonce', btn.getAttribute( 'data-nonce' ) );
			body.append( 'post_id', btn.getAttribute( 'data-post' ) );
			window.fetch( cfg.ajaxUrl || window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( ! r.success ) {
						return;
					}
					if ( r.data.finished ) {
						if ( out ) {
							out.textContent = ' ' + r.data.message;
						}
						showLines( btn, r.data.last );
					} else {
						poll( btn, out, tries + 1 );
					}
				} )
				.catch( function () { poll( btn, out, tries + 1 ); } );
		}, 3000 );
	}

	// Classic editor box: show the final result per platform.
	function showLines( btn, lines ) {
		var list = document.querySelector( '#smokypost.postbox ul.smokypost-status' );
		if ( ! list || ! lines ) {
			return;
		}
		list.innerHTML = '';
		lines.forEach( function ( line ) {
			var li = document.createElement( 'li' );
			var strong = document.createElement( 'strong' );
			li.className = 'is-' + line.status;
			strong.textContent = line.label + ': ';
			li.appendChild( strong );
			if ( line.url ) {
				var a = document.createElement( 'a' );
				a.href = line.url;
				a.target = '_blank';
				a.rel = 'noopener';
				a.textContent = line.text;
				li.appendChild( a );
			} else {
				li.appendChild( document.createTextNode( line.text ) );
			}
			list.appendChild( li );
		} );
	}

} )();
