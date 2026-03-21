jQuery(document).ready(function($) {
    if (!$('#ha-pf-calendar-wrapper').length) return;

    let currentStartDate = new Date();
    currentStartDate.setHours(0, 0, 0, 0);
    
    // Adjust to start of current week (Monday)
    const day = currentStartDate.getDay();
    const diff = currentStartDate.getDate() - day + (day === 0 ? -6 : 1);
    currentStartDate.setDate(diff);

    const $grid = $('#ha-pf-calendar-grid');
    const $weekRange = $('#ha-pf-week-range');
    const $modal = $('#ha-pf-booking-modal');
    
    let selectedSlot = null;
    let globalBookings = [];

    function renderCalendar() {
        $grid.html('<div class="ha-pf-calendar-loading">Loading slots...</div>');
        
        // Fetch bookings first
        $.post(haPfCalendar.ajaxUrl, {
            action: 'ha_pf_get_bookings',
            nonce: haPfCalendar.nonce,
            start_date: currentStartDate.toISOString().split('T')[0]
        }, function(response) {
            if (response.success) {
                $weekRange.text(response.data.week_range);
                globalBookings = response.data.bookings;
                buildGrid(globalBookings);
            }
        });
    }

    function buildGrid(bookings) {
        $grid.empty();
        
        // 1. Time Header (Empty corner)
        $grid.append('<div class="ha-pf-day-header ha-pf-time-header"></div>');
        
        // 2. Day Headers
        for (let i = 0; i < 7; i++) {
            const date = new Date(currentStartDate);
            date.setDate(date.getDate() + i);
            const dayName = date.toLocaleDateString('en-GB', { weekday: 'short' });
            const dayDate = date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            
            $grid.append(`
                <div class="ha-pf-day-header day-${i}">
                    <span class="day-name">${dayName}</span>
                    <span class="day-date">${dayDate}</span>
                </div>
            `);
        }

        // 3. Time Slots (48 slots total: 24h * 2 per hour)
        for (let hour = 0; hour < 24; hour++) {
            for (let min = 0; min < 60; min += 30) {
                const timeStr = `${hour.toString().padStart(2, '0')}:${min.toString().padStart(2, '0')}`;
                
                // Time label column
                $grid.append(`<div class="ha-pf-time-column">${timeStr}</div>`);
                
                // 7 Day slots for this time
                for (let dayOffset = 0; dayOffset < 7; dayOffset++) {
                    const slotDate = new Date(currentStartDate);
                    slotDate.setDate(slotDate.getDate() + dayOffset);
                    slotDate.setHours(hour, min, 0, 0);
                    
                    // Format for DB comparison: YYYY-MM-DD HH:MM:SS
                    const pad = (n) => n.toString().padStart(2, '0');
                    const dbFormat = `${slotDate.getFullYear()}-${pad(slotDate.getMonth() + 1)}-${pad(slotDate.getDate())} ${pad(slotDate.getHours())}:${pad(slotDate.getMinutes())}:00`;
                    
                    // Check if this 30-min slot falls within any booking range
                    const booking = bookings.find(b => {
                        return dbFormat >= b.start_time && dbFormat < b.end_time;
                    });
                    
                    let classes = 'ha-pf-slot';
                    let content = '';
                    let action = 'book';

                    if (booking) {
                        const canCancel = (booking.is_mine || haPfCalendar.isAdmin) && (dbFormat === booking.start_time);
                        if (booking.is_mine) {
                            classes += ' is-mine';
                        } else {
                            classes += ' is-booked';
                        }
                        
                        // Only show the label on the FIRST slot of the booking
                        if (dbFormat === booking.start_time) {
                            const label = booking.is_mine ? 'MINE' : (haPfCalendar.isAdmin ? booking.user_name : 'Booked');
                            content = `<span>${label}${canCancel ? ' (✕)' : ''}</span>`;
                            action = canCancel ? 'cancel' : 'none';
                        } else {
                            action = 'none'; // Middle of a booking
                        }
                    }
                    
                    const $slot = $(`<div class="${classes}" data-time="${dbFormat}" data-action="${action}" data-id="${booking ? booking.id : ''}">${content}</div>`);
                    $grid.append($slot);
                }
            }
        }
    }

    // Navigation
    $('#ha-pf-prev-week').on('click', function() {
        currentStartDate.setDate(currentStartDate.getDate() - 7);
        renderCalendar();
    });

    $('#ha-pf-next-week').on('click', function() {
        currentStartDate.setDate(currentStartDate.getDate() + 7);
        renderCalendar();
    });

    // Handle Clicks
    $grid.on('click', '.ha-pf-slot', function() {
        const $slot = $(this);
        const action = $slot.data('action');
        const time = $slot.data('time');
        const bookingId = $slot.data('id');

        if (action === 'none') return;

        if (action === 'cancel') {
            if (confirm('Cancel this entire booking session?')) {
                $.post(haPfCalendar.ajaxUrl, {
                    action: 'ha_pf_cancel_booking',
                    nonce: haPfCalendar.nonce,
                    booking_id: bookingId
                }, function(res) {
                    if (res.success) renderCalendar();
                    else alert(res.data);
                });
            }
            return;
        }

        // Logic for booking
        selectedSlot = time;
        const startDate = new Date(time.replace(' ', 'T'));
        const displayDate = startDate.toLocaleString('en-GB', { 
            weekday: 'long', 
            day: 'numeric', 
            month: 'long', 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        // Populate End Time Dropdown
        const $endSelect = $('#ha-pf-end-time').empty();
        
        // Find next booking across ALL 7 days to cap the end time
        const nextBooking = globalBookings
            .filter(b => b.start_time > time)
            .sort((a,b) => a.start_time > b.start_time ? 1 : -1)[0];
        
        const bufferMs = (haPfCalendar.bufferMins || 15) * 60 * 1000;
        const absoluteMax = new Date(startDate);
        absoluteMax.setHours(absoluteMax.getHours() + 24); // Cap at 24 hours total
        
        const nextBookingRaw = nextBooking ? new Date(nextBooking.start_time.replace(' ', 'T')) : absoluteMax;
        const maxEnd = new Date(nextBookingRaw.getTime() - bufferMs);
        const finalLimit = new Date(Math.min(maxEnd.getTime(), absoluteMax.getTime()));

        // Start from slot + 30m
        let iter = new Date(startDate);
        iter.setMinutes(iter.getMinutes() + 30);
        
        while (iter <= finalLimit) {
            const isNextDay = iter.getDate() !== startDate.getDate();
            const timeVal = iter.toTimeString().substring(0, 5) + (isNextDay ? ' (+1 Day)' : '');
            
            const pad = (n) => n.toString().padStart(2, '0');
            const dataVal = `${iter.getFullYear()}-${pad(iter.getMonth() + 1)}-${pad(iter.getDate())} ${pad(iter.getHours())}:${pad(iter.getMinutes())}:00`;
            
            $endSelect.append(`<option value="${dataVal}">${timeVal}</option>`);
            iter.setMinutes(iter.getMinutes() + 30);
        }

        calculateEstimate();
        $('#ha-pf-booking-details').html(`<strong>Date:</strong> ${displayDate}`);
        $modal.fadeIn(200);
    });

    function isOffPeak(date) {
        if (!haPfCalendar.intelMode) return true;
        const time = date.getHours() * 60 + date.getMinutes();
        const startArr = haPfCalendar.offpeakStart.split(':');
        const endArr   = haPfCalendar.offpeakEnd.split(':');
        const start = parseInt(startArr[0]) * 60 + parseInt(startArr[1]);
        const end   = parseInt(endArr[0]) * 60 + parseInt(endArr[1]);
        return (start <= end) ? (time >= start && time < end) : (time >= start || time < end);
    }

    function getMarkupPct(price) {
        let pct = 0;
        if (haPfCalendar.markupRanges && haPfCalendar.markupRanges.length) {
            haPfCalendar.markupRanges.forEach(r => {
                if (price >= r.min && price <= r.max) pct = r.pct;
            });
        }
        return pct;
    }

    function calculateEstimate() {
        if (!selectedSlot) return;
        const endVal = $('#ha-pf-end-time').val();
        if (!endVal) return;

        const start = new Date(selectedSlot.replace(' ', 'T'));
        const end   = new Date(endVal.replace(' ', 'T'));
        
        if (isNaN(start) || isNaN(end) || end <= start) {
            $('#ha-pf-booking-estimate').hide();
            return;
        }

        const durationHrs = (end - start) / (1000 * 60 * 60);
        const totalKwh = durationHrs * (haPfCalendar.chargeKw || 7);
        
        let totalCost = 0;
        let iter = new Date(start);
        const segmentKwh = (haPfCalendar.chargeKw || 7) * 0.5;

        while (iter < end) {
            const basePrice = isOffPeak(iter) ? parseFloat(haPfCalendar.gridPrice) : parseFloat(haPfCalendar.peakPrice);
            const markupPct = getMarkupPct(basePrice);
            totalCost += segmentKwh * basePrice * (1 + (markupPct / 100));
            iter.setMinutes(iter.getMinutes() + 30);
        }

        $('#ha-pf-est-kwh').text(totalKwh.toFixed(2) + ' kWh');
        $('#ha-pf-est-cost').text(haPfCalendar.currency + totalCost.toFixed(2));
        $('#ha-pf-booking-estimate').show();

        // Show marked-up rate(s)
        if (haPfCalendar.intelMode) {
            const peakM = getMarkupPct(parseFloat(haPfCalendar.peakPrice));
            const offM  = getMarkupPct(parseFloat(haPfCalendar.gridPrice));
            const pFull = parseFloat(haPfCalendar.peakPrice) * (1 + (peakM/100));
            const oFull = parseFloat(haPfCalendar.gridPrice) * (1 + (offM/100));
            $('#ha-pf-current-rate').html(`Peak: ${haPfCalendar.currency}${pFull.toFixed(4)} | Off-Peak: ${haPfCalendar.currency}${oFull.toFixed(4)}`);
            $('.estimate-disclaimer').text(`Calculated using marked-up peak and off-peak rates`);
        } else {
            const base = parseFloat(haPfCalendar.gridPrice);
            const mPct = getMarkupPct(base);
            const full = base * (1 + (mPct/100));
            $('#ha-pf-current-rate').text(`${haPfCalendar.currency}${full.toFixed(4)} per kWh`);
            $('.estimate-disclaimer').text(`Price includes ${mPct}% admin markup`);
        }
    }

    $('#ha-pf-end-time').on('change', calculateEstimate);

    $('#ha-pf-confirm-booking').on('click', function() {
        if (!selectedSlot) return;

        const endTime = $('#ha-pf-end-time').val();
        if (!endTime) return;

        const $btn = $(this);
        $btn.prop('disabled', true).text('Confirming...');

        $.post(haPfCalendar.ajaxUrl, {
            action: 'ha_pf_make_booking',
            nonce: haPfCalendar.nonce,
            start_time: selectedSlot,
            end_time: endTime
        }, function(response) {
            $modal.fadeOut(200);
            $btn.prop('disabled', false).text('Confirm');
            if (response.success) {
                renderCalendar();
            } else {
                alert(response.data);
            }
        });
    });

    $('.ha-pf-close, #ha-pf-close-modal').on('click', function() {
        $modal.fadeOut(200);
    });

    $(window).on('click', function(event) {
        if (event.target == $modal[0]) {
            $modal.fadeOut(200);
        }
    });

    // User Dashboard Cancel
    $(document).on('click', '.ha-pf-btn-cancel', function() {
        const $btn = $(this);
        const bookingId = $btn.data('id');
        if (!confirm('Cancel this entire booking session?')) return;

        $btn.prop('disabled', true).text('...');
        $.post(haPfCalendar.ajaxUrl, {
            action: 'ha_pf_cancel_booking',
            nonce: haPfCalendar.nonce,
            booking_id: bookingId
        }, function(res) {
            if (res.success) {
                $btn.closest('.ha-pf-booking-card').fadeOut(300, function() { $(this).remove(); renderCalendar(); });
            } else {
                alert(res.data);
                $btn.prop('disabled', false).text('✕ Cancel');
            }
        });
    });

    renderCalendar();
});
