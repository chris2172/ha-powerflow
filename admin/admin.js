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
    // Threshold builder
    // -------------------------------------------------------
    ( function () {
        var list      = document.getElementById( 'ha-pf-thresholds-list' );
        var jsonField = document.getElementById( 'ha-pf-thresholds-json' );
        var addBtn    = document.getElementById( 'ha-pf-add-threshold' );
        var form      = document.getElementById( 'ha-pf-form' );

        if ( ! list || ! jsonField ) return;

        // Build the entity <select> HTML from haPfAdmin data
        function buildEntityOptions( selectedKey ) {
            var labels  = haPfAdmin.entityLabels  || {};
            var customs = haPfAdmin.customEntities || [];

            var groups = {
                'Grid & Load': [ 'grid_power', 'grid_energy_in', 'grid_energy_out', 'load_power', 'load_energy' ],
                'Solar':       [ 'pv_power', 'pv_energy' ],
                'Battery':     [ 'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc' ],
                'EV':          [ 'ev_power', 'ev_soc' ],
            };

            var html = '';
            Object.keys( groups ).forEach( function ( gName ) {
                html += '<optgroup label="' + gName + '">';
                groups[ gName ].forEach( function ( k ) {
                    var sel = ( k === selectedKey ) ? ' selected' : '';
                    html += '<option value="' + k + '"' + sel + '>' + ( labels[ k ] || k ) + '</option>';
                } );
                html += '</optgroup>';
            } );

            if ( customs.length ) {
                html += '<optgroup label="Custom">';
                customs.forEach( function ( ce ) {
                    var k   = ce.id    || '';
                    var l   = ce.label || k;
                    var sel = ( k === selectedKey ) ? ' selected' : '';
                    html += '<option value="' + k + '"' + sel + '>' + l + '</option>';
                } );
                html += '</optgroup>';
            }

            return html;
        }

        var operators = [
            { v: '<',  l: 'is below' },
            { v: '<=', l: '\u2264' },
            { v: '>',  l: 'is above' },
            { v: '>=', l: '\u2265' },
            { v: '==', l: 'equals' },
        ];

        function buildOperatorOptions( selectedOp ) {
            return operators.map( function ( o ) {
                var sel = ( o.v === selectedOp ) ? ' selected' : '';
                return '<option value="' + o.v + '"' + sel + '>' + o.l + '</option>';
            } ).join( '' );
        }

        function buildRow( data ) {
            data = data || {};
            var row = document.createElement( 'div' );
            row.className = 'ha-pf-threshold-row';

            row.innerHTML =
                '<select class="tr-key">' + buildEntityOptions( data.key || 'grid_power' ) + '</select>' +
                '<select class="tr-operator">' + buildOperatorOptions( data.operator || '<' ) + '</select>' +
                '<input type="number" class="tr-value" step="any" placeholder="0" value="' + ( data.value !== undefined ? data.value : '' ) + '">' +
                '<input type="color" class="tr-colour" value="' + ( data.colour || '#ef4444' ) + '">' +
                '<button type="button" class="ha-pf-tr-delete button" aria-label="Remove rule">' +
                    '<span class="dashicons dashicons-trash"></span></button>';

            wireRow( row );
            return row;
        }

        function wireRow( row ) {
            row.querySelector( '.ha-pf-tr-delete' ).addEventListener( 'click', function () {
                row.parentNode.removeChild( row );
                serialise();
                markDirty();
            } );
            row.querySelectorAll( 'select, input' ).forEach( function ( el ) {
                el.addEventListener( 'change', function () { serialise(); markDirty(); } );
                el.addEventListener( 'input',  function () { serialise(); markDirty(); } );
            } );
        }

        // Wire server-rendered rows
        list.querySelectorAll( '.ha-pf-threshold-row' ).forEach( wireRow );

        if ( addBtn ) {
            addBtn.addEventListener( 'click', function () {
                var row = buildRow( {} );
                list.appendChild( row );
                row.querySelector( '.tr-value' ).focus();
                serialise();
                markDirty();
            } );
        }

        function serialise() {
            var items = [];
            list.querySelectorAll( '.ha-pf-threshold-row' ).forEach( function ( row ) {
                var key    = ( row.querySelector( '.tr-key'      ) || {} ).value || '';
                var op     = ( row.querySelector( '.tr-operator' ) || {} ).value || '<';
                var val    = parseFloat( ( row.querySelector( '.tr-value'  ) || {} ).value );
                var colour = ( row.querySelector( '.tr-colour'   ) || {} ).value || '#ef4444';
                if ( ! key ) return;
                items.push( { key: key, operator: op, value: isNaN( val ) ? 0 : val, colour: colour } );
            } );
            jsonField.value = JSON.stringify( items );
        }

        // Serialise once on page load so the field is consistent
        serialise();

        if ( form ) {
            form.addEventListener( 'submit', serialise );
        }
    }() );

    // -------------------------------------------------------
    // Server snapshot restore
    // -------------------------------------------------------
    ( function () {
        var select     = document.getElementById( 'ha-pf-snapshot-select' );
        var restoreBtn = document.getElementById( 'ha-pf-snapshot-restore-btn' );
        var statusEl   = document.getElementById( 'ha-pf-snapshot-status' );

        // Re-use the existing restore modal infrastructure
        var overlay    = document.getElementById( 'ha-pf-restore-overlay' );
        var confirmBtn = document.getElementById( 'ha-pf-restore-confirm' );
        var cancelBtn  = document.getElementById( 'ha-pf-restore-cancel' );
        var filenameEl = document.getElementById( 'ha-pf-restore-filename' );
        var confirmText = confirmBtn ? confirmBtn.querySelector( '.ha-pf-restore-confirm-text' ) : null;
        var confirmIcon = confirmBtn ? confirmBtn.querySelector( '.ha-pf-restore-confirm-icon' ) : null;
        var spinner     = confirmBtn ? confirmBtn.querySelector( '.ha-pf-restore-spinner' )      : null;

        if ( ! select || ! restoreBtn ) return;

        // ── Load snapshot list ────────────────────────────────────────
        var fd = new FormData();
        fd.append( 'action', 'ha_pf_list_snapshots' );
        fd.append( 'nonce',  haPfAdmin.listSnapshotsNonce );

        fetch( haPfAdmin.ajaxUrl, { method: 'POST', body: fd } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( data ) {
                select.innerHTML = '';

                if ( ! data.success || ! data.data.snapshots.length ) {
                    var opt = document.createElement( 'option' );
                    opt.value   = '';
                    opt.textContent = data.success ? 'No snapshots found' : 'Failed to load snapshots';
                    select.appendChild( opt );
                    if ( statusEl ) statusEl.textContent = 'Snapshots are created automatically every time you save settings.';
                    return;
                }

                data.data.snapshots.forEach( function ( snap, i ) {
                    var opt = document.createElement( 'option' );
                    opt.value       = snap.filename;
                    opt.textContent = snap.label + ( i === 0 ? '  (latest)' : '' );
                    select.appendChild( opt );
                } );

                restoreBtn.disabled = false;
                if ( statusEl ) statusEl.textContent = data.data.snapshots.length + ' snapshot' + ( data.data.snapshots.length === 1 ? '' : 's' ) + ' available';
            } )
            .catch( function () {
                select.innerHTML = '<option value="">Error loading snapshots</option>';
            } );

        // ── Restore button — show the shared confirm modal ────────────
        restoreBtn.addEventListener( 'click', function () {
            var filename = select.value;
            if ( ! filename || ! overlay ) return;

            if ( filenameEl ) filenameEl.textContent = filename;
            if ( confirmText ) confirmText.textContent = 'Yes, Restore Settings';
            if ( confirmIcon ) confirmIcon.className = 'dashicons dashicons-yes-alt ha-pf-restore-confirm-icon';

            clearError();
            overlay.setAttribute( 'aria-hidden', 'false' );
            overlay.classList.add( 'is-visible' );
            if ( cancelBtn ) cancelBtn.focus();

            // Swap the confirm handler for snapshot restore
            if ( confirmBtn ) {
                confirmBtn.onclick = function () {
                    setLoading( true );

                    var sfd = new FormData();
                    sfd.append( 'action',   'ha_pf_restore_snapshot' );
                    sfd.append( 'nonce',    haPfAdmin.restoreSnapshotNonce );
                    sfd.append( 'filename', filename );

                    fetch( haPfAdmin.ajaxUrl, { method: 'POST', body: sfd } )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( data ) {
                            if ( data.success ) {
                                if ( confirmText ) confirmText.textContent = 'Restored! Reloading\u2026';
                                setTimeout( function () { window.location.reload(); }, 900 );
                            } else {
                                var msg = ( data.data && data.data.message ) ? data.data.message : 'Restore failed.';
                                setLoading( false );
                                showError( msg );
                            }
                        } )
                        .catch( function ( err ) {
                            setLoading( false );
                            showError( 'Network error: ' + err.message );
                        } );
                };
            }
        } );

        cancelBtn && cancelBtn.addEventListener( 'click', function () {
            if ( confirmBtn ) confirmBtn.onclick = null;  // clear snapshot handler; file-restore handler re-sets its own
        } );

        function setLoading( on ) {
            if ( ! confirmBtn ) return;
            confirmBtn.disabled = on;
            if ( cancelBtn ) cancelBtn.disabled = on;
            if ( spinner ) spinner.style.display = on ? 'inline-block' : 'none';
            if ( confirmIcon ) confirmIcon.style.display = on ? 'none' : '';
        }

        function showError( msg ) {
            clearError();
            var el = document.createElement( 'p' );
            el.className = 'ha-pf-modal-error';
            el.textContent = msg;
            var actions = overlay && overlay.querySelector( '.ha-pf-modal-actions' );
            if ( actions ) actions.parentNode.insertBefore( el, actions );
        }

        function clearError() {
            var existing = overlay && overlay.querySelector( '.ha-pf-modal-error' );
            if ( existing ) existing.remove();
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

    // -------------------------------------------------------
    // Named Configs manager
    // -------------------------------------------------------
    ( function () {

        var ncNonce   = haPfAdmin.namedConfigsNonce || '';
        var ajaxUrl   = haPfAdmin.ajaxUrl || '';

        var listEl    = document.getElementById( 'ha-pf-nc-list' );
        var editor    = document.getElementById( 'ha-pf-nc-editor' );
        var statusEl  = document.getElementById( 'ha-pf-nc-status' );

        var createBtn = document.getElementById( 'ha-pf-nc-create-btn' );
        var newLabel  = document.getElementById( 'ha-pf-nc-new-label' );
        var newSlug   = document.getElementById( 'ha-pf-nc-new-slug' );

        var editorTitle = document.getElementById( 'ha-pf-nc-editor-title' );
        var editorSlugDisplay = document.getElementById( 'ha-pf-nc-editor-slug-display' );
        var closeBtn  = document.getElementById( 'ha-pf-nc-editor-close' );
        var saveBtn   = document.getElementById( 'ha-pf-nc-save-btn' );
        var saveStatus = document.getElementById( 'ha-pf-nc-save-status' );

        var currentSlug = null;   // slug of the config currently open in the editor

        if ( ! listEl || ! ncNonce ) return;

        // ── Auto-generate slug from label ─────────────────────────────────
        if ( newLabel && newSlug ) {
            newLabel.addEventListener( 'input', function () {
                if ( newSlug._userEdited ) return;
                newSlug.value = ( newLabel.value || '' )
                    .toLowerCase()
                    .replace( /[^a-z0-9]+/g, '-' )
                    .replace( /^-|-$/g, '' )
                    .substring( 0, 40 );
            } );
            newSlug.addEventListener( 'input', function () {
                newSlug._userEdited = !! newSlug.value;
            } );
        }

        // ── AJAX helper ───────────────────────────────────────────────────
        function ncAjax( action, data, cb ) {
            var fd = new FormData();
            fd.append( 'action', action );
            fd.append( 'nonce',  ncNonce );
            Object.keys( data || {} ).forEach( function ( k ) {
                fd.append( k, data[ k ] );
            } );
            fetch( ajaxUrl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) { cb( null, res ); } )
                .catch( function ( err ) { cb( err, null ); } );
        }

        // ── Build the configs list ────────────────────────────────────────
        function renderList( configs ) {
            listEl.innerHTML = '';

            if ( ! configs || ! configs.length ) {
                listEl.innerHTML = '<p class="description">No named configs yet. Create one below.</p>';
                return;
            }

            configs.forEach( function ( item ) {
                var div = document.createElement( 'div' );
                div.className = 'ha-pf-nc-item';
                div.innerHTML =
                    '<span class="ha-pf-nc-item-label">' + esc( item.label ) + '</span>' +
                    '<span class="ha-pf-nc-item-slug">' + esc( item.slug ) + '</span>' +
                    '<span class="ha-pf-nc-item-shortcode">[ha_powerflow config=&quot;' + esc( item.slug ) + '&quot;]</span>' +
                    '<span class="ha-pf-nc-item-actions">' +
                        '<button type="button" class="button ha-pf-nc-edit-btn" data-slug="' + esc( item.slug ) + '" data-label="' + esc( item.label ) + '">' +
                            '<span class="dashicons dashicons-edit" style="margin-top:3px;"></span> Edit' +
                        '</button>' +
                        '<button type="button" class="button ha-pf-nc-delete-btn" data-slug="' + esc( item.slug ) + '" data-label="' + esc( item.label ) + '">' +
                            '<span class="dashicons dashicons-trash" style="margin-top:3px;"></span>' +
                        '</button>' +
                    '</span>';

                div.querySelector( '.ha-pf-nc-edit-btn' ).addEventListener( 'click', openEditor );
                div.querySelector( '.ha-pf-nc-delete-btn' ).addEventListener( 'click', deleteConfig );

                listEl.appendChild( div );
            } );
        }

        // ── Load and render the list ──────────────────────────────────────
        function loadList() {
            ncAjax( 'ha_pf_nc_list', {}, function ( err, res ) {
                if ( err || ! res || ! res.success ) {
                    setStatus( 'Could not load configs.', 'error' );
                    return;
                }
                renderList( res.data.configs );
            } );
        }

        loadList();

        // ── Create config ─────────────────────────────────────────────────
        if ( createBtn ) {
            createBtn.addEventListener( 'click', function () {
                var label = ( newLabel && newLabel.value.trim() ) || '';
                var slug  = ( newSlug  && newSlug.value.trim()  ) || '';

                if ( ! label ) { setStatus( 'Please enter a name for the config.', 'error' ); return; }
                if ( ! slug  ) { setStatus( 'Please enter a slug (e.g. solar-only).', 'error' ); return; }
                if ( ! /^[a-z0-9][a-z0-9\-]{0,38}$/.test( slug ) ) {
                    setStatus( 'Slug must use lowercase letters, digits and hyphens only.', 'error' );
                    return;
                }

                createBtn.disabled = true;
                setStatus( 'Creating…', '' );

                ncAjax( 'ha_pf_nc_clone_global', { slug: slug, label: label }, function ( err, res ) {
                    createBtn.disabled = false;
                    if ( err || ! res || ! res.success ) {
                        var msg = res && res.data && res.data.message ? res.data.message : 'Create failed.';
                        setStatus( msg, 'error' );
                        return;
                    }
                    if ( newLabel ) { newLabel.value = ''; }
                    if ( newSlug  ) { newSlug.value = ''; newSlug._userEdited = false; }
                    setStatus( 'Config "' + esc( res.data.label ) + '" created.', 'ok' );
                    loadList();
                    // Immediately open the editor for the new config
                    openEditorForSlug( res.data.slug, res.data.label );
                } );
            } );
        }

        // ── Delete config ─────────────────────────────────────────────────
        function deleteConfig( e ) {
            var btn   = e.currentTarget;
            var slug  = btn.dataset.slug;
            var label = btn.dataset.label;

            if ( ! window.confirm( 'Delete config "' + label + '"? This cannot be undone.' ) ) return;

            ncAjax( 'ha_pf_nc_delete', { slug: slug }, function ( err, res ) {
                if ( err || ! res || ! res.success ) {
                    setStatus( 'Delete failed.', 'error' );
                    return;
                }
                setStatus( 'Config "' + esc( label ) + '" deleted.', 'ok' );
                if ( currentSlug === slug ) closeEditor();
                loadList();
            } );
        }

        // ── Open editor ───────────────────────────────────────────────────
        function openEditor( e ) {
            var btn   = e.currentTarget;
            openEditorForSlug( btn.dataset.slug, btn.dataset.label );
        }

        function openEditorForSlug( slug, label ) {
            currentSlug = slug;
            if ( editorTitle ) editorTitle.textContent = label;
            if ( editorSlugDisplay ) editorSlugDisplay.textContent = '[ha_powerflow config="' + slug + '"]';
            if ( saveStatus ) saveStatus.textContent = '';

            // Fetch the config data then populate fields
            ncAjax( 'ha_pf_nc_get', { slug: slug }, function ( err, res ) {
                if ( err || ! res || ! res.success ) {
                    setStatus( 'Could not load config.', 'error' );
                    return;
                }
                populateEditor( res.data.config );

                if ( editor ) {
                    editor.style.display = '';
                    editor.removeAttribute( 'aria-hidden' );
                    editor.scrollIntoView( { behavior: 'smooth', block: 'start' } );
                }
            } );
        }

        // ── Populate all editor fields from a config object ───────────────
        function populateEditor( cfg ) {
            if ( ! editor ) return;
            editor.querySelectorAll( '.ha-pf-nc-field' ).forEach( function ( el ) {
                var field = el.dataset.field || '';
                var type  = el.dataset.type  || el.type;
                var val   = cfg[ field ];

                if ( type === 'checkbox' ) {
                    el.checked = ( val === '1' || val === true || val === 1 );
                } else if ( val !== undefined && val !== null ) {
                    el.value = val;
                }
            } );
        }

        // ── Collect editor fields into a config object ────────────────────
        function collectEditor() {
            var out = {};
            if ( ! editor ) return out;
            editor.querySelectorAll( '.ha-pf-nc-field' ).forEach( function ( el ) {
                var field = el.dataset.field || '';
                var type  = el.dataset.type  || el.type;
                if ( ! field ) return;
                out[ field ] = ( type === 'checkbox' ) ? ( el.checked ? '1' : '0' ) : el.value;
            } );
            return out;
        }

        // ── Save config ───────────────────────────────────────────────────
        if ( saveBtn ) {
            saveBtn.addEventListener( 'click', function () {
                if ( ! currentSlug ) return;

                saveBtn.disabled = true;
                if ( saveStatus ) { saveStatus.textContent = 'Saving…'; saveStatus.style.color = ''; }

                var cfg = collectEditor();
                // Preserve the label from the editor title since we don't have a label field in the editor
                if ( editorTitle ) cfg.label = editorTitle.textContent;

                ncAjax( 'ha_pf_nc_save', { slug: currentSlug, config: JSON.stringify( cfg ) }, function ( err, res ) {
                    saveBtn.disabled = false;
                    if ( err || ! res || ! res.success ) {
                        var msg = res && res.data && res.data.message ? res.data.message : 'Save failed.';
                        if ( saveStatus ) { saveStatus.textContent = msg; saveStatus.style.color = '#ef4444'; }
                        return;
                    }
                    if ( saveStatus ) {
                        saveStatus.textContent = 'Saved ✓';
                        saveStatus.style.color = 'var(--pf-green-dim)';
                        setTimeout( function () {
                            if ( saveStatus ) saveStatus.textContent = '';
                        }, 2500 );
                    }
                    loadList();
                } );
            } );
        }

        // ── Close editor ──────────────────────────────────────────────────
        function closeEditor() {
            currentSlug = null;
            if ( editor ) {
                editor.style.display = 'none';
                editor.setAttribute( 'aria-hidden', 'true' );
            }
        }

        if ( closeBtn ) {
            closeBtn.addEventListener( 'click', closeEditor );
        }

        // ── HTML escape helper ────────────────────────────────────────────
        function esc( str ) {
            return String( str )
                .replace( /&/g, '&amp;' )
                .replace( /</g, '&lt;' )
                .replace( />/g, '&gt;' )
                .replace( /"/g, '&quot;' );
        }

        function setStatus( msg, type ) {
            if ( ! statusEl ) return;
            statusEl.textContent = msg;
            statusEl.style.color = type === 'error' ? '#ef4444' : ( type === 'ok' ? 'var(--pf-green-dim)' : '' );
        }

    }() );


    // ══════════════════════════════════════════════════════════════════════
    // LIVE PREVIEW  — updates the miniature SVG as settings are changed
    // ══════════════════════════════════════════════════════════════════════
    (function initPreview() {

        const svg  = document.getElementById( 'ha-pf-preview-svg' );
        if ( ! svg ) return;   // preview panel not rendered

        const D = window.haPfPreviewData;
        if ( ! D ) return;

        // ── helpers ──────────────────────────────────────────────────────
        function val( id ) {
            const el = document.getElementById( id );
            return el ? el.value : '';
        }

        function optVal( name ) {
            const el = document.querySelector( '[name="' + name + '"]:not([type=hidden])' );
            if ( ! el ) return '';
            if ( el.type === 'checkbox' ) return el.checked ? '1' : '0';
            return el.value;
        }

        function pos( key, field ) {
            const name    = 'ha_powerflow_' + key + '_' + field;
            const input   = document.querySelector( '[name="' + name + '"]' );
            const raw     = input ? parseInt( input.value, 10 ) : NaN;
            const defKey  = field === 'x_pos' ? 'x' : field === 'y_pos' ? 'y' : 'rot';
            const defVal  = ( D.posDefaults[ key ] || {} )[ defKey ] || 0;
            // x/y of 0 means "not set, use default"; rotation of 0 is valid
            const isXY    = field === 'x_pos' || field === 'y_pos';
            if ( isNaN( raw ) ) return defVal;
            if ( isXY && raw === 0 ) return defVal;
            return raw;
        }

        // ── image ─────────────────────────────────────────────────────────
        function updateImage() {
            const imgEl = document.getElementById( 'ha-pf-preview-img' );
            if ( ! imgEl ) return;
            const urlField = document.getElementById( 'ha_pf_image_url_field' );
            if ( urlField && urlField.value ) {
                imgEl.setAttribute( 'href', urlField.value );
            }
        }

        // ── colours ───────────────────────────────────────────────────────
        function updateColours() {
            const textC = val( 'ha_pf_text_colour' ) || '#5EC766';
            const lineC = val( 'ha_pf_line_colour' ) || '#5EC766';

            // Labels
            svg.querySelectorAll( '.ha-pf-preview-label' ).forEach( el => {
                el.setAttribute( 'fill', textC );
            } );
            // Lines
            svg.querySelectorAll( '.ha-pf-preview-line' ).forEach( el => {
                el.setAttribute( 'stroke', lineC );
            } );
        }

        // ── label positions ───────────────────────────────────────────────
        function updatePositions() {
            D.entities.forEach( key => {
                const el = document.getElementById( 'ha-pf-prev-txt-' + key );
                if ( ! el ) return;

                const x   = pos( key, 'x_pos' );
                const y   = pos( key, 'y_pos' );
                const rot = pos( key, 'rot' );

                el.setAttribute( 'x', x );
                el.setAttribute( 'y', y );

                if ( rot !== 0 ) {
                    el.setAttribute( 'transform', 'rotate(' + rot + ' ' + x + ' ' + y + ')' );
                } else {
                    el.removeAttribute( 'transform' );
                }
            } );
        }

        // ── flow paths ───────────────────────────────────────────────────
        function updatePaths() {
            const flowMap = {
                grid: 'grid', load: 'load', pv: 'pv', battery: 'bat', ev: 'ev'
            };
            for ( const [ flow, lineKey ] of Object.entries( flowMap ) ) {
                const lineEl = document.getElementById( 'ha-pf-prev-line-' + lineKey );
                if ( ! lineEl ) continue;

                const fwdInput = document.querySelector( '[name="ha_powerflow_' + flow + '_flow_forward"]' );
                const rawFwd   = fwdInput ? fwdInput.value.trim() : '';
                const fwd      = rawFwd.match( /^[Mm]/ ) ? rawFwd : D.pathDefaults[ flow ].fwd;
                lineEl.setAttribute( 'd', fwd );
            }
        }

        // ── feature toggles (show/hide lines & labels) ───────────────────
        function updateFeatures() {
            const solar   = document.getElementById( 'ha_pf_enable_solar' );
            const battery = document.getElementById( 'ha_pf_enable_battery' );
            const ev      = document.getElementById( 'ha_pf_enable_ev' );
            const hasSolar   = solar   && solar.checked;
            const hasBattery = battery && battery.checked;
            const hasEv      = ev      && ev.checked;

            function setVisible( id, show ) {
                const el = document.getElementById( id );
                if ( el ) el.style.display = show ? '' : 'none';
            }

            setVisible( 'ha-pf-prev-line-pv',  hasSolar );
            setVisible( 'ha-pf-prev-line-bat',  hasBattery );
            setVisible( 'ha-pf-prev-line-ev',   hasEv );

            const solarKeys   = ['pv_power','pv_energy'];
            const batteryKeys = ['battery_power','battery_energy_in','battery_energy_out','battery_soc'];
            const evKeys      = ['ev_power','ev_soc'];
            const exportKeys  = ['grid_energy_out'];

            solarKeys.forEach( k => setVisible( 'ha-pf-prev-txt-' + k, hasSolar ) );
            batteryKeys.forEach( k => setVisible( 'ha-pf-prev-txt-' + k, hasBattery ) );
            evKeys.forEach( k => setVisible( 'ha-pf-prev-txt-' + k, hasEv ) );
            exportKeys.forEach( k => setVisible( 'ha-pf-prev-txt-' + k, hasSolar || hasBattery ) );
        }

        // ── full refresh ─────────────────────────────────────────────────
        function refresh() {
            updateImage();
            updateColours();
            updatePositions();
            updatePaths();
            updateFeatures();
        }

        // ── wire up change listeners ──────────────────────────────────────

        // Colour pickers
        ['ha_pf_text_colour','ha_pf_line_colour','ha_pf_dot_colour'].forEach( id => {
            const el = document.getElementById( id );
            if ( el ) el.addEventListener( 'input', refresh );
        } );

        // Image URL field
        const imgField = document.getElementById( 'ha_pf_image_url_field' );
        if ( imgField ) {
            imgField.addEventListener( 'change', refresh );
            imgField.addEventListener( 'input',  refresh );
        }

        // Feature toggles
        ['ha_pf_enable_solar','ha_pf_enable_battery','ha_pf_enable_ev'].forEach( id => {
            const el = document.getElementById( id );
            if ( el ) el.addEventListener( 'change', refresh );
        } );

        // Position fields — delegate on the form so it covers dynamically added rows
        const form = document.getElementById( 'ha-pf-form' );
        if ( form ) {
            form.addEventListener( 'input', function ( e ) {
                const name = e.target.name || '';
                if ( name.match( /_(rot|x_pos|y_pos)$/ ) ) {
                    updatePositions();
                }
                if ( name.match( /_flow_(forward|reverse)$/ ) ) {
                    updatePaths();
                }
            } );
        }

        // Reset buttons fire a 'change' event after resetting; listen on document
        document.addEventListener( 'click', function ( e ) {
            if ( e.target.closest( '.ha-pf-colour-reset-btn' ) ) {
                setTimeout( refresh, 0 );
            }
        } );

        // Media library image selection
        document.addEventListener( 'ha-pf-image-selected', refresh );

        // Initial render
        refresh();

    }() );

} )( jQuery, haPfAdmin );
