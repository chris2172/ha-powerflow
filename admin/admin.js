/**
 * admin/admin.js
 *
 * Handles all admin-page interactivity:
 *   - Collapsible panels
 *   - Feature-toggle show/hide of optional panels
 *   - WordPress media uploader for background image
 *   - Colour picker reset buttons
 *
 * Enqueued only on the HA PowerFlow settings page.
 * haPfAdmin (ajaxUrl, copyImageNonce) is provided via wp_localize_script().
 */

/* global haPfAdmin, wp */

( function ( $, haPfAdmin ) {
    'use strict';

    // -------------------------------------------------------
    // Collapsible panels
    // -------------------------------------------------------
    document.querySelectorAll( '.ha-pf-panel-header' ).forEach( function ( header ) {
        header.addEventListener( 'click', function () {
            const panel = this.closest( '.ha-pf-panel' );
            if ( ! panel ) return;

            // Don't toggle panels that are hidden by the feature-toggle logic
            if ( panel.style.display === 'none' ) return;

            panel.classList.toggle( 'open' );
        } );
    } );

    // -------------------------------------------------------
    // Feature toggles — show / hide optional panels
    // -------------------------------------------------------
    function syncPanelVisibility( checkbox ) {
        const panelId = checkbox.dataset.panel;
        const panel   = document.getElementById( panelId );
        if ( ! panel ) return;

        if ( checkbox.checked ) {
            panel.style.display = '';
            panel.classList.add( 'open' );
        } else {
            panel.style.display = 'none';
            panel.classList.remove( 'open' );
        }
    }

    document.querySelectorAll( '.ha-pf-feature-toggle' ).forEach( function ( cb ) {
        // Apply on load
        syncPanelVisibility( cb );

        // Apply on change
        cb.addEventListener( 'change', function () {
            syncPanelVisibility( this );
        } );
    } );

    // -------------------------------------------------------
    // Colour reset buttons
    // -------------------------------------------------------
    document.querySelectorAll( '.ha-pf-colour-reset' ).forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            const input = document.getElementById( this.dataset.target );
            if ( input ) {
                input.value = this.dataset.default;
            }
        } );
    } );

    // -------------------------------------------------------
    // WordPress media uploader for background image
    // -------------------------------------------------------
    var mediaFrame;

    $( '#ha-pf-upload-btn' ).on( 'click', function ( e ) {
        e.preventDefault();

        if ( mediaFrame ) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media( {
            title:    'Select or Upload Background Image',
            button:   { text: 'Use this image' },
            library:  { type: 'image' },
            multiple: false,
        } );

        mediaFrame.on( 'select', function () {
            const attachment = mediaFrame.state().get( 'selection' ).first().toJSON();

            // Copy image to plugin's upload directory via AJAX
            $.post( haPfAdmin.ajaxUrl, {
                action:        'ha_pf_copy_image',
                nonce:         haPfAdmin.copyImageNonce,
                attachment_id: attachment.id,
            }, function ( response ) {
                if ( response.success ) {
                    var newUrl = response.data.url;
                    $( '#ha_pf_image_url_field' ).val( newUrl );
                    $( '#ha-pf-image-preview' )
                        .attr( 'src', newUrl )
                        .show();
                } else {
                    alert( 'Image copy failed: ' + ( response.data || 'Unknown error' ) );
                }
            } );
        } );

        mediaFrame.open();
    } );

} )( jQuery, haPfAdmin );
