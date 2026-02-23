/**
 * admin/admin.js
 *
 * Handles all admin-page interactivity:
 *   - Collapsible panels
 *   - Feature-toggle show/hide panels + toggle card state
 *   - Colour picker live hex display
 *   - Colour reset buttons
 *   - WordPress media uploader for background image
 *   - Unsaved-changes dirty badge
 *
 * haPfAdmin (ajaxUrl, copyImageNonce) provided via wp_localize_script().
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
            if ( ! panel || panel.style.display === 'none' ) return;
            panel.classList.toggle( 'open' );
        } );
    } );

    // -------------------------------------------------------
    // Feature toggles — panels + card highlight
    // -------------------------------------------------------
    function syncToggle( checkbox ) {
        const panelId = checkbox.dataset.panel;
        const cardId  = checkbox.dataset.card;
        const panel   = panelId ? document.getElementById( panelId ) : null;
        const card    = cardId  ? document.getElementById( cardId  ) : null;

        if ( panel ) {
            if ( checkbox.checked ) {
                panel.style.display = '';
                panel.classList.add( 'open' );
            } else {
                panel.style.display = 'none';
                panel.classList.remove( 'open' );
            }
        }

        if ( card ) {
            card.classList.toggle( 'is-enabled', checkbox.checked );
        }
    }

    document.querySelectorAll( '.ha-pf-feature-toggle' ).forEach( function ( cb ) {
        syncToggle( cb );
        cb.addEventListener( 'change', function () {
            syncToggle( this );
            syncGridExport();
        } );
    } );

    // -------------------------------------------------------
    // Grid Export row — visible only when Solar or Battery is enabled
    // -------------------------------------------------------
    function syncGridExport() {
        const solarCb   = document.querySelector( '[name="ha_powerflow_enable_solar"][type="checkbox"]' );
        const batteryCb = document.querySelector( '[name="ha_powerflow_enable_battery"][type="checkbox"]' );
        const row       = document.getElementById( 'ha-pf-grid-export-row' );
        if ( ! row ) return;
        const show = ( solarCb && solarCb.checked ) || ( batteryCb && batteryCb.checked );
        row.style.display = show ? '' : 'none';
    }

    syncGridExport();

    // -------------------------------------------------------
    // Custom entity builder
    // -------------------------------------------------------
    ( function () {
        var list      = document.getElementById( 'ha-pf-custom-entities-list' );
        var jsonField = document.getElementById( 'ha-pf-custom-entities-json' );
        var addBtn    = document.getElementById( 'ha-pf-add-custom-entity' );
        var form      = document.getElementById( 'ha-pf-form' );

        if ( ! list || ! jsonField ) return;

        // Generate a slug id from label text, unique enough for our purposes
        function makeId( label ) {
            var base = 'custom_' + ( label || '' )
                .toLowerCase()
                .replace( /[^a-z0-9]+/g, '_' )
                .replace( /^_|_$/, '' )
                .substring( 0, 28 );
            return base || ( 'custom_' + Date.now() );
        }

        // Build and return one row <div> from a data object
        function buildRow( data ) {
            data = data || {};
            var row  = document.createElement( 'div' );
            row.className  = 'ha-pf-custom-entity-row';
            row.dataset.id = data.id || '';

            function inp( cls, type, ph, val, extra ) {
                var s = '<input type="' + type + '" class="' + cls + '" placeholder="' + ph + '" value="' +
                        String( val || '' ).replace( /"/g, '&quot;' ) + '"' + ( extra || '' ) + '>';
                return s;
            }

            row.innerHTML =
                inp( 'ce-label',     'text',   'Solar kWh',          data.label     || '' ) +
                inp( 'ce-entity-id', 'text',   'sensor.solar_energy', data.entity_id || '' ) +
                inp( 'ce-unit',      'text',   'kWh',                data.unit      || '', ' style="width:60px;"' ) +
                inp( 'ce-size',      'number', '14',                 data.size      || 14, ' style="width:52px;" min="6" max="72"' ) +
                inp( 'ce-rot',       'number', '0',                  data.rot       || 0,  ' style="width:56px;"' ) +
                inp( 'ce-x',         'number', '0',                  data.x         || 0,  ' style="width:70px;" min="0"' ) +
                inp( 'ce-y',         'number', '0',                  data.y         || 0,  ' style="width:70px;" min="0"' ) +
                '<label class="ha-pf-ce-visible"><input type="checkbox" class="ce-visible"' +
                    ( data.visible ? ' checked' : '' ) + '></label>' +
                '<button type="button" class="ha-pf-ce-delete button" aria-label="Remove row">' +
                    '<span class="dashicons dashicons-trash"></span></button>';

            // Delete button
            row.querySelector( '.ha-pf-ce-delete' ).addEventListener( 'click', function () {
                row.parentNode.removeChild( row );
                serialise();
                markDirty();
            } );

            // Any change marks dirty
            row.querySelectorAll( 'input' ).forEach( function ( el ) {
                el.addEventListener( 'change', markDirty );
                el.addEventListener( 'input',  markDirty );
            } );

            return row;
        }

        // Attach delete listeners to rows that were server-rendered
        list.querySelectorAll( '.ha-pf-custom-entity-row' ).forEach( function ( row ) {
            row.querySelector( '.ha-pf-ce-delete' ).addEventListener( 'click', function () {
                row.parentNode.removeChild( row );
                serialise();
                markDirty();
            } );
        } );

        // Add button
        if ( addBtn ) {
            addBtn.addEventListener( 'click', function () {
                var row = buildRow( {} );
                list.appendChild( row );
                row.querySelector( '.ce-label' ).focus();
                serialise();
                markDirty();
            } );
        }

        // Collect all rows into the hidden JSON field before the form submits
        function serialise() {
            var items = [];
            list.querySelectorAll( '.ha-pf-custom-entity-row' ).forEach( function ( row ) {
                var label = ( row.querySelector( '.ce-label'     ) || {} ).value || '';
                var id    = row.dataset.id || makeId( label );
                if ( ! id ) return;
                items.push( {
                    id:        id,
                    label:     label,
                    entity_id: ( row.querySelector( '.ce-entity-id' ) || {} ).value || '',
                    unit:      ( row.querySelector( '.ce-unit'      ) || {} ).value || '',
                    size:      parseInt( ( row.querySelector( '.ce-size' ) || {} ).value || '14', 10 ),
                    rot:       parseInt( ( row.querySelector( '.ce-rot' ) || {} ).value || '0', 10 ),
                    x:         parseInt( ( row.querySelector( '.ce-x'   ) || {} ).value || '0', 10 ),
                    y:         parseInt( ( row.querySelector( '.ce-y'   ) || {} ).value || '0', 10 ),
                    visible:   !! ( row.querySelector( '.ce-visible' ) || {} ).checked,
                } );
            } );
            jsonField.value = JSON.stringify( items );
        }

        // Serialise on submit (catches any last-second edits)
        if ( form ) {
            form.addEventListener( 'submit', function () {
                serialise();
            } );
        }

        // Serialise on any change inside the list
        list.addEventListener( 'change', serialise );
        list.addEventListener( 'input',  serialise );

    }() );

    // -------------------------------------------------------
    // Colour pickers — live hex label update
    // -------------------------------------------------------
    document.querySelectorAll( '.ha-pf-colour-input' ).forEach( function ( input ) {
        const hexEl = document.getElementById( input.id.replace( 'ha_pf_', 'ha-pf-hex-' ) );

        function updateHex() {
            if ( hexEl ) hexEl.textContent = input.value.toUpperCase();
        }

        input.addEventListener( 'input',  updateHex );
        input.addEventListener( 'change', updateHex );
    } );

    // -------------------------------------------------------
    // Colour reset buttons
    // -------------------------------------------------------
    document.querySelectorAll( '.ha-pf-colour-reset' ).forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            const input  = document.getElementById( this.dataset.target );
            const hexEl  = document.getElementById( this.dataset.hex );
            const colour = this.dataset.default;

            if ( input  ) input.value = colour;
            if ( hexEl  ) hexEl.textContent = colour.toUpperCase();
        } );
    } );

    // -------------------------------------------------------
    // Unsaved-changes dirty badge
    // -------------------------------------------------------
    var dirty  = false;
    var badge  = document.getElementById( 'ha-pf-dirty-badge' );
    var cfgNote = document.getElementById( 'ha-pf-config-note' );

    function markDirty() {
        if ( dirty ) return;
        dirty = true;
        if ( badge   ) badge.classList.add( 'visible' );
        if ( cfgNote ) cfgNote.style.display = 'none';
    }

    document.querySelectorAll( '#ha-pf-form input, #ha-pf-form select, #ha-pf-form textarea' )
        .forEach( function ( el ) {
            el.addEventListener( 'change', markDirty );
            el.addEventListener( 'input',  markDirty );
        } );

    document.getElementById( 'ha-pf-form' ) &&
        document.getElementById( 'ha-pf-form' ).addEventListener( 'submit', function () {
            dirty = false;
            if ( badge   ) badge.classList.remove( 'visible' );
            if ( cfgNote ) cfgNote.style.display = '';
        } );

    // -------------------------------------------------------
    // WordPress media uploader
    // -------------------------------------------------------
    var mediaFrame;

    $( '#ha-pf-upload-btn' ).on( 'click', function ( e ) {
        e.preventDefault();

        if ( mediaFrame ) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media( {
            title:   'Select or Upload Background Image',
            button:  { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false,
        } );

        mediaFrame.on( 'select', function () {
            var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();

            $.post( haPfAdmin.ajaxUrl, {
                action:        'ha_pf_copy_image',
                nonce:         haPfAdmin.copyImageNonce,
                attachment_id: attachment.id,
            }, function ( response ) {
                if ( response.success ) {
                    var url = response.data.url;
                    $( '#ha_pf_image_url_field' ).val( url );

                    var preview = document.getElementById( 'ha-pf-image-preview' );
                    var wrap    = preview && preview.closest( '.ha-pf-image-preview-wrap' );
                    if ( preview ) preview.src = url;
                    if ( wrap    ) wrap.style.display = '';

                    markDirty();
                } else {
                    // eslint-disable-next-line no-alert
                    alert( 'Image copy failed: ' + ( response.data || 'Unknown error' ) );
                }
            } );
        } );

        mediaFrame.open();
    } );

    // -------------------------------------------------------
    // Test connection
    // -------------------------------------------------------
    ( function () {
        var btn     = document.getElementById( 'ha-pf-test-btn' );
        var result  = document.getElementById( 'ha-pf-test-result' );
        var spinner = btn ? btn.querySelector( '.ha-pf-test-spinner' ) : null;
        var icon    = btn ? btn.querySelector( '.ha-pf-test-icon' ) : null;

        if ( ! btn || ! result ) return;

        btn.addEventListener( 'click', function () {
            setTestState( 'loading' );

            var fd = new FormData();
            fd.append( 'action', 'ha_pf_test_connection' );
            fd.append( 'nonce',  haPfAdmin.testConnectionNonce );

            fetch( haPfAdmin.ajaxUrl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( data ) {
                    if ( data.success ) {
                        setTestState( 'success', data.data.detail || 'Connected successfully' );
                    } else {
                        var msg = ( data.data && data.data.message ) ? data.data.message : 'Connection failed';
                        setTestState( 'error', msg );
                    }
                } )
                .catch( function ( err ) {
                    setTestState( 'error', 'Network error: ' + err.message );
                } );
        } );

        function setTestState( state, text ) {
            btn.disabled = ( state === 'loading' );
            if ( spinner ) spinner.style.display = state === 'loading' ? 'inline-block' : 'none';
            if ( icon )    icon.style.display    = state === 'loading' ? 'none' : '';

            result.textContent = text || '';
            result.className   = 'ha-pf-test-result' + ( text ? ' ha-pf-test-' + state : '' );
        }
    }() );

    // -------------------------------------------------------
    // Config restore: file picker → confirmation modal → AJAX
    // -------------------------------------------------------
    ( function () {

        var restoreBtn    = document.getElementById( 'ha-pf-restore-btn' );
        var fileInput     = document.getElementById( 'ha-pf-import-file' );
        var overlay       = document.getElementById( 'ha-pf-restore-overlay' );
        var cancelBtn     = document.getElementById( 'ha-pf-restore-cancel' );
        var confirmBtn    = document.getElementById( 'ha-pf-restore-confirm' );
        var filenameEl    = document.getElementById( 'ha-pf-restore-filename' );
        var confirmText   = confirmBtn  ? confirmBtn.querySelector( '.ha-pf-restore-confirm-text' )  : null;
        var confirmIcon   = confirmBtn  ? confirmBtn.querySelector( '.ha-pf-restore-confirm-icon' )  : null;
        var spinner       = confirmBtn  ? confirmBtn.querySelector( '.ha-pf-restore-spinner' )        : null;

        if ( ! restoreBtn || ! fileInput || ! overlay ) return;

        // Open file picker when the Restore button is clicked
        restoreBtn.addEventListener( 'click', function () {
            fileInput.value = '';   // reset so same file can be re-selected
            fileInput.click();
        } );

        // Once a file is chosen, show the confirmation modal
        fileInput.addEventListener( 'change', function () {
            if ( ! fileInput.files || ! fileInput.files[0] ) return;
            var name = fileInput.files[0].name;
            if ( filenameEl ) filenameEl.textContent = name;
            showModal();
        } );

        // Close modal on Cancel or overlay background click
        cancelBtn && cancelBtn.addEventListener( 'click', hideModal );

        overlay.addEventListener( 'click', function ( e ) {
            if ( e.target === overlay ) hideModal();
        } );

        // Close on Escape key
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && overlay.getAttribute( 'aria-hidden' ) === 'false' ) {
                hideModal();
            }
        } );

        // Confirm → upload via FormData AJAX
        confirmBtn && confirmBtn.addEventListener( 'click', function () {
            var file = fileInput.files && fileInput.files[0];
            if ( ! file ) { hideModal(); return; }

            setLoading( true );

            var fd = new FormData();
            fd.append( 'action',      'ha_pf_import_config_ajax' );
            fd.append( 'nonce',       haPfAdmin.importNonce );
            fd.append( 'config_file', file, file.name );

            fetch( haPfAdmin.ajaxUrl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( data ) {
                    if ( data.success ) {
                        // Brief success state then reload so all fields reflect restored values
                        if ( confirmText ) confirmText.textContent = 'Restored! Reloading…';
                        if ( confirmIcon ) { confirmIcon.className = 'dashicons dashicons-yes-alt ha-pf-restore-confirm-icon'; }
                        setTimeout( function () { window.location.reload(); }, 900 );
                    } else {
                        var msg = ( data.data && data.data.message ) ? data.data.message : 'An unknown error occurred.';
                        setLoading( false );
                        showError( msg );
                    }
                } )
                .catch( function ( err ) {
                    setLoading( false );
                    showError( 'Network error: ' + err.message );
                } );
        } );

        function showModal() {
            clearError();
            overlay.setAttribute( 'aria-hidden', 'false' );
            overlay.classList.add( 'is-visible' );
            if ( cancelBtn ) cancelBtn.focus();
        }

        function hideModal() {
            overlay.setAttribute( 'aria-hidden', 'true' );
            overlay.classList.remove( 'is-visible' );
            setLoading( false );
            clearError();
            // Reset confirm button text in case a previous attempt changed it
            if ( confirmText ) confirmText.textContent = 'Yes, Restore Settings';
            if ( confirmIcon ) confirmIcon.className = 'dashicons dashicons-yes-alt ha-pf-restore-confirm-icon';
        }

        function setLoading( on ) {
            if ( ! confirmBtn ) return;
            confirmBtn.disabled = on;
            if ( cancelBtn ) cancelBtn.disabled = on;
            if ( spinner ) spinner.style.display = on ? 'inline-block' : 'none';
            if ( confirmIcon ) confirmIcon.style.display = on ? 'none' : '';
        }

        function showError( msg ) {
            var existing = overlay.querySelector( '.ha-pf-modal-error' );
            if ( existing ) existing.remove();
            var el = document.createElement( 'p' );
            el.className = 'ha-pf-modal-error';
            el.textContent = msg;
            var actions = overlay.querySelector( '.ha-pf-modal-actions' );
            if ( actions ) actions.parentNode.insertBefore( el, actions );
        }

        function clearError() {
            var existing = overlay && overlay.querySelector( '.ha-pf-modal-error' );
            if ( existing ) existing.remove();
        }

    }() );

} )( jQuery, haPfAdmin );
