/**
 * Time Slot Booking JavaScript
 * Handles date navigation, time slot management, and user interactions
 */

(function($) {
    'use strict';

    // Global variables
    let currentDate = new Date();
    let isAdmin = false;

    // Initialize the plugin
    $(document).ready(function() {
        initializeDateNavigation();
        bindEvents();
        loadCurrentDateSlots();
        
        // Check if user has admin privileges
        isAdmin = $('[id*="tsb-add-slot"]').length > 0;
    });

    /**
     * Initialize date navigation
     */
    function initializeDateNavigation() {
        // Set current date
        updateDateDisplay();
        
        // Bind navigation events
        $('#tsb-prev-date').on('click', function() {
            navigateDate(-1);
        });
        
        $('#tsb-next-date').on('click', function() {
            navigateDate(1);
        });
        
        // Disable previous date if it's today or earlier
        updateNavigationButtons();
    }

    /**
     * Navigate to previous/next date
     */
    function navigateDate(direction) {
        currentDate.setDate(currentDate.getDate() + direction);
        updateDateDisplay();
        updateNavigationButtons();
        loadCurrentDateSlots();
    }

    /**
     * Update date display
     */
    function updateDateDisplay() {
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        $('#tsb-current-date').text(currentDate.toLocaleDateString('fr-FR', options));
    }

    /**
     * Update navigation button states
     */
    function updateNavigationButtons() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        currentDate.setHours(0, 0, 0, 0);
        
        // Disable previous button if current date is today or earlier
        $('#tsb-prev-date').prop('disabled', currentDate <= today);
        
        // Calculate 8 days from today for next button
        const maxDate = new Date(today);
        maxDate.setDate(maxDate.getDate() + 8);
        
        // Disable next button if current date is 8 days from today or later
        $('#tsb-next-date').prop('disabled', currentDate >= maxDate);
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Add time slot modal
        $(document).on('click', '#tsb-add-slot-btn', showAddSlotModal);
        $(document).on('click', '.tsb-close', closeModal);
        $(document).on('submit', '#tsb-add-slot-form', handleAddSlot);
        
        // User registration
        $(document).on('click', '.tsb-register-btn', showRegisterModal);
        $(document).on('submit', '#tsb-register-form', handleUserRegistration);
        
        // Remove time slot
        $(document).on('click', '.tsb-remove-btn', handleRemoveSlot);
        
        // Close modal when clicking outside
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('tsb-modal')) {
                closeModal();
            }
        });
        
        // ESC key to close modal
        $(document).on('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Load time slots for current date
     */
    function loadCurrentDateSlots() {
        showLoading();
        
        const dateString = formatDateForServer(currentDate);
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_date_slots',
                date: dateString,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderTimeSlots(response.data);
                } else {
                    showError(response.data || tsb_ajax.messages.error);
                }
            },
            error: function() {
                showError(tsb_ajax.messages.error);
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    /**
     * Render time slots in table
     */
    function renderTimeSlots(slots) {
        const tbody = $('#tsb-table-body');
        tbody.empty();
        
        if (!slots || slots.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="2" class="tsb-empty-state">
                        <h4>Aucun créneau disponible</h4>
                        <p>Aucun créneau horaire n'a été défini pour cette date.</p>
                        ${isAdmin ? '<p>Utilisez le bouton "Ajouter un créneau" pour créer des créneaux.</p>' : ''}
                    </td>
                </tr>
            `);
            return;
        }
        
        // Group slots by time range
        const groupedSlots = groupSlotsByTime(slots);
        
        for (const timeRange in groupedSlots) {
            const slotsForTime = groupedSlots[timeRange];
            const row = $(`
                <tr>
                    <td class="tsb-time-cell" data-label="Horaires">${timeRange}</td>
                    <td class="tsb-slot-cell" data-label="Créneaux">
                        <div class="tsb-slots-container" id="slots-${timeRange.replace(/[:\s-]/g, '')}">
                        </div>
                    </td>
                </tr>
            `);
            
            tbody.append(row);
            
            // Add individual slots
            slotsForTime.forEach(slot => {
                renderSlotItem(slot, timeRange.replace(/[:\s-]/g, ''));
            });
        }
    }

    /**
     * Group slots by time range
     */
    function groupSlotsByTime(slots) {
        const grouped = {};
        
        slots.forEach(slot => {
            const timeRange = `${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}`;
            if (!grouped[timeRange]) {
                grouped[timeRange] = [];
            }
            grouped[timeRange].push(slot);
        });
        
        return grouped;
    }

    /**
     * Render individual slot item
     */
    function renderSlotItem(slot, containerId) {
        const container = $(`#slots-${containerId}`);
        const isAvailable = parseInt(slot.registered_count) < parseInt(slot.capacity);
        const slotClass = isAvailable ? 'tsb-slot-available' : 'tsb-slot-full';
        
        const slotElement = $(`
            <div class="tsb-slot-item ${slotClass}" data-slot-id="${slot.id}">
                <div class="tsb-slot-info">
                    <div class="tsb-slot-status">
                        ${isAvailable ? 'Disponible' : 'Complet'}
                    </div>
                    <div class="tsb-slot-capacity">
                        ${slot.registered_count}/${slot.capacity} inscrit(s)
                    </div>
                </div>
                <div class="tsb-slot-actions">
                    ${isAvailable ? `<button class="tsb-btn tsb-btn-success tsb-btn-small tsb-register-btn" data-slot-id="${slot.id}">S'inscrire</button>` : ''}
                    ${isAdmin ? `<button class="tsb-btn tsb-btn-danger tsb-btn-small tsb-remove-btn" data-slot-id="${slot.id}">Supprimer</button>` : ''}
                </div>
            </div>
        `);
        
        container.append(slotElement);
    }

    /**
     * Show add slot modal
     */
    function showAddSlotModal() {
        $('#tsb-add-slot-modal').show();
        $('#tsb-start-time').focus();
    }

    /**
     * Show user registration modal
     */
    function showRegisterModal(event) {
        const slotId = $(event.target).data('slot-id');
        $('#tsb-register-slot-id').val(slotId);
        $('#tsb-register-modal').show();
        $('#tsb-user-first-name').focus();
    }

    /**
     * Close all modals
     */
    function closeModal() {
        $('.tsb-modal').hide();
        // Reset forms
        $('#tsb-add-slot-form')[0].reset();
        $('#tsb-register-form')[0].reset();
    }

    /**
     * Handle add time slot
     */
    function handleAddSlot(event) {
        event.preventDefault();
        
        const startTime = $('#tsb-start-time').val();
        const endTime = $('#tsb-end-time').val();
        const capacity = $('#tsb-capacity').val();
        
        if (!startTime || !endTime || !capacity) {
            showError('Veuillez remplir tous les champs requis.');
            return;
        }
        
        if (startTime >= endTime) {
            showError('L\'heure de fin doit être postérieure à l\'heure de début.');
            return;
        }
        
        const dateString = formatDateForServer(currentDate);
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'add_time_slot',
                date: dateString,
                start_time: startTime,
                end_time: endTime,
                capacity: capacity,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                    closeModal();
                    loadCurrentDateSlots();
                } else {
                    showError(response.data || 'Erreur lors de l\'ajout du créneau.');
                }
            },
            error: function() {
                showError('Erreur de communication avec le serveur.');
            }
        });
    }

    /**
     * Handle user registration
     */
    function handleUserRegistration(event) {
        event.preventDefault();
        
        const slotId = $('#tsb-register-slot-id').val();
        const userFirstName = $('#tsb-user-first-name').val();
        const userLastName = $('#tsb-user-last-name').val();
        const userEmail = $('#tsb-user-email').val();
        const userPhone = $('#tsb-user-phone').val();
        
        if (!slotId || !userFirstName || !userLastName || !userEmail) {
            showError('Veuillez remplir tous les champs obligatoires.');
            return;
        }
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'register_user_slot',
                slot_id: slotId,
                user_first_name: userFirstName,
                user_last_name: userLastName,
                user_email: userEmail,
                user_phone: userPhone,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Inscription réussie !');
                    closeModal();
                    loadCurrentDateSlots();
                } else {
                    showError(response.data || 'Erreur lors de l\'inscription.');
                }
            },
            error: function() {
                showError('Erreur de communication avec le serveur.');
            }
        });
    }

    /**
     * Handle remove time slot
     */
    function handleRemoveSlot(event) {
        if (!confirm(tsb_ajax.messages.confirm_remove)) {
            return;
        }
        
        const slotId = $(event.target).data('slot-id');
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'remove_time_slot',
                slot_id: slotId,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                    loadCurrentDateSlots();
                } else {
                    showError(response.data || 'Erreur lors de la suppression.');
                }
            },
            error: function() {
                showError('Erreur de communication avec le serveur.');
            }
        });
    }

    /**
     * Format date for server (YYYY-MM-DD)
     */
    function formatDateForServer(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Show loading state
     */
    function showLoading() {
        $('#tsb-table-body').html(`
            <tr>
                <td colspan="2" class="tsb-loading">
                    <div class="tsb-spinner"></div>
                    Chargement des créneaux...
                </td>
            </tr>
        `);
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        // Loading is hidden when content is loaded
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        showMessage(message, 'success');
    }

    /**
     * Show error message
     */
    function showError(message) {
        showMessage(message, 'error');
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        // Remove existing messages
        $('.tsb-message').remove();
        
        const messageElement = $(`
            <div class="tsb-message tsb-message-${type}">
                ${message}
            </div>
        `);
        
        $('#tsb-booking-container').prepend(messageElement);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageElement.fadeOut(() => {
                messageElement.remove();
            });
        }, 5000);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: messageElement.offset().top - 20
        }, 300);
    }

    /**
     * Utility function to validate email
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Utility function to validate time format
     */
    function isValidTime(time) {
        const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
        return timeRegex.test(time);
    }

})(jQuery);