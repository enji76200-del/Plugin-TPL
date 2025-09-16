jQuery(document).ready(function($) {
    var currentDate = new Date();
    var startDate = new Date();
    
    // Initialize the table
    updateTable();
    
    // Date navigation
    $('#prev-date').on('click', function() {
        currentDate.setDate(currentDate.getDate() - 1);
        updateTable();
    });
    
    $('#next-date').on('click', function() {
        currentDate.setDate(currentDate.getDate() + 1);
        updateTable();
    });
    
    // Add slot button
    $('#add-slot-btn').on('click', function() {
        $('#add-slot-form').toggle();
        if ($('#add-slot-form').is(':visible')) {
            $('#slot-date').val(formatDate(currentDate));
        }
    });
    
    // Cancel add slot
    $('#cancel-slot').on('click', function() {
        $('#add-slot-form').hide();
        clearAddSlotForm();
    });
    
    // Save new slot
    $('#save-slot').on('click', function() {
        var date = $('#slot-date').val();
        var time = $('#slot-time').val();
        var description = $('#slot-description').val();
        
        if (!date || !time) {
            alert('Veuillez sélectionner une date et une heure');
            return;
        }
        
        addTimeSlot(date, time, description);
    });
    
    // Add slot from table (+ button)
    $(document).on('click', '.add-slot-here', function() {
        var date = $(this).data('date');
        $('#slot-date').val(date);
        $('#add-slot-form').show();
    });
    
    // Remove slot
    $(document).on('click', '.remove-slot', function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce créneau ?')) {
            var slotId = $(this).data('slot-id');
            removeTimeSlot(slotId);
        }
    });
    
    // Register for slot
    $(document).on('click', '.slot-cell.clickable', function() {
        var slotId = $(this).data('slot-id');
        var isRegistered = $(this).closest('tr').hasClass('registered');
        
        if (!isRegistered) {
            showRegistrationModal(slotId);
        }
    });
    
    function updateTable() {
        $('#current-date').text(formatDateDisplay(currentDate));
        
        // Update table content via AJAX
        $.ajax({
            url: rtp_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_table_content',
                date: formatDate(currentDate),
                nonce: rtp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#table-body').html(response.data);
                }
            },
            error: function() {
                console.log('Erreur lors de la mise à jour du tableau');
            }
        });
    }
    
    function addTimeSlot(date, time, description) {
        $.ajax({
            url: rtp_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_time_slot',
                date: date,
                time: time,
                description: description,
                nonce: rtp_ajax.nonce
            },
            beforeSend: function() {
                $('#registration-table-container').addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    $('#add-slot-form').hide();
                    clearAddSlotForm();
                    updateTable();
                    showMessage('Créneau ajouté avec succès', 'success');
                } else {
                    showMessage(response.data || 'Erreur lors de l\'ajout du créneau', 'error');
                }
            },
            error: function() {
                showMessage('Erreur de connexion', 'error');
            },
            complete: function() {
                $('#registration-table-container').removeClass('loading');
            }
        });
    }
    
    function removeTimeSlot(slotId) {
        $.ajax({
            url: rtp_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_time_slot',
                slot_id: slotId,
                nonce: rtp_ajax.nonce
            },
            beforeSend: function() {
                $('#registration-table-container').addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    updateTable();
                    showMessage('Créneau supprimé avec succès', 'success');
                } else {
                    showMessage(response.data || 'Erreur lors de la suppression', 'error');
                }
            },
            error: function() {
                showMessage('Erreur de connexion', 'error');
            },
            complete: function() {
                $('#registration-table-container').removeClass('loading');
            }
        });
    }
    
    function showRegistrationModal(slotId) {
        var modal = createRegistrationModal(slotId);
        $('body').append(modal);
        $('#registration-modal').show();
    }
    
    function createRegistrationModal(slotId) {
        return $(`
            <div id="registration-modal" class="registration-modal">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <h3>S'inscrire au créneau</h3>
                    <input type="text" id="user-name" placeholder="Votre nom" required>
                    <input type="email" id="user-email" placeholder="Votre email" required>
                    <div class="modal-buttons">
                        <button class="cancel-btn">Annuler</button>
                        <button class="confirm-btn" data-slot-id="${slotId}">S'inscrire</button>
                    </div>
                </div>
            </div>
        `);
    }
    
    // Modal event handlers
    $(document).on('click', '.close-modal, .cancel-btn', function() {
        $('#registration-modal').remove();
    });
    
    $(document).on('click', '.confirm-btn', function() {
        var slotId = $(this).data('slot-id');
        var userName = $('#user-name').val().trim();
        var userEmail = $('#user-email').val().trim();
        
        if (!userName || !userEmail) {
            alert('Veuillez remplir tous les champs');
            return;
        }
        
        if (!isValidEmail(userEmail)) {
            alert('Veuillez saisir un email valide');
            return;
        }
        
        registerUser(slotId, userName, userEmail);
    });
    
    // Close modal when clicking outside
    $(document).on('click', '#registration-modal', function(e) {
        if (e.target.id === 'registration-modal') {
            $('#registration-modal').remove();
        }
    });
    
    function registerUser(slotId, userName, userEmail) {
        $.ajax({
            url: rtp_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'register_user',
                slot_id: slotId,
                user_name: userName,
                user_email: userEmail,
                nonce: rtp_ajax.nonce
            },
            beforeSend: function() {
                $('.modal-content').addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    $('#registration-modal').remove();
                    updateTable();
                    showMessage('Inscription réussie !', 'success');
                } else {
                    showMessage(response.data || 'Erreur lors de l\'inscription', 'error');
                }
            },
            error: function() {
                showMessage('Erreur de connexion', 'error');
            },
            complete: function() {
                $('.modal-content').removeClass('loading');
            }
        });
    }
    
    function clearAddSlotForm() {
        $('#slot-date').val('');
        $('#slot-time').val('');
        $('#slot-description').val('');
    }
    
    function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function formatDateDisplay(date) {
        var options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('fr-FR', options);
    }
    
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showMessage(message, type) {
        // Remove existing messages
        $('.rtp-message').remove();
        
        var messageClass = type === 'success' ? 'rtp-success' : 'rtp-error';
        var messageHtml = `<div class="rtp-message ${messageClass}" style="
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            z-index: 9999;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        ">${message}</div>`;
        
        $('body').append(messageHtml);
        
        // Auto-remove after 3 seconds
        setTimeout(function() {
            $('.rtp-message').fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Left arrow key for previous date
        if (e.keyCode === 37 && !$(e.target).is('input, textarea')) {
            e.preventDefault();
            $('#prev-date').click();
        }
        // Right arrow key for next date
        if (e.keyCode === 39 && !$(e.target).is('input, textarea')) {
            e.preventDefault();
            $('#next-date').click();
        }
        // Escape key to close modal
        if (e.keyCode === 27) {
            $('#registration-modal').remove();
            $('#add-slot-form').hide();
        }
    });
});