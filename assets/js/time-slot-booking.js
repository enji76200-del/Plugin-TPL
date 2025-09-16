/**
 * Time Slot Booking JavaScript
 * Handles date navigation, time slot management, and user interactions
 */

(function($) {
    'use strict';

    // Global variables
    let currentDate = new Date();
    let currentView = 'daily'; // 'daily' or 'weekly'
    let currentWeekStart = new Date();
    let isAdmin = false;
    let blockingMode = false;
    let currentPlanning = 1;
    let plannings = [];

    // Initialize the plugin
    $(document).ready(function() {
        initializePlanningNavigation();
        initializeDateNavigation();
        initializeViewControls();
        bindEvents();
        loadPlannings();
        loadCurrentView();
        
        // Check if user has admin privileges from localized data
        isAdmin = tsb_ajax.is_admin == 1;
        
        // Hide admin controls if not admin
        if (!isAdmin) {
            $('.tsb-admin-controls').hide();
            $('.tsb-remove-btn, .tsb-block-btn, .tsb-unblock-btn').hide();
        }
        
        // Get current planning from container
        const container = $('#tsb-booking-container');
        if (container.data('current-planning')) {
            currentPlanning = parseInt(container.data('current-planning'));
            tsb_ajax.current_planning = currentPlanning;
        }
        
        // Set initial week start
        setWeekStart(currentDate);
    });

    /**
     * Initialize planning navigation
     */
    function initializePlanningNavigation() {
        // Planning selector change
        $(document).on('change', '#tsb-planning-select', function() {
            currentPlanning = parseInt($(this).val());
            tsb_ajax.current_planning = currentPlanning;
            updatePlanningNavigationButtons();
            loadCurrentView();
        });
        
        // Planning navigation buttons
        $(document).on('click', '#tsb-prev-planning', function() {
            navigatePlanning(-1);
        });
        
        $(document).on('click', '#tsb-next-planning', function() {
            navigatePlanning(1);
        });
    }
    
    /**
     * Load available plannings
     */
    function loadPlannings() {
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_plannings',
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    plannings = response.data;
                    updatePlanningNavigationButtons();
                }
            },
            error: function() {
                console.log('Error loading plannings');
            }
        });
    }
    
    /**
     * Navigate to previous/next planning
     */
    function navigatePlanning(direction) {
        if (plannings.length === 0) return;
        
        const currentIndex = plannings.findIndex(p => p.id == currentPlanning);
        if (currentIndex === -1) return;
        
        let newIndex = currentIndex + direction;
        if (newIndex < 0) newIndex = plannings.length - 1;
        if (newIndex >= plannings.length) newIndex = 0;
        
        currentPlanning = parseInt(plannings[newIndex].id);
        tsb_ajax.current_planning = currentPlanning;
        
        // Update selector
        $('#tsb-planning-select').val(currentPlanning);
        
        updatePlanningNavigationButtons();
        loadCurrentView();
    }
    
    /**
     * Update planning navigation button states
     */
    function updatePlanningNavigationButtons() {
        if (plannings.length <= 1) {
            $('#tsb-prev-planning, #tsb-next-planning').prop('disabled', true);
        } else {
            $('#tsb-prev-planning, #tsb-next-planning').prop('disabled', false);
        }
    }

    /**
     * Initialize view controls
     */
    function initializeViewControls() {
        $('.tsb-view-btn').on('click', function() {
            const view = $(this).data('view');
            switchView(view);
        });
    }
    
    /**
     * Switch between daily and weekly views
     */
    function switchView(view) {
        currentView = view;
        
        // Update active button
        $('.tsb-view-btn').removeClass('active');
        $(`.tsb-view-btn[data-view="${view}"]`).addClass('active');
        
        // Masquer les boutons de navigation en Vue Large
        if (view === 'weekly') {
            $('#tsb-prev-date, #tsb-next-date').hide();
            $('#tsb-daily-view').hide();
            $('#tsb-weekly-view').show();
            loadWeeklyView();
        } else {
            $('#tsb-prev-date, #tsb-next-date').show();
            $('#tsb-weekly-view').hide();
            $('#tsb-daily-view').show();
            loadCurrentDateSlots();
        }
        
        updateDateDisplay();
    }
    
    /**
     * Set week start date (Monday)
     */
    function setWeekStart(date) {
        currentWeekStart = new Date(date);
        const day = currentWeekStart.getDay();
        const diff = currentWeekStart.getDate() - day + (day === 0 ? -6 : 1); // Adjust for Monday start
        currentWeekStart.setDate(diff);
    }
    
    /**
     * Load current view based on selected view type
     */
    function loadCurrentView() {
        if (currentView === 'weekly') {
            loadWeeklyView();
        } else {
            loadCurrentDateSlots();
        }
    }
    function initializeDateNavigation() {
        // Set current date
        updateDateDisplay();
        
        // Masquer les boutons de navigation si on est en Vue Large
        if (currentView === 'weekly') {
            $('#tsb-prev-date, #tsb-next-date').hide();
        }
        
        // Bind navigation events
        $('#tsb-prev-date').on('click', function() {
            if (currentView === 'weekly') {
                navigateWeek(-1);
            } else {
                navigateDate(-1);
            }
        });
        
        $('#tsb-next-date').on('click', function() {
            if (currentView === 'weekly') {
                navigateWeek(1);
            } else {
                navigateDate(1);
            }
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
     * Navigate to previous/next week
     */
    function navigateWeek(direction) {
        // Désactiver la navigation pour Vue Large - toujours afficher la semaine actuelle
        if (currentView === 'weekly') {
            return; // Ne rien faire en Vue Large
        }
        
        currentWeekStart.setDate(currentWeekStart.getDate() + (direction * 7));
        currentDate = new Date(currentWeekStart);
        updateDateDisplay();
        updateNavigationButtons();
        loadWeeklyView();
    }

    /**
     * Update date display
     */
    function updateDateDisplay() {
        if (currentView === 'weekly') {
            // Pour Vue Large, toujours afficher la semaine actuelle
            const today = new Date();
            const weekStart = new Date(today);
            const day = weekStart.getDay();
            const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1); // Ajuster pour lundi
            weekStart.setDate(diff);
            
            const endDate = new Date(weekStart);
            endDate.setDate(endDate.getDate() + 6);
            
            const startStr = weekStart.toLocaleDateString('fr-FR', { 
                day: 'numeric', 
                month: 'short' 
            });
            const endStr = endDate.toLocaleDateString('fr-FR', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric' 
            });
            
            $('#tsb-current-date').text(`Semaine du ${startStr} au ${endStr}`);
        } else {
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            $('#tsb-current-date').text(currentDate.toLocaleDateString('fr-FR', options));
        }
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
        
        // Generate weekly slots button
        $(document).on('click', '#tsb-generate-weekly-btn', handleGenerateWeeklySlots);
        
        // User registration
        $(document).on('click', '.tsb-register-btn', showRegisterModal);
        $(document).on('submit', '#tsb-register-form', handleUserRegistration);
        
        // User unregistration - REMOVED: Now using direct click on names
        // $(document).on('click', '.tsb-unregister-btn', showUnregisterModal);
        // $(document).on('submit', '#tsb-unregister-form', handleUserUnregistration);
        
        // Direct unregistration by clicking on registered user names
        $(document).on('click', '.tsb-registered-user', handleDirectUnregistration);
        $(document).on('click', '.tsb-registered-user-mini', handleDirectUnregistration);
        
        // Block slot functionality
        $(document).on('click', '.tsb-block-btn', showBlockSlotModal);
        $(document).on('click', '.tsb-unblock-btn', showBlockSlotModal);
        $(document).on('submit', '#tsb-block-slot-form', handleBlockSlot);
        $(document).on('change', '#tsb-block-checkbox', toggleBlockReasonField);
        
        // Block mode toggle
        $(document).on('click', '#tsb-block-mode-btn', toggleBlockingMode);
        
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
                planning_id: currentPlanning,
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
                        ${isAdmin ? '<p>Utilisez le bouton "Ajouter un créneau" ou "Générer la semaine type" pour créer des créneaux.</p>' : ''}
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
        const isBlocked = parseInt(slot.is_blocked || 0) === 1;
        
        let slotClass = 'tsb-slot-item ';
        if (isBlocked) {
            slotClass += 'tsb-slot-blocked';
        } else if (isAvailable) {
            slotClass += 'tsb-slot-available';
        } else {
            slotClass += 'tsb-slot-full';
        }
        
        let status = '';
        if (isBlocked) {
            status = `Bloqué${slot.block_reason ? ` (${slot.block_reason})` : ''}`;
        } else if (isAvailable) {
            status = 'Disponible';
        } else {
            status = 'Complet';
        }
        
        let actions = '';
        if (!isBlocked && isAvailable) {
            actions += `<button class="tsb-btn tsb-btn-success tsb-btn-small tsb-register-btn" data-slot-id="${slot.id}">S'inscrire</button>`;
        }
        // REMOVED: Désinscription button - now using direct click on names
        // if (!isBlocked && parseInt(slot.registered_count) > 0) {
        //     actions += `<button class="tsb-btn tsb-btn-warning tsb-btn-small tsb-unregister-btn" data-slot-id="${slot.id}">Me désinscrire</button>`;
        // }
        if (isAdmin) {
            if (isBlocked) {
                actions += `<button class="tsb-btn tsb-btn-success tsb-btn-small tsb-unblock-btn" data-slot-id="${slot.id}">Débloquer</button>`;
            } else {
                actions += `<button class="tsb-btn tsb-btn-warning tsb-btn-small tsb-block-btn" data-slot-id="${slot.id}">Bloquer</button>`;
            }
            actions += `<button class="tsb-btn tsb-btn-danger tsb-btn-small tsb-remove-btn" data-slot-id="${slot.id}">Supprimer</button>`;
        }
        
        let registeredNames = '';
        if (slot.registrations && slot.registrations.length > 0) {
            registeredNames = '<div class="tsb-slot-names">Inscrits : ';
            registeredNames += slot.registrations.map(function(reg) {
                return `<span class="tsb-registered-user" data-slot-id="${slot.id}" data-first-name="${reg.user_first_name}" data-last-name="${reg.user_last_name}" title="Cliquez pour désinscrire ${reg.user_first_name} ${reg.user_last_name}">${reg.user_first_name} ${reg.user_last_name.charAt(0)}.<span class="tsb-unregister-cross">×</span></span>`;
            }).join(', ');
            registeredNames += '</div>';
        }
        const slotElement = $(`
            <div class="${slotClass}" data-slot-id="${slot.id}">
                <div class="tsb-slot-info">
                    <div class="tsb-slot-status">${status}</div>
                    <div class="tsb-slot-capacity">
                        ${slot.registered_count}/${slot.capacity} inscrit(s)
                    </div>
                    ${registeredNames}
                </div>
                <div class="tsb-slot-actions">
                    ${actions}
                </div>
            </div>
        `);
        
        container.append(slotElement);
    }

    /**
     * Load weekly view
     */
    function loadWeeklyView() {
        showLoading('#tsb-weekly-table-body');

        // Toujours utiliser la date d'aujourd'hui pour la Vue Large (pas de navigation)
        const today = new Date();
        const startDateString = formatDateForServer(today);

        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_week_slots',
                start_date: startDateString,
                planning_id: currentPlanning,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderWeeklySlots(response.data, startDateString);
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
     * Render weekly slots
     */
    function renderWeeklySlots(slots, startDateString) {
        const tbody = $('#tsb-weekly-table-body');
        tbody.empty();
        
        if (!slots || slots.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="8" class="tsb-empty-state">
                        <h4>Aucun créneau disponible</h4>
                        <p>Aucun créneau horaire n'a été défini pour cette semaine.</p>
                    </td>
                </tr>
            `);
            return;
        }

        // Group slots by time and day
        const slotsByTimeAndDay = {};
        const timeSlots = new Set();

        slots.forEach(slot => {
            const timeKey = `${slot.start_time.substring(0, 5)}-${slot.end_time.substring(0, 5)}`;
            // Calculer l'index du jour par rapport à la date de début de la semaine
            const slotDate = new Date(slot.date + 'T00:00:00');
            const weekStartDate = new Date(startDateString + 'T00:00:00'); // Utiliser la date envoyée au serveur
            slotDate.setHours(0, 0, 0, 0);
            weekStartDate.setHours(0, 0, 0, 0);

            // Calculer la différence en jours par rapport à la date de début
            const diffTime = slotDate.getTime() - weekStartDate.getTime();
            const dayOffset = Math.floor(diffTime / (1000 * 60 * 60 * 24));

            console.log('Slot:', slot.start_time, '-', slot.end_time, 'Date:', slot.date, 'WeekStart:', startDateString, 'dayOffset:', dayOffset);

            timeSlots.add(timeKey);

            if (!slotsByTimeAndDay[timeKey]) {
                slotsByTimeAndDay[timeKey] = {};
            }

            if (!slotsByTimeAndDay[timeKey][dayOffset]) {
                slotsByTimeAndDay[timeKey][dayOffset] = [];
            }

            slotsByTimeAndDay[timeKey][dayOffset].push(slot);
        });

        // Sort time slots
        const sortedTimeSlots = Array.from(timeSlots).sort();

        // Générer les dates des 7 jours consécutifs à partir d'aujourd'hui
        const days = [];
        const today = new Date();
        for (let i = 0; i < 7; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            days.push(d);
        }

        // Mettre à jour l'en-tête du tableau
        const thead = $('#tsb-weekly-view table thead tr');
        thead.empty();
        thead.append('<th class="time-header">Horaires</th>');
        days.forEach((date, idx) => {
            const options = { weekday: 'short', day: 'numeric', month: 'short' };
            thead.append(`<th>${date.toLocaleDateString('fr-FR', options)}</th>`);
        });

        // Render each time slot row
        sortedTimeSlots.forEach(timeKey => {
            const [startTime, endTime] = timeKey.split('-');
            const row = $('<tr></tr>');
            row.append(`<td class="time-cell">${startTime}<br><small>${endTime}</small></td>`);

            // Add cells for each of the 7 days (0 = aujourd'hui, 1 = demain, etc.)
            for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
                const dayCell = $('<td class="tsb-weekly-slot"></td>');
                if (slotsByTimeAndDay[timeKey] && slotsByTimeAndDay[timeKey][dayIndex]) {
                    slotsByTimeAndDay[timeKey][dayIndex].forEach(slot => {
                        const slotElement = renderWeeklySlotItem(slot);
                        dayCell.append(slotElement);
                    });
                }
                row.append(dayCell);
            }
            tbody.append(row);
        });
    }

    /**
     * Render individual weekly slot item
     */
    function renderWeeklySlotItem(slot) {
        const isAvailable = parseInt(slot.registered_count) < parseInt(slot.capacity);
        const isBlocked = parseInt(slot.is_blocked) === 1;
        
        let slotClass = 'tsb-slot-item-mini ';
        if (isBlocked) {
            slotClass += 'tsb-slot-blocked-mini';
        } else if (isAvailable) {
            slotClass += 'tsb-slot-available-mini';
        } else {
            slotClass += 'tsb-slot-full-mini';
        }
        
        let status = '';
        if (isBlocked) {
            status = 'Bloqué';
        } else if (isAvailable) {
            status = 'Libre';
        } else {
            status = 'Complet';
        }
        
        let registeredNames = '';
        if (slot.registrations && slot.registrations.length > 0) {
            registeredNames = '<div class="tsb-slot-names-mini">Inscrits : ';
            registeredNames += slot.registrations.map(function(reg) {
                return `<span class="tsb-registered-user-mini" data-slot-id="${slot.id}" data-first-name="${reg.user_first_name}" data-last-name="${reg.user_last_name}" title="Cliquez pour désinscrire ${reg.user_first_name} ${reg.user_last_name}">${reg.user_first_name} ${reg.user_last_name.charAt(0)}.<span class="tsb-unregister-cross-mini">×</span></span>`;
            }).join(', ');
            registeredNames += '</div>';
        }
        const slotElement = $(`
            <div class="${slotClass}" data-slot-id="${slot.id}" title="${status}">
                <div class="tsb-slot-status-mini">${status}</div>
                <div class="tsb-slot-capacity-mini">${slot.registered_count}/${slot.capacity}</div>
                ${registeredNames}
            </div>
        `);
        
        // Add click handlers
        if (!isBlocked && isAvailable) {
            slotElement.on('click', function() {
                showRegisterModal({ target: this });
            });
        }
        
        return slotElement;
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
     * Show block slot modal
     */
    function showBlockSlotModal(event) {
        const slotId = $(event.target).data('slot-id');
        const isCurrentlyBlocked = $(event.target).hasClass('tsb-unblock-btn');
        
        $('#tsb-block-slot-id').val(slotId);
        $('#tsb-block-checkbox').prop('checked', !isCurrentlyBlocked);
        $('#tsb-block-action').val(isCurrentlyBlocked ? 'unblock' : 'block');
        
        if (isCurrentlyBlocked) {
            $('#tsb-block-reason-group').hide();
        } else {
            $('#tsb-block-reason-group').show();
        }
        
        $('#tsb-block-slot-modal').show();
        if (!isCurrentlyBlocked) {
            $('#tsb-block-reason').focus();
        }
    }

    /**
     * Toggle block reason field
     */
    function toggleBlockReasonField() {
        const isBlocked = $('#tsb-block-checkbox').is(':checked');
        if (isBlocked) {
            $('#tsb-block-reason-group').show();
        } else {
            $('#tsb-block-reason-group').hide();
        }
    }

    /**
     * Toggle blocking mode
     */
    function toggleBlockingMode() {
        blockingMode = !blockingMode;
        const btn = $('#tsb-block-mode-btn');
        
        if (blockingMode) {
            btn.removeClass('tsb-btn-warning').addClass('tsb-btn-danger').text('Mode normal');
            showSuccess('Mode blocage activé. Cliquez sur les créneaux pour les bloquer/débloquer.');
        } else {
            btn.removeClass('tsb-btn-danger').addClass('tsb-btn-warning').text('Mode blocage');
            showSuccess('Mode blocage désactivé.');
        }
    }

    /**
     * Close all modals
     */
    function closeModal() {
        $('.tsb-modal').hide();
        // Reset forms
        $('#tsb-add-slot-form')[0].reset();
        $('#tsb-register-form')[0].reset();
        $('#tsb-unregister-form')[0].reset();
        $('#tsb-block-slot-form')[0].reset();
        $('#tsb-block-reason-group').show(); // Reset to default state
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
                planning_id: currentPlanning,
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
     * Handle generate weekly slots
     */
    function handleGenerateWeeklySlots() {
        if (!confirm('Générer les créneaux de la semaine type pour ce planning ? Cela ajoutera les créneaux manquants pour les 7 prochains jours.')) {
            return;
        }
        
        // Show loading state
        const btn = $('#tsb-generate-weekly-btn');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Génération...');
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_weekly_slots',
                planning_id: currentPlanning,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data || tsb_ajax.messages.generate_weekly_success);
                    loadCurrentView();
                } else {
                    showError(response.data || tsb_ajax.messages.generate_weekly_error);
                }
            },
            error: function() {
                showError(tsb_ajax.messages.generate_weekly_error);
            },
            complete: function() {
                btn.prop('disabled', false).text(originalText);
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
        
        if (!slotId || !userFirstName || !userLastName) {
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
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Inscription réussie !');
                    closeModal();
                    loadCurrentView();
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
                    loadCurrentView();
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
    function showLoading(target = '#tsb-table-body') {
        const colspan = target === '#tsb-weekly-table-body' ? '8' : '2';
        $(target).html(`
            <tr>
                <td colspan="${colspan}" class="tsb-loading">
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

    /**
     * Handle direct unregistration by clicking on user names
     */
    function handleDirectUnregistration(event) {
        event.stopPropagation(); // Prevent triggering parent click handlers
        
        const slotId = $(event.currentTarget).data('slot-id');
        const userFirstName = $(event.currentTarget).data('first-name');
        const userLastName = $(event.currentTarget).data('last-name');
        
        if (!confirm(`Voulez-vous vraiment désinscrire ${userFirstName} ${userLastName} de ce créneau ?`)) {
            return;
        }
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'unregister_user_slot',
                slot_id: slotId,
                user_first_name: userFirstName,
                user_last_name: userLastName,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Désinscription réussie !');
                    loadCurrentView();
                } else {
                    showError(response.data || 'Erreur lors de la désinscription.');
                }
            },
            error: function() {
                showError('Erreur de communication avec le serveur.');
            }
        });
    }

    /**
     * Handle slot blocking/unblocking
     */
    function handleBlockSlot(event) {
        event.preventDefault();
        
        const slotId = $('#tsb-block-slot-id').val();
        const isBlocked = $('#tsb-block-checkbox').is(':checked') ? 1 : 0;
        const blockReason = $('#tsb-block-reason').val();
        
        if (!slotId) {
            showError('Erreur: ID du créneau manquant.');
            return;
        }
        
        $.ajax({
            url: tsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_slot_block',
                slot_id: slotId,
                is_blocked: isBlocked,
                block_reason: blockReason,
                nonce: tsb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                    closeModal();
                    loadCurrentView();
                } else {
                    showError(response.data || 'Erreur lors de la modification du créneau.');
                }
            },
            error: function() {
                showError('Erreur de communication avec le serveur.');
            }
        });
    }

})(jQuery);