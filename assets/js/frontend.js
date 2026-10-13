( function () {
	'use strict';

	var config = window.cqmfwCartQuota || {};
	var refreshTimer = null;
	var cartUpdateTimer = null;
	var previewTimer = null;
	var previewRequest = null;
	var quantityChangeSequence = 0;
	var lastStoreSignature = '';

	function getIndicators() {
		return document.querySelectorAll( '[data-cqmfw-progress]' );
	}

	function updateIndicators( status ) {
		if ( ! status || ! status.enabled ) {
			return;
		}

		if ( status.cart_items ) {
			config.cartItems = status.cart_items;
		}

		getIndicators().forEach( function ( indicator ) {
			var bar = indicator.querySelector( '[data-cqmfw-bar]' );
			var progressbar = indicator.querySelector( '[role="progressbar"]' );

			indicator.classList.toggle( 'is-reached', Boolean( status.reached ) );
			indicator.classList.toggle( 'is-short', ! status.reached );
			indicator.removeAttribute( 'aria-busy' );

			indicator.querySelector( '[data-cqmfw-current]' ).textContent = status.current_formatted;
			indicator.querySelector( '[data-cqmfw-minimum]' ).textContent = status.minimum_formatted;
			indicator.querySelector( '[data-cqmfw-message]' ).textContent = status.message;

			if ( bar ) {
				bar.style.width = Math.max( 0, Math.min( 100, Number( status.percent ) || 0 ) ) + '%';
			}

			if ( progressbar ) {
				progressbar.setAttribute( 'aria-valuemax', status.minimum );
				progressbar.setAttribute( 'aria-valuenow', Math.min( status.current, status.minimum ) );
			}
		} );
	}

	function markIndicatorsUpdating() {
		getIndicators().forEach( function ( indicator ) {
			indicator.setAttribute( 'aria-busy', 'true' );
			indicator.querySelector( '[data-cqmfw-message]' ).textContent = config.updatingMessage || 'Updating cart weight…';
		} );
	}

	function formatWeight( weight ) {
		return Number( weight ).toLocaleString( undefined, { maximumFractionDigits: 3 } );
	}

	function buildInstantStatus( quantityInput ) {
		var nameMatch = quantityInput.name.match( /^cart\[([^\]]+)\]\[qty\]$/ );
		var quantity = Number( quantityInput.value );
		var minimum = Number( config.minimum );
		var current = 0;
		var remaining;
		var reached;
		var currentFormatted;
		var minimumFormatted;
		var message;

		if ( ! nameMatch || ! config.cartItems || ! config.cartItems[ nameMatch[ 1 ] ] || ! Number.isFinite( quantity ) || quantity < 0 || ! Number.isFinite( minimum ) ) {
			return null;
		}

		config.cartItems[ nameMatch[ 1 ] ].quantity = quantity;
		Object.keys( config.cartItems ).forEach( function ( cartItemKey ) {
			var item = config.cartItems[ cartItemKey ];
			current += Number( item.unitWeight ) * Number( item.quantity );
		} );

		remaining = Math.max( 0, minimum - current );
		reached = remaining <= 0.000001;
		currentFormatted = formatWeight( current );
		minimumFormatted = formatWeight( minimum );
		message = reached
			? ( config.reachedMessage || 'Minimum reached — your cart is %1$s kg of the required %2$s kg.' )
				.replace( '%1$s', currentFormatted )
				.replace( '%2$s', minimumFormatted )
			: ( config.remainingMessage || 'Add %1$s kg more to reach the %2$s kg checkout minimum.' )
				.replace( '%1$s', formatWeight( remaining ) )
				.replace( '%2$s', minimumFormatted );

		return {
			enabled: true,
			reached: reached,
			current: current,
			minimum: minimum,
			percent: minimum > 0 ? Math.min( 100, ( current / minimum ) * 100 ) : 100,
			current_formatted: currentFormatted,
			minimum_formatted: minimumFormatted,
			message: message
		};
	}

	function scheduleCartUpdate( event ) {
		var quantityInput = event.target.closest( '.woocommerce-cart-form .cart_item input.qty' );
		var sequence;
		var instantStatus;
		if ( ! quantityInput ) {
			return;
		}

		sequence = ++quantityChangeSequence;
		markIndicatorsUpdating();
		instantStatus = buildInstantStatus( quantityInput );
		if ( instantStatus ) {
			updateIndicators( instantStatus );
		}
		scheduleWeightPreview( quantityInput, sequence );
		window.clearTimeout( cartUpdateTimer );
		cartUpdateTimer = window.setTimeout( function () {
			submitCartUpdate( quantityInput, sequence );
		}, 450 );
	}

	function submitCartUpdate( quantityInput, sequence ) {
		var form;
		var updateButton;

		if ( sequence !== quantityChangeSequence ) {
			return;
		}

		form = quantityInput.closest( '.woocommerce-cart-form' );
		updateButton = form ? form.querySelector( '[name="update_cart"]' ) : null;

		if ( updateButton ) {
			// Follow WooCommerce's native submit-button path so its nonce and
			// quantity-update handling remain authoritative.
			updateButton.disabled = false;
			updateButton.click();
		}
	}

	function scheduleWeightPreview( quantityInput, sequence ) {
		var nameMatch = quantityInput.name.match( /^cart\[([^\]]+)\]\[qty\]$/ );
		var quantity = Number( quantityInput.value );

		if ( ! config.previewEndpoint || ! nameMatch || ! Number.isFinite( quantity ) || quantity < 0 ) {
			return false;
		}

		window.clearTimeout( previewTimer );
		previewTimer = window.setTimeout( function () {
			var previewUrl = new window.URL( config.previewEndpoint, window.location.origin );

			if ( previewRequest ) {
				previewRequest.abort();
			}

			previewUrl.searchParams.set( 'cart_item_key', nameMatch[ 1 ] );
			previewUrl.searchParams.set( 'quantity', quantity );

			var requestController = new window.AbortController();
			previewRequest = requestController;
			window.fetch( previewUrl.toString(), {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
				cache: 'no-store',
				signal: requestController.signal
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Unable to preview minimum cart weight.' );
				}

				return response.json();
			} ).then( function ( status ) {
				if ( sequence !== quantityChangeSequence ) {
					return;
				}

				updateIndicators( status );
			} ).catch( function () {
				// The scheduled full cart update remains the fallback on failure.
			} ).finally( function () {
				if ( previewRequest === requestController ) {
					previewRequest = null;
				}
			} );
		}, 120 );

		return true;
	}

	function readBlocksStatus() {
		if ( ! window.wp || ! window.wp.data || ! config.namespace ) {
			return null;
		}

		try {
			var cartStore = window.wp.data.select( 'wc/store/cart' );
			var cartData = cartStore && cartStore.getCartData ? cartStore.getCartData() : null;

			return cartData && cartData.extensions ? cartData.extensions[ config.namespace ] : null;
		} catch ( error ) {
			return null;
		}
	}

	function fetchStatus() {
		var blocksStatus = readBlocksStatus();
		if ( blocksStatus ) {
			updateIndicators( blocksStatus );
			return;
		}

		if ( ! config.endpoint || ! getIndicators().length ) {
			return;
		}

		window.fetch( config.endpoint, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
			cache: 'no-store'
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Unable to refresh minimum cart weight.' );
			}

			return response.json();
		} ).then( updateIndicators ).catch( function () {
			// The server-side restriction remains authoritative if a visual refresh fails.
		} );
	}

	function scheduleRefresh() {
		window.clearTimeout( refreshTimer );
		refreshTimer = window.setTimeout( fetchStatus, 120 );
	}

	function subscribeToBlocksCart() {
		if ( ! window.wp || ! window.wp.data || ! window.wp.data.subscribe ) {
			return;
		}

		window.wp.data.subscribe( function () {
			var status = readBlocksStatus();
			if ( ! status ) {
				return;
			}

			var signature = [ status.current, status.minimum, status.reached, status.message ].join( '|' );
			if ( signature !== lastStoreSignature ) {
				lastStoreSignature = signature;
				updateIndicators( status );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		subscribeToBlocksCart();
		document.addEventListener( 'input', scheduleCartUpdate );
		document.addEventListener( 'change', scheduleCartUpdate );

		[ 'wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart' ].forEach( function ( eventName ) {
			document.body.addEventListener( eventName, scheduleRefresh );
		} );

		if ( window.jQuery ) {
			window.jQuery( document.body ).on(
				'added_to_cart removed_from_cart updated_cart_totals updated_checkout',
				scheduleRefresh
			);
		}
	} );
}() );

